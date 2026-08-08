<?php

namespace App;

use App\Models\CollectionItem;
use App\Models\Release;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\Paginator;
use InvalidArgumentException;

class CollectionBrowse
{
    public const DIMENSIONS = [
        'labels' => ['label' => 'Labels', 'description' => 'Record labels represented by active copies.'],
        'artists' => ['label' => 'Artists', 'description' => 'Artists credited on releases you own.'],
        'years' => ['label' => 'Years', 'description' => 'Effective release years, including personal corrections.'],
        'recently-added' => ['label' => 'Recently added', 'description' => 'The latest releases added to your collection.'],
        'recently-updated' => ['label' => 'Recently updated', 'description' => 'Recently refreshed release information.'],
        'videos' => ['label' => 'Videos', 'description' => 'Copies grouped by available release videos.'],
    ];

    /** @return array{dimension: string, dimensions: array<string, array{label: string, description: string}>, entries: Paginator<int, array{value: int, label: string, count: int}>|Paginator<int, array{value: int, label: string, count: int, occurred_at: string}>|Paginator<int, array{value: string, label: string, count: int}>} */
    public function for(User $user, string $dimension): array
    {
        $entries = match ($dimension) {
            'labels' => $this->labels($user),
            'artists' => $this->artists($user),
            'years' => $this->years($user),
            'recently-added' => $this->recentlyAdded($user),
            'recently-updated' => $this->recentlyUpdated($user),
            'videos' => $this->videos($user),
            default => throw new InvalidArgumentException("Unknown browse dimension [{$dimension}]."),
        };

        return [
            'dimension' => $dimension,
            'dimensions' => self::DIMENSIONS,
            'entries' => $entries,
        ];
    }

    /** @return Paginator<int, array{value: int, label: string, count: int}> */
    private function labels(User $user): Paginator
    {
        $entries = $this->ownedItems($user)
            ->join('label_release', function (JoinClause $join): void {
                $join->on('label_release.release_id', '=', 'releases.id')
                    ->whereNull('label_release.retired_at');
            })
            ->join('labels', 'labels.id', '=', 'label_release.label_id')
            ->selectRaw('labels.id as value, labels.name as label, count(distinct collection_items.id) as aggregate_count')
            ->groupBy('labels.id', 'labels.name')
            ->orderBy('labels.name')
            ->simplePaginate(60);

        return $this->facetEntries($entries);
    }

    /** @return Paginator<int, array{value: int, label: string, count: int}> */
    private function artists(User $user): Paginator
    {
        $entries = $this->ownedItems($user)
            ->join('artist_release', function (JoinClause $join): void {
                $join->on('artist_release.release_id', '=', 'releases.id')
                    ->whereNull('artist_release.retired_at');
            })
            ->join('artists', 'artists.id', '=', 'artist_release.artist_id')
            ->selectRaw('artists.id as value, artists.name as label, count(distinct collection_items.id) as aggregate_count')
            ->groupBy('artists.id', 'artists.name')
            ->orderBy('artists.name')
            ->simplePaginate(60);

        return $this->facetEntries($entries);
    }

    /** @return Paginator<int, array{value: int, label: string, count: int}> */
    private function years(User $user): Paginator
    {
        $year = 'coalesce(personal_release_metadata.corrected_year, releases.released_year)';
        $entries = $this->ownedItems($user, freshSourceRequired: false)
            ->leftJoin('personal_release_metadata', function (JoinClause $join) use ($user): void {
                $join->on('personal_release_metadata.release_id', '=', 'releases.id')
                    ->where('personal_release_metadata.user_id', '=', $user->id);
            })
            ->where(function (Builder $query): void {
                $query->whereNotNull('personal_release_metadata.corrected_year')
                    ->orWhere('releases.fetched_at', '>', now()->subHours(Release::DISPLAY_MAX_AGE_HOURS));
            })
            ->whereRaw("{$year} is not null")
            ->selectRaw("{$year} as value, {$year} as label, count(distinct collection_items.id) as aggregate_count")
            ->groupByRaw($year)
            ->orderByDesc('value')
            ->simplePaginate(60);

        return $this->facetEntries($entries);
    }

