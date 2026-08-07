<?php

namespace App;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Sleep;

class DiscogsRateLimiter
{
    private const string Key = 'discogs:requests';

    private const string LockKey = 'discogs:requests:lock';

    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly LockProvider $locks,
        private readonly int $requestsPerMinute,
    ) {}

    public function acquire(): void
    {
        while ($this->locks->lock(self::LockKey, 5)->block(5, fn () => $this->limiter->attempt(
            self::Key,
            $this->requestsPerMinute,
            fn (): bool => true,
            60,
        )) === false) {
            Sleep::for(max(1, $this->limiter->availableIn(self::Key)))->seconds();
        }
    }

    public function observe(Response $response): void
    {
        if ($response->status() === 429) {
            $retryAfter = $this->integerHeader($response, 'Retry-After');

            $this->exhaust($retryAfter);

            return;
        }

        $limit = $this->integerHeader($response, 'X-Discogs-Ratelimit');
        $remaining = $this->integerHeader($response, 'X-Discogs-Ratelimit-Remaining');

        if ($limit === null || $remaining === null) {
            return;
        }

        $headroom = max(0, $limit - $this->requestsPerMinute);

        if ($remaining <= $headroom) {
            $this->exhaust();
        }
    }

    public function availableInMilliseconds(): int
    {
        return $this->limiter->availableIn(self::Key) * 1000;
    }

    private function exhaust(?int $decaySeconds = null): void
    {
        $this->locks->lock(self::LockKey, 5)->block(5, function () use ($decaySeconds): void {
            if ($decaySeconds !== null) {
                $this->limiter->clear(self::Key);
            }

            $attempts = (int) $this->limiter->attempts(self::Key);
            $missingAttempts = $this->requestsPerMinute - $attempts;

            if ($missingAttempts > 0) {
                $this->limiter->increment(self::Key, max(1, $decaySeconds ?? 60), $missingAttempts);
            }
        });
    }

    private function integerHeader(Response $response, string $name): ?int
    {
        $value = $response->header($name);

        return ctype_digit($value) ? (int) $value : null;
    }
}
