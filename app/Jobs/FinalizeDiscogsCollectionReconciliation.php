<?php

namespace App\Jobs;

use App\DiscogsCollectionImporter;
use App\DiscogsGateway;
use App\Models\DiscogsSyncRun;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Throwable;

class FinalizeDiscogsCollectionReconciliation implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 80;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 120];

    public function __construct(public int $syncRunId) {}

    public function handle(DiscogsGateway $discogs, DiscogsCollectionImporter $importer): void
    {
        $syncRun = DiscogsSyncRun::query()->find($this->syncRunId);

        if ($syncRun === null) {
            return;
        }

        $isComplete = $importer->finalizeReconciliation($syncRun, $discogs, Date::now());

        if (! $isComplete) {
            self::dispatch($syncRun->id);
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->syncRunId;
    }

    public function failed(?Throwable $exception): void
    {
        DiscogsSyncRun::query()
            ->whereKey($this->syncRunId)
            ->where('status', '!=', 'completed')
            ->update([
                'status' => 'failed',
                'error_message' => Str::limit($exception?->getMessage() ?? 'Collection reconciliation failed.', 2000),
            ]);
    }
}
