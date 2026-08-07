<?php

namespace App\Jobs;

use App\DiscogsFailureMessage;
use App\DiscogsGateway;
use App\DiscogsReleaseNormalizer;
use App\Models\DiscogsAccount;
use App\Models\Release;
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
        Release::query()->where('discogs_id', $this->releaseId)->update([
            'refresh_status' => 'refreshing',
            'refresh_attempted_at' => Date::now(),
            'refresh_error' => null,
        ]);

        $account = DiscogsAccount::query()->find($this->discogsAccountId);

        if ($account === null) {
            Release::query()->where('discogs_id', $this->releaseId)->update(['refresh_status' => 'idle']);

            return;
        }

        $normalizer->normalize($discogs->release($account, $this->releaseId), Date::now());

        Release::query()->where('discogs_id', $this->releaseId)->update([
            'refresh_status' => 'idle',
            'refresh_failed_at' => null,
            'refresh_error' => null,
        ]);
    }

    public function uniqueId(): string
    {
        return (string) $this->releaseId;
    }

    public function failed(?Throwable $exception): void
    {
        Release::query()->where('discogs_id', $this->releaseId)->update([
            'refresh_status' => 'failed',
            'refresh_failed_at' => Date::now(),
            'refresh_error' => DiscogsFailureMessage::release($exception),
        ]);

        Log::warning('Discogs release refresh failed.', [
            'discogs_account_id' => $this->discogsAccountId,
            'release_id' => $this->releaseId,
            'exception_type' => $exception === null ? null : $exception::class,
        ]);
    }
}
