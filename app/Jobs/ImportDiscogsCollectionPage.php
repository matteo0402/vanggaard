<?php

namespace App\Jobs;

use App\DiscogsCollectionImporter;
use App\DiscogsGateway;
use App\Models\DiscogsAccount;
use App\Models\DiscogsSyncRun;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
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

    public function handle(DiscogsGateway $discogs, DiscogsCollectionImporter $importer): void
    {
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
            RefreshDiscogsRelease::dispatch($account->id, $releaseId);
        }

        if ($result['next_page'] !== null) {
            self::dispatch($syncRun->id, $result['next_page']);
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
                'error_message' => Str::limit($exception?->getMessage() ?? 'Collection import failed.', 2000),
            ]);
    }
}
