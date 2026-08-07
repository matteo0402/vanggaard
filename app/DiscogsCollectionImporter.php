<?php

namespace App;

use App\Jobs\FinalizeDiscogsCollectionReconciliation;
use App\Jobs\ImportDiscogsCollectionPage;
use App\Models\CollectionItem;
use App\Models\DiscogsAccount;
use App\Models\DiscogsCollectionInstance;
use App\Models\DiscogsSyncRun;
use App\Models\Release;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

class DiscogsCollectionImporter
{
    private const RECONCILIATION_CONFIRMATION_CHUNK_SIZE = 25;

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
     * Start or resume a full collection reconciliation.
     */
    public function startReconciliation(DiscogsAccount $account): DiscogsSyncRun
    {
        $syncRun = DB::transaction(function () use ($account): DiscogsSyncRun {
            DiscogsAccount::query()->whereKey($account)->lockForUpdate()->firstOrFail();

            $activeSyncRun = $account->syncRuns()
                ->where('kind', 'collection_reconciliation')
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
                'kind' => 'collection_reconciliation',
                'status' => 'pending',
                'is_full_reconciliation' => true,
            ]);
        });

        if ($this->pagesAreComplete($syncRun)) {
            FinalizeDiscogsCollectionReconciliation::dispatch($syncRun->id);
        } else {
            ImportDiscogsCollectionPage::dispatch($syncRun->id, $syncRun->last_completed_page + 1);
        }

        return $syncRun;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{next_page: int|null, release_ids_needing_refresh: list<int>, should_finalize: bool}
     */
    public function importPage(
        DiscogsSyncRun $syncRun,
        int $page,
        array $payload,
        CarbonInterface $fetchedAt,
    ): array {
        $validated = $this->validatePage($page, $payload);

        return DB::transaction(function () use ($syncRun, $page, $validated, $fetchedAt): array {
            $account = DiscogsAccount::query()
                ->whereKey($syncRun->discogs_account_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedSyncRun = DiscogsSyncRun::query()->lockForUpdate()->findOrFail($syncRun->id);

            if ($page <= $lockedSyncRun->last_completed_page) {
                return [
                    'next_page' => $this->nextPage($lockedSyncRun),
                    'release_ids_needing_refresh' => $this->releaseIdsNeedingRefresh($validated['releases'], $fetchedAt),
                    'should_finalize' => $lockedSyncRun->is_full_reconciliation && $this->pagesAreComplete($lockedSyncRun),
                ];
            }

            if ($lockedSyncRun->status === 'completed') {
                return ['next_page' => null, 'release_ids_needing_refresh' => [], 'should_finalize' => false];
            }

            if ($page !== $lockedSyncRun->last_completed_page + 1) {
                throw ValidationException::withMessages([
                    'pagination.page' => 'Collection pages must be imported in order.',
                ]);
            }

            $releases = collect($validated['releases'])
                ->unique('instance_id')
                ->values();
            $itemsCreated = 0;
            $itemsUpdated = 0;
            $itemsSeen = 0;
            $releaseIdsNeedingRefresh = [];

            foreach ($releases as $collectionRelease) {
                $release = $this->releaseFor($collectionRelease['basic_information'], $fetchedAt);
                $instance = DiscogsCollectionInstance::query()
                    ->whereBelongsTo($account)
                    ->where('discogs_instance_id', $collectionRelease['instance_id'])
                    ->lockForUpdate()
                    ->first();

                if ($instance === null) {
                    $collectionItem = CollectionItem::query()
                        ->whereBelongsTo($account->user)
                        ->whereBelongsTo($release)
                        ->where('is_active', false)
                        ->whereDoesntHave('discogsCollectionInstance')
                        ->oldest('id')
                        ->lockForUpdate()
                        ->first();

                    if ($collectionItem === null) {
                        $collectionItem = CollectionItem::query()->create([
                            'user_id' => $account->user_id,
                            'release_id' => $release->id,
                        ]);
                        $itemsCreated++;
                    } else {
                        $collectionItem->update(['is_active' => true]);
                        $itemsUpdated++;
                    }

                    $instance = new DiscogsCollectionInstance([
                        'collection_item_id' => $collectionItem->id,
                        'user_id' => $account->user_id,
                        'discogs_instance_id' => $collectionRelease['instance_id'],
                    ]);
                    $instance->discogsAccount()->associate($account);
                } else {
                    if ($instance->last_seen_sync_run_id !== $lockedSyncRun->id) {
                        $itemsUpdated++;
                    }

                    $instance->collectionItem()->update([
                        'release_id' => $release->id,
                        'is_active' => true,
                    ]);
                }

                if ($instance->last_seen_sync_run_id !== $lockedSyncRun->id) {
                    $itemsSeen++;
                }

                $instance->fill([
                    'last_seen_sync_run_id' => $lockedSyncRun->id,
                    'missing_confirmed_sync_run_id' => null,
                    'discogs_folder_id' => $collectionRelease['folder_id'] ?? null,
                    'source_url' => $release->source_url,
                    'fetched_at' => $fetchedAt,
                ])->save();

                if ($this->releaseNeedsRefresh($release, $fetchedAt)) {
                    $releaseIdsNeedingRefresh[] = $release->discogs_id;
                }
            }

            $totalPages = $validated['pagination']['pages'];
            $pagesAreComplete = $page >= max(1, $totalPages);
            $isComplete = $pagesAreComplete && ! $lockedSyncRun->is_full_reconciliation;
            $lockedSyncRun->fill([
                'status' => $isComplete ? 'completed' : 'running',
                'items_seen' => $lockedSyncRun->items_seen + $itemsSeen,
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
                'next_page' => $pagesAreComplete ? null : $page + 1,
                'release_ids_needing_refresh' => array_values(array_unique($releaseIdsNeedingRefresh)),
                'should_finalize' => $pagesAreComplete && $lockedSyncRun->is_full_reconciliation,
            ];
        }, attempts: 3);
    }

    public function finalizeReconciliation(
        DiscogsSyncRun $syncRun,
        DiscogsGateway $discogs,
        CarbonInterface $completedAt,
    ): bool {
        $syncRun->refresh();

        if ($syncRun->status === 'completed') {
            return true;
        }

        if (! $syncRun->is_full_reconciliation || ! $this->pagesAreComplete($syncRun)) {
            throw new LogicException('Only fully imported reconciliation runs can be finalized.');
        }

        $account = DiscogsAccount::query()->findOrFail($syncRun->discogs_account_id);
        $candidates = $this->pendingReconciliationCandidates($syncRun)
            ->oldest('id')
            ->limit(self::RECONCILIATION_CONFIRMATION_CHUNK_SIZE)
            ->get();

        foreach ($candidates as $instance) {
            try {
                $discogs->releaseInstance(
                    $account,
                    $instance->collectionItem->release->discogs_id,
                    $instance->discogs_instance_id,
                );
                $this->pendingReconciliationCandidates($syncRun)
                    ->whereKey($instance)
                    ->update([
                        'last_seen_sync_run_id' => $syncRun->id,
                        'missing_confirmed_sync_run_id' => null,
                    ]);
            } catch (DiscogsRequestException $exception) {
                if ($exception->failure !== DiscogsFailure::NotFound) {
                    throw $exception;
                }

                $this->pendingReconciliationCandidates($syncRun)
                    ->whereKey($instance)
                    ->update(['missing_confirmed_sync_run_id' => $syncRun->id]);
            }
        }

        if ($this->pendingReconciliationCandidates($syncRun)->exists()) {
            return false;
        }

        return DB::transaction(function () use ($syncRun, $completedAt): bool {
            DiscogsAccount::query()
                ->whereKey($syncRun->discogs_account_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedSyncRun = DiscogsSyncRun::query()->lockForUpdate()->findOrFail($syncRun->id);

            if ($lockedSyncRun->status === 'completed') {
                return true;
            }

            if (! $lockedSyncRun->is_full_reconciliation || ! $this->pagesAreComplete($lockedSyncRun)) {
                throw new LogicException('Only fully imported reconciliation runs can be finalized.');
            }

            if ($this->pendingReconciliationCandidates($lockedSyncRun)->exists()) {
                return false;
            }

            $itemsRemoved = $this->confirmedRemovalCandidates($lockedSyncRun)->count();
            CollectionItem::query()
                ->whereIn(
                    'id',
                    $this->confirmedRemovalCandidates($lockedSyncRun)->select('collection_item_id'),
                )
                ->update(['is_active' => false]);
            $this->confirmedRemovalCandidates($lockedSyncRun)->delete();

            $lockedSyncRun->update([
                'status' => 'completed',
                'items_removed' => $lockedSyncRun->items_removed + $itemsRemoved,
                'completed_at' => $completedAt,
                'error_message' => null,
            ]);

            return true;
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

    private function pagesAreComplete(DiscogsSyncRun $syncRun): bool
    {
        return $syncRun->total_pages !== null
            && $syncRun->last_completed_page >= max(1, $syncRun->total_pages);
    }

    /** @return Builder<DiscogsCollectionInstance> */
    private function reconciliationCandidates(DiscogsSyncRun $syncRun): Builder
    {
        return DiscogsCollectionInstance::query()
            ->where('discogs_account_id', $syncRun->discogs_account_id)
            ->where(function ($query) use ($syncRun): void {
                $query->whereNull('last_seen_sync_run_id')
                    ->orWhere('last_seen_sync_run_id', '!=', $syncRun->id);
            });
    }

    /** @return Builder<DiscogsCollectionInstance> */
    private function pendingReconciliationCandidates(DiscogsSyncRun $syncRun): Builder
    {
        return $this->reconciliationCandidates($syncRun)
            ->where(function ($query) use ($syncRun): void {
                $query->whereNull('missing_confirmed_sync_run_id')
                    ->orWhere('missing_confirmed_sync_run_id', '!=', $syncRun->id);
            })
            ->with('collectionItem.release');
    }

    /** @return Builder<DiscogsCollectionInstance> */
    private function confirmedRemovalCandidates(DiscogsSyncRun $syncRun): Builder
    {
        return $this->reconciliationCandidates($syncRun)
            ->where('missing_confirmed_sync_run_id', $syncRun->id);
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
