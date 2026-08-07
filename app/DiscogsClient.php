<?php

namespace App;

use App\Models\DiscogsAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class DiscogsClient implements DiscogsGateway
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $userAgent,
        private readonly int $connectTimeout,
        private readonly int $timeout,
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
                'Authorization' => "Discogs token={$account->personal_access_token}",
                'User-Agent' => $this->userAgent,
            ])
            ->connectTimeout($this->connectTimeout)
            ->timeout($this->timeout);
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
