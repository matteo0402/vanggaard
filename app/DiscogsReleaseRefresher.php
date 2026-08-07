<?php

namespace App;

use App\Jobs\RefreshDiscogsRelease;
use App\Models\DiscogsAccount;
use App\Models\Release;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Throwable;

class DiscogsReleaseRefresher
{
    public function queueStale(): int
    {
        $now = CarbonImmutable::instance(Date::now());
        $staleBefore = $now->subHours((int) config('services.discogs.stale_after_hours'));
        $retryFailedBefore = $now->subMinutes((int) config('services.discogs.refresh_failure_cooldown_minutes'));
        $budget = max(0, (int) config('services.discogs.stale_refresh_budget'));

        if ($budget === 0) {
            return 0;
        }

        $releases = Release::query()
            ->whereHas('collectionItems', fn (Builder $query): Builder => $query
                ->where('is_active', true)
                ->whereHas('user.discogsAccount'))
            ->where(function (Builder $query) use ($staleBefore): void {
                $query->whereJsonLength('raw_payload', 0)
                    ->orWhere('fetched_at', '<=', $staleBefore);
            })
            ->whereNotIn('refresh_status', ['queued', 'refreshing'])
            ->where(function (Builder $query) use ($retryFailedBefore): void {
                $query->where('refresh_status', '!=', 'failed')
                    ->orWhereNull('refresh_failed_at')
                    ->orWhere('refresh_failed_at', '<=', $retryFailedBefore);
            })
            ->with(['collectionItems' => fn ($query) => $query
                ->where('is_active', true)
                ->whereHas('user.discogsAccount')
                ->with('user.discogsAccount')
                ->oldest('id')])
            ->oldest('fetched_at')
            ->oldest('id')
            ->limit($budget)
            ->get();

        $queued = 0;

        foreach ($releases as $release) {
            $account = $release->collectionItems->first()?->user->discogsAccount;

            if ($account !== null && $this->queue($account, $release)) {
                $queued++;
            }
        }

        return $queued;
    }

    public function queue(DiscogsAccount $account, Release $release, string $queue = 'default'): bool
    {
        $previousStatus = $release->refresh_status;
        $queued = Release::query()
            ->whereKey($release)
            ->whereNotIn('refresh_status', ['queued', 'refreshing'])
            ->update([
                'refresh_status' => 'queued',
            ]);

        if ($queued === 0) {
            return false;
        }

        try {
            RefreshDiscogsRelease::dispatch($account->id, $release->discogs_id)->onQueue($queue);
        } catch (Throwable $exception) {
            Release::query()
                ->whereKey($release)
                ->where('refresh_status', 'queued')
                ->update(['refresh_status' => $previousStatus]);

            throw $exception;
        }

        return true;
    }
}
