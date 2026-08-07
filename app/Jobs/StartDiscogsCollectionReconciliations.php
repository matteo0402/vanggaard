<?php

namespace App\Jobs;

use App\DiscogsCollectionImporter;
use App\Models\DiscogsAccount;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class StartDiscogsCollectionReconciliations implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function handle(DiscogsCollectionImporter $importer): void
    {
        DiscogsAccount::query()->eachById(
            fn (DiscogsAccount $account) => $importer->startReconciliation($account),
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Scheduled Discogs collection reconciliation could not be started.', [
            'exception_type' => $exception === null ? null : $exception::class,
        ]);
    }
}
