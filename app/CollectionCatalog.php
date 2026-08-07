<?php

namespace App;

use App\Models\CollectionItem;
use App\Models\Release;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CollectionCatalog
{
    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function for(User $user, ?string $search = null, int $page = 1): LengthAwarePaginator
    {
        $search = filled($search) ? trim($search) : null;

        /** @var LengthAwarePaginator<int, Release> $releases */
        $releases = Release::query()
            ->select(['id', 'title', 'released_year', 'source_url', 'fetched_at', 'image_urls', 'refresh_status'])
            ->whereIn('id', CollectionItem::query()->displayableFor($user)->select('release_id'))
            ->when($search, fn (Builder $query, string $search): Builder => $this->search($query, $user, $search))
            ->with([
                'collectionItems' => fn ($query) => $query
                    ->displayableFor($user)
                    ->select(['id', 'release_id'])
                    ->oldest('id'),
                'personalMetadata' => fn ($query) => $query
                    ->whereBelongsTo($user)
                    ->select(['id', 'user_id', 'release_id', 'rating', 'corrected_year']),
                'artists' => fn ($query) => $query
                    ->select(['artists.id', 'name'])
                    ->wherePivotNull('retired_at')
                    ->orderByPivot('position'),
                'labels' => fn ($query) => $query
                    ->select(['labels.id', 'name'])
                    ->wherePivotNull('retired_at')
                    ->orderByPivot('position'),
                'formats' => fn ($query) => $query
                    ->select(['id', 'release_id', 'position', 'name'])
                    ->whereNull('retired_at')
                    ->orderBy('position'),
            ])
            ->orderBy('title')
            ->orderBy('id')
            ->paginate(perPage: 24, page: $page);

        return $releases->through(fn (Release $release): array => $this->catalogRelease($release));
    }

    /** @return array<string, mixed> */
    public function release(User $user, Release $release): array
    {
        $release->load([
            'personalMetadata' => fn ($query) => $query->whereBelongsTo($user),
            'artists' => fn ($query) => $query
                ->wherePivotNull('retired_at')
                ->orderByPivot('position'),
            'labels' => fn ($query) => $query
                ->wherePivotNull('retired_at')
                ->orderByPivot('position'),
            'formats' => fn ($query) => $query
                ->whereNull('retired_at')
                ->orderBy('position'),
            'tracks' => fn ($query) => $query
                ->whereNull('retired_at')
                ->orderBy('sequence'),
            'collectionItems' => fn ($query) => $query
                ->displayableFor($user)
                ->with(['storageAssignments' => fn ($query) => $query
                    ->whereNull('removed_at')
                    ->with('storageLocation')
                    ->latest('stored_at')])
                ->oldest('id'),
        ]);

        $locations = StorageLocation::query()
            ->whereBelongsTo($user)
            ->get(['id', 'parent_id', 'name'])
            ->keyBy('id');
        $metadata = $release->personalMetadata->isEmpty()
            ? null
            : $release->personalMetadata->firstOrFail();
        $correctedYear = $metadata?->corrected_year;
        $isFresh = $release->isFreshForDisplay();

        return [
            'id' => $release->id,
            'is_fresh' => $isFresh,
            'values' => [
                'title' => [
                    'effective' => $isFresh ? $release->title : null,
                    'discogs' => $isFresh ? $release->title : null,
                    'is_corrected' => false,
                ],
                'year' => [
                    'effective' => $correctedYear ?? ($isFresh ? $release->released_year : null),
                    'discogs' => $isFresh ? $release->released_year : null,
                    'is_corrected' => $correctedYear !== null,
                ],
            ],
            'discogs' => $isFresh ? $this->discogsDetails($release) : null,
            'personal' => [
                'notes' => $metadata?->personal_notes,
                'rating' => $metadata?->rating,
            ],
            'physical_copies' => $release->collectionItems
                ->map(fn (CollectionItem $item): array => [
                    'id' => $item->id,
                    'locations' => $item->storageAssignments
                        ->map(fn ($assignment): string => $this->locationPath(
                            $assignment->storageLocation->id,
                            $locations,
                        ))
                        ->values()
                        ->all(),
                ])
                ->all(),
            'sync' => [
                'status' => $release->refresh_status,
                'fetched_at' => $release->fetched_at->toIso8601String(),
                'refresh_attempted_at' => $release->refresh_attempted_at?->toIso8601String(),
                'refresh_failed_at' => $release->refresh_failed_at?->toIso8601String(),
                'error' => $release->refresh_status === 'failed' ? $release->refresh_error : null,
            ],
        ];
    }

    /** @param Builder<Release> $query
     * @return Builder<Release>
     */
    private function search(Builder $query, User $user, string $search): Builder
    {
        return $query->where(function (Builder $query) use ($user, $search): void {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('fetched_at', '>', now()->subHours(Release::DISPLAY_MAX_AGE_HOURS))
                    ->where(function (Builder $query) use ($search): void {
                        $query->whereLike('title', "%{$search}%")
                            ->orWhereHas('artists', fn (Builder $query): Builder => $query
                                ->whereNull('artist_release.retired_at')
                                ->whereLike('name', "%{$search}%"))
                            ->orWhereHas('labels', fn (Builder $query): Builder => $query
                                ->whereNull('label_release.retired_at')
                                ->whereLike('name', "%{$search}%"));
                    });
            })->orWhereHas('personalMetadata', fn (Builder $query): Builder => $query
                ->whereBelongsTo($user)
                ->whereLike('personal_notes', "%{$search}%"));
        });
    }

    /** @return array<string, mixed> */
    private function catalogRelease(Release $release): array
    {
        $metadata = $release->personalMetadata->isEmpty()
            ? null
            : $release->personalMetadata->firstOrFail();
        $correctedYear = $metadata?->corrected_year;
        $isFresh = $release->isFreshForDisplay();

        return [
            'id' => $release->id,
            'physical_copies_count' => $release->collectionItems->count(),
            'collection_item_ids' => $release->collectionItems->pluck('id')->all(),
            'is_fresh' => $isFresh,
            'refresh_status' => $release->refresh_status,
            'effective_year' => $correctedYear ?? ($isFresh ? $release->released_year : null),
            'is_year_corrected' => $correctedYear !== null,
            'rating' => $metadata?->rating,
            'discogs' => $isFresh ? [
                'title' => $release->title,
                'released_year' => $release->released_year,
                'artists' => $release->artists->pluck('name')->all(),
                'labels' => $release->labels->pluck('name')->all(),
                'formats' => $release->formats->pluck('name')->all(),
                'image_url' => $release->image_urls[0] ?? null,
                'source_url' => $release->source_url,
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function discogsDetails(Release $release): array
    {
        return [
            'discogs_id' => $release->discogs_id,
            'title' => $release->title,
            'country' => $release->country,
            'released' => $release->released,
            'released_year' => $release->released_year,
            'source_url' => $release->source_url,
            'image_url' => $release->image_urls[0] ?? null,
            'artists' => $release->artists->map(function ($artist): array {
                $pivot = $artist->getRelation('pivot');

                return [
                    'name' => $pivot->getAttribute('credited_name') ?: $artist->name,
                    'join' => $pivot->getAttribute('join_text'),
                ];
            })->all(),
            'labels' => $release->labels->map(function ($label): array {
                $pivot = $label->getRelation('pivot');

                return [
                    'name' => $label->name,
                    'catalog_number' => $pivot->getAttribute('catalog_number'),
                ];
            })->all(),
            'formats' => $release->formats->map(fn ($format): array => [
                'name' => $format->name,
                'quantity' => $format->quantity,
                'text' => $format->text,
                'descriptions' => $format->descriptions ?? [],
            ])->all(),
            'tracks' => $release->tracks->map(fn ($track): array => [
                'position' => $track->position,
                'title' => $track->title,
                'duration' => $track->duration,
            ])->all(),
        ];
    }

    /** @param Collection<int, StorageLocation> $locations */
    private function locationPath(int $locationId, Collection $locations): string
    {
        $path = [];
        $location = $locations->get($locationId);

        while ($location !== null) {
            array_unshift($path, $location->name);
            $location = $location->parent_id === null ? null : $locations->get($location->parent_id);
        }

        return implode(' / ', $path);
    }
}
