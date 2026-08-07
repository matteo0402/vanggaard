<?php

namespace App\Jobs;

use App\DiscogsReleaseRefresher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueueStaleDiscogsReleaseRefreshes implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function handle(DiscogsReleaseRefresher $refresher): void
    {
        $refresher->queueStale();
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Scheduled stale Discogs releases could not be queued.', [
            'exception_type' => $exception === null ? null : $exception::class,
        ]);
    }
}
