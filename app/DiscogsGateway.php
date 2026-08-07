<?php

namespace App;

use App\Models\DiscogsAccount;

interface DiscogsGateway
{
    /** @return array<string, mixed> */
    public function identity(DiscogsAccount $account): array;

    /** @return array<string, mixed> */
    public function folders(DiscogsAccount $account): array;

    /** @return array<string, mixed> */
    public function collectionPage(DiscogsAccount $account, int $folderId = 0, int $page = 1, int $perPage = 100): array;

    /** @return array<string, mixed> */
    public function releaseInstance(DiscogsAccount $account, int $releaseId, int $instanceId): array;

    /** @return array<string, mixed> */
    public function release(DiscogsAccount $account, int $releaseId): array;
}