    /** @return Paginator<int, array{value: int, label: string, count: int, occurred_at: string}> */
    private function recentlyAdded(User $user): Paginator
    {
        return $this->recentEntries(
            $this->ownedItems($user, freshSourceRequired: false)
                ->selectRaw('releases.id as value, case when releases.fetched_at > ? then releases.title else null end as label, count(distinct collection_items.id) as aggregate_count, max(collection_items.created_at) as occurred_at', [now()->subHours(Release::DISPLAY_MAX_AGE_HOURS)])
                ->groupBy('releases.id', 'releases.title', 'releases.fetched_at')
                ->orderByDesc('occurred_at')
                ->simplePaginate(24),
        );
    }

    /** @return Paginator<int, array{value: int, label: string, count: int, occurred_at: string}> */
    private function recentlyUpdated(User $user): Paginator
    {
        return $this->recentEntries(
            $this->ownedItems($user, freshSourceRequired: false)
                ->selectRaw('releases.id as value, case when releases.fetched_at > ? then releases.title else null end as label, count(distinct collection_items.id) as aggregate_count, releases.fetched_at as occurred_at', [now()->subHours(Release::DISPLAY_MAX_AGE_HOURS)])
                ->groupBy('releases.id', 'releases.title', 'releases.fetched_at')
                ->orderByDesc('occurred_at')
                ->simplePaginate(24),
        );
    }

    /** @return Paginator<int, array{value: string, label: string, count: int}> */
    private function videos(User $user): Paginator
    {
        $hasVideo = 'case when exists (select 1 from videos where videos.release_id = releases.id and videos.retired_at is null) then 1 else 0 end';
        $entries = $this->ownedItems($user)
            ->selectRaw("{$hasVideo} as value, count(distinct collection_items.id) as aggregate_count")
            ->groupByRaw($hasVideo)
            ->orderByDesc('value')
            ->simplePaginate(60);

        return $entries->through(function (CollectionItem $entry): array {
            $attributes = $entry->getAttributes();
            $hasVideo = (bool) $attributes['value'];

            return [
                'value' => $hasVideo ? 'with' : 'without',
                'label' => $hasVideo ? 'With video' : 'Without video',
                'count' => (int) $attributes['aggregate_count'],
            ];
        });
    }

    /** @return Builder<CollectionItem> */
    private function ownedItems(User $user, bool $freshSourceRequired = true): Builder
    {
        return CollectionItem::query()
            ->displayableFor($user)
            ->join('releases', 'releases.id', '=', 'collection_items.release_id')
            ->when(
                $freshSourceRequired,
                fn (Builder $query): Builder => $query->where(
                    'releases.fetched_at',
                    '>',
                    now()->subHours(Release::DISPLAY_MAX_AGE_HOURS),
                ),
            );
    }

    /** @param Paginator<int, CollectionItem> $entries
     * @return Paginator<int, array{value: int, label: string, count: int}>
     */
    private function facetEntries(Paginator $entries): Paginator
    {
        return $entries->through(function (CollectionItem $entry): array {
            $attributes = $entry->getAttributes();

            return [
                'value' => (int) $attributes['value'],
                'label' => (string) $attributes['label'],
                'count' => (int) $attributes['aggregate_count'],
            ];
        });
    }

    /** @param Paginator<int, CollectionItem> $entries
     * @return Paginator<int, array{value: int, label: string, count: int, occurred_at: string}>
     */
    private function recentEntries(Paginator $entries): Paginator
    {
        return $entries->through(function (CollectionItem $entry): array {
            $attributes = $entry->getAttributes();

            return [
                'value' => (int) $attributes['value'],
                'label' => $attributes['label'] === null ? 'Discogs details hidden' : (string) $attributes['label'],
                'count' => (int) $attributes['aggregate_count'],
                'occurred_at' => CarbonImmutable::parse($attributes['occurred_at'])->toIso8601String(),
            ];
        });
    }
}
