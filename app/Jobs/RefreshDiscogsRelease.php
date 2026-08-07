<?php

namespace App\Jobs;

use App\DiscogsGateway;
use App\DiscogsReleaseNormalizer;
use App\Models\DiscogsAccount;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshDiscogsRelease implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 80;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 120];

    public function __construct(
        public int $discogsAccountId,
        public int $releaseId,
    ) {}

    public function handle(DiscogsGateway $discogs, DiscogsReleaseNormalizer $normalizer): void
    {
        $account = DiscogsAccount::query()->find($this->discogsAccountId);

        if ($account === null) {
            return;
        }

        $normalizer->normalize($discogs->release($account, $this->releaseId), Date::now());
    }

    public function uniqueId(): string
    {
        return (string) $this->releaseId;
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Discogs release refresh failed.', [
            'discogs_account_id' => $this->discogsAccountId,
            'release_id' => $this->releaseId,
            'exception' => $exception,
        ]);
    }
}
