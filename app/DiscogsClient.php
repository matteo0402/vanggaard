<?php

namespace App;

use App\Models\DiscogsAccount;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use LogicException;
use Throwable;

class DiscogsClient implements DiscogsGateway
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $userAgent,
        private readonly int $connectTimeout,
        private readonly int $timeout,
        private readonly DiscogsRateLimiter $rateLimiter,
        private readonly int $retryAttempts,
        private readonly int $retryBaseDelay,
        private readonly int $retryMaxDelay,
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
    ) {}

    /** @return array<string, mixed> */
    public function identity(DiscogsAccount $account): array
    {
        return $this->get($account, '/oauth/identity');
    }

    /** @return array<string, mixed> */
    public function folders(DiscogsAccount $account): array
    {
        return $this->get($account, "/users/{$account->username}/collection/folders");
    }

    /** @return array<string, mixed> */
    public function collectionPage(DiscogsAccount $account, int $folderId = 0, int $page = 1, int $perPage = 100): array
    {
        return $this->get(
            $account,
            "/users/{$account->username}/collection/folders/{$folderId}/releases",
            ['page' => $page, 'per_page' => $perPage],
        );
    }

    /** @return array<string, mixed> */
    public function releaseInstance(DiscogsAccount $account, int $releaseId, int $instanceId): array
    {
        return $this->get(
            $account,
            "/users/{$account->username}/collection/releases/{$releaseId}/instances/{$instanceId}",
        );
    }

    /** @return array<string, mixed> */
    public function release(DiscogsAccount $account, int $releaseId): array
    {
        return $this->get($account, "/releases/{$releaseId}");
    }

    /**
     * @param  array<string, int>  $query
     * @return array<string, mixed>
     */
    private function get(DiscogsAccount $account, string $path, array $query = []): array
    {
        try {
            $response = $this->request($account)->get($path, $query);
        } catch (ConnectionException) {
            throw new DiscogsRequestException(DiscogsFailure::Transient);
        }

        $this->ensureSuccessful($response);

        $data = $response->json();

        if (! is_array($data)) {
            throw new DiscogsRequestException(DiscogsFailure::Unexpected, $response->status());
        }

        return $data;
    }

    private function request(DiscogsAccount $account): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Accept' => 'application/vnd.discogs.v2.discogs+json',
                'Authorization' => $this->authorization($account),
                'User-Agent' => $this->userAgent,
            ])
            ->connectTimeout($this->connectTimeout)
            ->timeout($this->timeout)
            ->beforeSending(fn () => $this->rateLimiter->acquire())
            ->afterResponse(function (Response $response): Response {
                $this->rateLimiter->observe($response);

                return $response;
            })
            ->retry(
                $this->retryAttempts,
                fn (int $attempt, Exception $exception): int => $this->retryDelay($attempt, $exception),
                fn (Throwable $exception): bool => $this->shouldRetry($exception),
                throw: false,
            );
    }

    private function authorization(DiscogsAccount $account): string
    {
        if ($account->personal_access_token !== null) {
            return "Discogs token={$account->personal_access_token}";
        }

        if ($this->consumerKey === '' || $this->consumerSecret === '') {
            throw new LogicException('Discogs authentication credentials are not configured.');
        }

        return "Discogs key={$this->consumerKey}, secret={$this->consumerSecret}";
    }

    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException) {
            return false;
        }

        return in_array($exception->response->status(), [429, 500, 502, 503, 504], true);
    }

    private function retryDelay(int $attempt, Exception $exception): int
    {
        if ($exception instanceof RequestException) {
            $retryAfter = $exception->response->header('Retry-After');

            if (ctype_digit($retryAfter)) {
                return ((int) $retryAfter * 1000) + random_int(0, $this->retryBaseDelay);
            }
        }

        $exponentialDelay = min(
            $this->retryMaxDelay,
            $this->retryBaseDelay * (2 ** ($attempt - 1)),
        );
        $jitterLimit = min($exponentialDelay, $this->retryMaxDelay - $exponentialDelay);
        $backoff = $exponentialDelay + random_int(0, $jitterLimit);

        if ($exception instanceof RequestException && $exception->response->status() === 429) {
            return max($backoff, $this->rateLimiter->availableInMilliseconds())
                + random_int(0, $this->retryBaseDelay);
        }

        return $backoff;
    }

    private function ensureSuccessful(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $failure = match (true) {
            in_array($response->status(), [401, 403], true) => DiscogsFailure::Authentication,
            $response->notFound() => DiscogsFailure::NotFound,
            $response->status() === 429, $response->serverError() => DiscogsFailure::Transient,
            default => DiscogsFailure::Unexpected,
        };

        throw new DiscogsRequestException($failure, $response->status());
    }
}
