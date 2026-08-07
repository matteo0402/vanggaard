<?php

namespace App;

use App\Jobs\ImportDiscogsCollectionPage;
use App\Models\CollectionItem;
use App\Models\DiscogsAccount;
use App\Models\DiscogsCollectionInstance;
use App\Models\DiscogsSyncRun;
use App\Models\Release;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DiscogsCollectionImporter
{
    /**
     * Start or resume the account's initial collection import.
     */
    public function start(DiscogsAccount $account): DiscogsSyncRun
    {
        $syncRun = DB::transaction(function () use ($account): DiscogsSyncRun {
            DiscogsAccount::query()->whereKey($account)->lockForUpdate()->firstOrFail();

            $activeSyncRun = $account->syncRuns()
                ->where('kind', 'initial_collection_import')
                ->whereIn('status', ['pending', 'running', 'failed'])
                ->latest('id')
                ->first();

            if ($activeSyncRun !== null) {
                $activeSyncRun->update([
                    'status' => 'pending',
                    'error_message' => null,
                ]);

                return $activeSyncRun;
            }

            return $account->syncRuns()->create([
                'kind' => 'initial_collection_import',
                'status' => 'pending',
                'is_full_reconciliation' => false,
            ]);
        });

        ImportDiscogsCollectionPage::dispatch($syncRun->id, $syncRun->last_completed_page + 1);

        return $syncRun;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{next_page: int|null, release_ids_needing_refresh: list<int>}
     */
    public function importPage(
        DiscogsSyncRun $syncRun,
        int $page,
        array $payload,
        CarbonInterface $fetchedAt,
    ): array {
        $validated = $this->validatePage($page, $payload);

        return DB::transaction(function () use ($syncRun, $page, $validated, $fetchedAt): array {
            $lockedSyncRun = DiscogsSyncRun::query()->lockForUpdate()->findOrFail($syncRun->id);

            if ($page <= $lockedSyncRun->last_completed_page) {
                return [
                    'next_page' => $this->nextPage($lockedSyncRun),
                    'release_ids_needing_refresh' => $this->releaseIdsNeedingRefresh($validated['releases'], $fetchedAt),
                ];
            }

            if ($lockedSyncRun->status === 'completed') {
                return ['next_page' => null, 'release_ids_needing_refresh' => []];
            }

            if ($page !== $lockedSyncRun->last_completed_page + 1) {
                throw ValidationException::withMessages([
                    'pagination.page' => 'Collection pages must be imported in order.',
                ]);
            }

            $account = DiscogsAccount::query()->findOrFail($lockedSyncRun->discogs_account_id);
            $releases = collect($validated['releases'])
                ->unique('instance_id')
                ->values();
            $itemsCreated = 0;
            $itemsUpdated = 0;
            $releaseIdsNeedingRefresh = [];

            foreach ($releases as $collectionRelease) {
                $release = $this->releaseFor($collectionRelease['basic_information'], $fetchedAt);
                $instance = DiscogsCollectionInstance::query()
                    ->whereBelongsTo($account)
                    ->where('discogs_instance_id', $collectionRelease['instance_id'])
                    ->lockForUpdate()
                    ->first();

                if ($instance === null) {
                    $collectionItem = CollectionItem::query()->create([
                        'user_id' => $account->user_id,
                        'release_id' => $release->id,
                    ]);
                    $instance = new DiscogsCollectionInstance([
                        'collection_item_id' => $collectionItem->id,
                        'user_id' => $account->user_id,
                        'discogs_instance_id' => $collectionRelease['instance_id'],
                    ]);
                    $instance->discogsAccount()->associate($account);
                    $itemsCreated++;
                } else {
                    $instance->collectionItem()->update(['release_id' => $release->id]);
                    $itemsUpdated++;
                }

                $instance->fill([
                    'last_seen_sync_run_id' => $lockedSyncRun->id,
                    'discogs_folder_id' => $collectionRelease['folder_id'] ?? null,
                    'source_url' => $release->source_url,
                    'fetched_at' => $fetchedAt,
                ])->save();

                if ($this->releaseNeedsRefresh($release, $fetchedAt)) {
                    $releaseIdsNeedingRefresh[] = $release->discogs_id;
                }
            }

            $totalPages = $validated['pagination']['pages'];
            $isComplete = $page >= max(1, $totalPages);
            $lockedSyncRun->fill([
                'status' => $isComplete ? 'completed' : 'running',
                'items_seen' => $lockedSyncRun->items_seen + $releases->count(),
                'items_created' => $lockedSyncRun->items_created + $itemsCreated,
                'items_updated' => $lockedSyncRun->items_updated + $itemsUpdated,
                'last_completed_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $validated['pagination']['items'],
                'started_at' => $lockedSyncRun->started_at ?? $fetchedAt,
                'completed_at' => $isComplete ? $fetchedAt : null,
                'error_message' => null,
            ])->save();

            return [
                'next_page' => $isComplete ? null : $page + 1,
                'release_ids_needing_refresh' => array_values(array_unique($releaseIdsNeedingRefresh)),
            ];
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{pagination: array{page: int, pages: int, items: int}, releases: list<array{instance_id: int, folder_id?: int|null, basic_information: array{id: int, title: string}}>}
     */
    private function validatePage(int $page, array $payload): array
    {
        $validated = Validator::make($payload, [
            'pagination' => ['required', 'array'],
            'pagination.page' => ['required', 'integer:strict', 'min:1'],
            'pagination.pages' => ['required', 'integer:strict', 'min:0'],
            'pagination.items' => ['required', 'integer:strict', 'min:0'],
            'releases' => ['required', 'array'],
            'releases.*.instance_id' => ['required', 'integer:strict', 'min:1'],
            'releases.*.folder_id' => ['sometimes', 'nullable', 'integer:strict', 'min:0'],
            'releases.*.basic_information' => ['required', 'array'],
            'releases.*.basic_information.id' => ['required', 'integer:strict', 'min:1'],
            'releases.*.basic_information.title' => ['required', 'string', 'max:255'],
        ])->validate();

        if (Arr::integer($validated, 'pagination.page') !== $page) {
            throw ValidationException::withMessages([
                'pagination.page' => 'Discogs returned a different collection page than requested.',
            ]);
        }

        $releases = [];

        foreach (Arr::array($validated, 'releases') as $release) {
            if (! is_array($release)) {
                throw ValidationException::withMessages(['releases' => 'Each collection release must be an object.']);
            }

            $basicInformation = Arr::array($release, 'basic_information');
            $folderId = Arr::get($release, 'folder_id');
            $releases[] = [
                'instance_id' => Arr::integer($release, 'instance_id'),
                'folder_id' => $folderId === null ? null : Arr::integer($release, 'folder_id'),
                'basic_information' => [
                    'id' => Arr::integer($basicInformation, 'id'),
                    'title' => Arr::string($basicInformation, 'title'),
                ],
            ];
        }

        return [
            'pagination' => [
                'page' => Arr::integer($validated, 'pagination.page'),
                'pages' => Arr::integer($validated, 'pagination.pages'),
                'items' => Arr::integer($validated, 'pagination.items'),
            ],
            'releases' => $releases,
        ];
    }

    /** @param array{id: int, title: string} $basicInformation */
    private function releaseFor(array $basicInformation, CarbonInterface $fetchedAt): Release
    {
        return Release::query()->firstOrCreate(
            ['discogs_id' => $basicInformation['id']],
            [
                'title' => $basicInformation['title'],
                'source_url' => "https://www.discogs.com/release/{$basicInformation['id']}",
                'fetched_at' => $fetchedAt,
                'source_hash' => hash('sha256', json_encode($basicInformation, JSON_THROW_ON_ERROR)),
                'raw_payload' => [],
                'image_urls' => [],
            ],
        );
    }

    private function nextPage(DiscogsSyncRun $syncRun): ?int
    {
        if ($syncRun->total_pages === null || $syncRun->last_completed_page >= $syncRun->total_pages) {
            return null;
        }

        return $syncRun->last_completed_page + 1;
    }

    /**
     * @param  list<array{instance_id: int, folder_id?: int|null, basic_information: array{id: int, title: string}}>  $collectionReleases
     * @return list<int>
     */
    private function releaseIdsNeedingRefresh(array $collectionReleases, CarbonInterface $fetchedAt): array
    {
        $releaseIds = collect($collectionReleases)
            ->pluck('basic_information.id')
            ->unique()
            ->values();

        return array_values(Release::query()
            ->whereIn('discogs_id', $releaseIds)
            ->get(['discogs_id', 'raw_payload', 'fetched_at'])
            ->filter(fn (Release $release): bool => $this->releaseNeedsRefresh($release, $fetchedAt))
            ->map(fn (Release $release): int => $release->discogs_id)
            ->all());
    }

    private function releaseNeedsRefresh(Release $release, CarbonInterface $fetchedAt): bool
    {
        return $release->raw_payload === []
            || $release->fetched_at->lte(CarbonImmutable::instance($fetchedAt)->subHours(4));
    }
}
