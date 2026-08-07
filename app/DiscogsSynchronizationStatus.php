<?php

namespace App;

use App\Models\DiscogsSyncRun;
use App\Models\Release;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

class DiscogsSynchronizationStatus
{
    private const DISPLAY_MAX_AGE_HOURS = 6;

    /** @return array<string, mixed> */
    public function for(User $user, int $refreshPage = 1): array
    {
        $account = $user->discogsAccount;

        if ($account === null) {
            return [
                'synchronization' => [
                    'account_configured' => false,
                    'current' => null,
                    'last_success' => null,
                    'release_refreshes' => [
                        'active' => 0,
                        'failed' => 0,
                        'latest_error' => null,
                    ],
                ],
                'refreshable_releases' => [],
            ];
        }

        $current = $account->syncRuns()
            ->where('kind', 'collection_reconciliation')
            ->whereIn('status', ['pending', 'running', 'failed'])
            ->latest('id')
            ->first();
        $lastSuccess = $account->syncRuns()
            ->where('kind', 'collection_reconciliation')
            ->where('status', 'completed')
            ->latest('completed_at')
            ->first();
        $ownedReleases = Release::query()->whereHas(
            'collectionItems',
            fn (Builder $query): Builder => $query
                ->whereBelongsTo($user)
                ->where('is_active', true),
        );

        return [
            'synchronization' => [
                'account_configured' => true,
                'current' => $this->syncRun($current),
                'last_success' => $this->syncRun($lastSuccess),
                'release_refreshes' => [
                    'active' => (clone $ownedReleases)
                        ->whereIn('refresh_status', ['queued', 'refreshing'])
                        ->count(),
                    'failed' => (clone $ownedReleases)
                        ->where('refresh_status', 'failed')
                        ->count(),
                    'latest_error' => (clone $ownedReleases)
                        ->where('refresh_status', 'failed')
                        ->latest('refresh_failed_at')
                        ->value('refresh_error'),
                ],
            ],
            'refreshable_releases' => (clone $ownedReleases)
                ->with(['collectionItems' => fn ($query) => $query
                    ->whereBelongsTo($user)
                    ->where('is_active', true)
                    ->oldest('id')])
                ->oldest('fetched_at')
                ->oldest('id')
                ->paginate(5, ['id', 'title', 'discogs_id', 'source_url', 'fetched_at', 'refresh_status'], 'refresh_page', $refreshPage)
                ->through(fn (Release $release): array => $this->refreshableRelease($release)),
        ];
    }

    /** @return array<string, int|string|bool|null> */
    private function refreshableRelease(Release $release): array
    {
        $isFresh = $release->fetched_at->gt(
            CarbonImmutable::instance(Date::now())->subHours(self::DISPLAY_MAX_AGE_HOURS),
        );

        return [
            'id' => $release->id,
            'collection_item_id' => $release->collectionItems->firstOrFail()->id,
            'title' => $isFresh ? $release->title : null,
            'discogs_id' => $isFresh ? $release->discogs_id : null,
            'source_url' => $isFresh ? $release->source_url : null,
            'is_fresh' => $isFresh,
            'refresh_status' => $release->refresh_status,
        ];
    }

    /** @return array<string, int|string|null>|null */
    private function syncRun(?DiscogsSyncRun $syncRun): ?array
    {
        if ($syncRun === null) {
            return null;
        }

        return [
            'status' => $syncRun->status,
            'items_seen' => $syncRun->items_seen,
            'items_created' => $syncRun->items_created,
            'items_updated' => $syncRun->items_updated,
            'items_removed' => $syncRun->items_removed,
            'last_completed_page' => $syncRun->last_completed_page,
            'total_pages' => $syncRun->total_pages,
            'total_items' => $syncRun->total_items,
            'completed_at' => $syncRun->completed_at?->toIso8601String(),
            'error' => $syncRun->status === 'failed' ? $syncRun->error_message : null,
        ];
    }
}
