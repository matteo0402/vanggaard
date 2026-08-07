<?php

namespace App\Jobs;

use App\DiscogsCollectionImporter;
use App\DiscogsFailureMessage;
use App\DiscogsGateway;
use App\DiscogsReleaseRefresher;
use App\Models\DiscogsAccount;
use App\Models\DiscogsSyncRun;
use App\Models\Release;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Throwable;

class ImportDiscogsCollectionPage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 80;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 120];

    public function __construct(
        public int $syncRunId,
        public int $page,
    ) {}

    public function handle(
        DiscogsGateway $discogs,
        DiscogsCollectionImporter $importer,
        DiscogsReleaseRefresher $refresher,
    ): void {
        $syncRun = DiscogsSyncRun::query()->find($this->syncRunId);

        if ($syncRun === null) {
            return;
        }

        $account = DiscogsAccount::query()->findOrFail($syncRun->discogs_account_id);
        $result = $importer->importPage(
            $syncRun,
            $this->page,
            $discogs->collectionPage($account, page: $this->page),
            Date::now(),
        );

        foreach ($result['release_ids_needing_refresh'] as $releaseId) {
            $release = Release::query()->where('discogs_id', $releaseId)->firstOrFail();
            $refresher->queue($account, $release);
        }

        if ($result['next_page'] !== null) {
            self::dispatch($syncRun->id, $result['next_page']);
        }

        if ($result['should_finalize']) {
            FinalizeDiscogsCollectionReconciliation::dispatch($syncRun->id);
        }
    }

    public function uniqueId(): string
    {
        return "{$this->syncRunId}:{$this->page}";
    }

    public function failed(?Throwable $exception): void
    {
        DiscogsSyncRun::query()
            ->whereKey($this->syncRunId)
            ->where('status', '!=', 'completed')
            ->update([
                'status' => 'failed',
                'error_message' => DiscogsFailureMessage::collection($exception),
            ]);
    }
}
