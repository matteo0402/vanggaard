<?php

use App\DiscogsCollectionImporter;
use App\DiscogsFailure;
use App\DiscogsGateway;
use App\DiscogsReleaseRefresher;
use App\DiscogsRequestException;
use App\Jobs\ImportDiscogsCollectionPage;
use App\Jobs\RefreshDiscogsRelease;
use App\Models\CollectionItem;
use App\Models\DiscogsAccount;
use App\Models\DiscogsCollectionInstance;
use App\Models\DiscogsSyncRun;
use App\Models\Release;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

uses(LazilyRefreshDatabase::class);

function collectionPage(int $page = 1, int $pages = 1, int $items = 1, array $releases = []): array
{
    return [
        'pagination' => compact('page', 'pages', 'items'),
        'releases' => $releases,
    ];
}

function collectionRelease(int $instanceId, int $releaseId, ?int $folderId = 0): array
{
    return [
        'instance_id' => $instanceId,
        'folder_id' => $folderId,
        'basic_information' => [
            'id' => $releaseId,
            'title' => "Release {$releaseId}",
        ],
    ];
}

test('starting an import immediately returns progress and queues the first page', function () {
    Queue::fake();
    $account = DiscogsAccount::factory()->create();

    $syncRun = app(DiscogsCollectionImporter::class)->start($account);

    expect($syncRun)
        ->kind->toBe('initial_collection_import')
        ->status->toBe('pending')
        ->is_full_reconciliation->toBeFalse()
        ->last_completed_page->toBe(0);
    Queue::assertPushed(
        ImportDiscogsCollectionPage::class,
        fn (ImportDiscogsCollectionPage $job): bool => $job->syncRunId === $syncRun->id && $job->page === 1,
    );
});

test('starting again resumes the active import instead of creating a duplicate run', function () {
    Queue::fake();
    $account = DiscogsAccount::factory()->create();
    $syncRun = DiscogsSyncRun::factory()->for($account)->create([
        'kind' => 'initial_collection_import',
        'status' => 'running',
        'last_completed_page' => 7,
        'total_pages' => 61,
    ]);

    $resumedSyncRun = app(DiscogsCollectionImporter::class)->start($account);

    expect($resumedSyncRun->is($syncRun))->toBeTrue()
        ->and(DiscogsSyncRun::query()->count())->toBe(1);
    Queue::assertPushed(
        ImportDiscogsCollectionPage::class,
        fn (ImportDiscogsCollectionPage $job): bool => $job->page === 8,
    );
});

test('a failed import resumes from its completed page checkpoint', function () {
    Queue::fake();
    $account = DiscogsAccount::factory()->create();
    $syncRun = DiscogsSyncRun::factory()->for($account)->create([
        'kind' => 'initial_collection_import',
        'status' => 'failed',
        'last_completed_page' => 7,
        'total_pages' => 61,
        'error_message' => 'Worker stopped.',
    ]);

    $resumedSyncRun = app(DiscogsCollectionImporter::class)->start($account);

    expect($resumedSyncRun->refresh())
        ->status->toBe('pending')
        ->last_completed_page->toBe(7)
        ->error_message->toBeNull();
    Queue::assertPushed(
        ImportDiscogsCollectionPage::class,
        fn (ImportDiscogsCollectionPage $job): bool => $job->page === 8,
    );
});

test('a page imports distinct physical copies and queues one missing release refresh', function () {
    Queue::fake();
    Date::setTestNow('2026-08-07 20:00:00');
    $account = DiscogsAccount::factory()->create();
    $syncRun = DiscogsSyncRun::factory()->for($account)->create([
        'kind' => 'initial_collection_import',
    ]);
    $payload = collectionPage(items: 2, releases: [
        collectionRelease(101, 42, 3),
        collectionRelease(102, 42, 4),
    ]);
    /** @var DiscogsGateway&MockInterface $discogs */
    $discogs = mock(DiscogsGateway::class);
    $discogs->shouldReceive('collectionPage')->once()->andReturn($payload);

    (new ImportDiscogsCollectionPage($syncRun->id, 1))->handle(
        $discogs,
        app(DiscogsCollectionImporter::class),
        app(DiscogsReleaseRefresher::class),
    );

    $syncRun->refresh();
    expect($syncRun)
        ->status->toBe('completed')
        ->items_seen->toBe(2)
        ->items_created->toBe(2)
        ->items_updated->toBe(0)
        ->last_completed_page->toBe(1)
        ->total_items->toBe(2)
        ->completed_at->not->toBeNull()
        ->and(CollectionItem::query()->count())->toBe(2)
        ->and(DiscogsCollectionInstance::query()->pluck('discogs_instance_id')->all())->toBe([101, 102])
        ->and(Release::query()->where('discogs_id', 42)->firstOrFail()->raw_payload)->toBe([])
        ->and(Release::query()->where('discogs_id', 42)->firstOrFail()->refresh_status)->toBe('queued');
    Queue::assertPushedTimes(RefreshDiscogsRelease::class, 1);
});

test('large collections continue one queued page at a time', function () {
    Queue::fake();
    $account = DiscogsAccount::factory()->create();
    $syncRun = DiscogsSyncRun::factory()->for($account)->create([
        'kind' => 'initial_collection_import',
    ]);
    /** @var DiscogsGateway&MockInterface $discogs */
    $discogs = mock(DiscogsGateway::class);
    $discogs->shouldReceive('collectionPage')->once()->andReturn(
        collectionPage(page: 1, pages: 61, items: 6001, releases: [collectionRelease(1, 42)]),
    );

    (new ImportDiscogsCollectionPage($syncRun->id, 1))->handle(
        $discogs,
        app(DiscogsCollectionImporter::class),
        app(DiscogsReleaseRefresher::class),
    );

    expect($syncRun->refresh())
        ->status->toBe('running')
        ->last_completed_page->toBe(1)
        ->total_pages->toBe(61)
        ->total_items->toBe(6001);
    Queue::assertPushed(
        ImportDiscogsCollectionPage::class,
        fn (ImportDiscogsCollectionPage $job): bool => $job->page === 2,
    );
});

test('existing release metadata is refreshed within the four hour collection cycle', function () {
    Queue::fake();
    Date::setTestNow('2026-08-07 20:00:00');
    $account = DiscogsAccount::factory()->create();
    Release::factory()->create([
        'discogs_id' => 42,
        'fetched_at' => now()->subHours(4),
        'raw_payload' => ['id' => 42, 'title' => 'Release 42'],
    ]);
    $syncRun = DiscogsSyncRun::factory()->for($account)->create([
        'kind' => 'initial_collection_import',
    ]);
    /** @var DiscogsGateway&MockInterface $discogs */
    $discogs = mock(DiscogsGateway::class);
    $discogs->shouldReceive('collectionPage')->once()->andReturn(
        collectionPage(releases: [collectionRelease(1, 42)]),
    );

    (new ImportDiscogsCollectionPage($syncRun->id, 1))->handle(
        $discogs,
        app(DiscogsCollectionImporter::class),
        app(DiscogsReleaseRefresher::class),
    );

    Queue::assertPushed(
        RefreshDiscogsRelease::class,
        fn (RefreshDiscogsRelease $job): bool => $job->releaseId === 42,
    );
});

test('retrying an interrupted request resumes from the unchanged checkpoint', function () {
    Queue::fake();
    $account = DiscogsAccount::factory()->create();
    $syncRun = DiscogsSyncRun::factory()->for($account)->create([
        'kind' => 'initial_collection_import',
    ]);
    /** @var DiscogsGateway&MockInterface $discogs */
    $discogs = mock(DiscogsGateway::class);
    $discogs->shouldReceive('collectionPage')->once()->andThrow(
        new DiscogsRequestException(DiscogsFailure::Transient),
    );
    $job = new ImportDiscogsCollectionPage($syncRun->id, 1);

    expect(fn () => $job->handle(
        $discogs,
        app(DiscogsCollectionImporter::class),
        app(DiscogsReleaseRefresher::class),
    ))
        ->toThrow(DiscogsRequestException::class)
        ->and($syncRun->refresh()->last_completed_page)->toBe(0)
        ->and(CollectionItem::query()->count())->toBe(0);

    /** @var DiscogsGateway&MockInterface $discogs */
    $discogs = mock(DiscogsGateway::class);
    $discogs->shouldReceive('collectionPage')->once()->andReturn(
        collectionPage(releases: [collectionRelease(1, 42)]),
    );
    $job->handle(
        $discogs,
        app(DiscogsCollectionImporter::class),
        app(DiscogsReleaseRefresher::class),
    );

    expect($syncRun->refresh())
        ->status->toBe('completed')
        ->last_completed_page->toBe(1)
        ->items_created->toBe(1);
});

test('duplicate pages and duplicate rows do not inflate progress', function () {
    $account = DiscogsAccount::factory()->create();
    $syncRun = DiscogsSyncRun::factory()->for($account)->create([
        'kind' => 'initial_collection_import',
    ]);
    $payload = collectionPage(items: 1, releases: [
        collectionRelease(101, 42),
        collectionRelease(101, 42),
    ]);
    $importer = app(DiscogsCollectionImporter::class);

    $importer->importPage($syncRun, 1, $payload, now());
    $retryResult = $importer->importPage($syncRun->refresh(), 1, $payload, now());

    expect($syncRun->refresh())
        ->items_seen->toBe(1)
        ->items_created->toBe(1)
        ->items_updated->toBe(0)
        ->and(CollectionItem::query()->count())->toBe(1)
        ->and(DiscogsCollectionInstance::query()->count())->toBe(1)
        ->and($retryResult['release_ids_needing_refresh'])->toBe([42]);
});

test('existing copies are updated without replacing their collection items', function () {
    $account = DiscogsAccount::factory()->create();
    $release = Release::factory()->create(['discogs_id' => 42]);
    $collectionItem = CollectionItem::factory()->for($account->user)->for($release)->create();
    $instance = DiscogsCollectionInstance::factory()->for($account)->for($collectionItem)->create([
        'user_id' => $account->user_id,
        'discogs_instance_id' => 101,
        'discogs_folder_id' => 1,
    ]);
    $syncRun = DiscogsSyncRun::factory()->for($account)->create([
        'kind' => 'initial_collection_import',
    ]);

    app(DiscogsCollectionImporter::class)->importPage(
        $syncRun,
        1,
        collectionPage(releases: [collectionRelease(101, 42, 9)]),
        now(),
    );

    expect($syncRun->refresh())
        ->items_created->toBe(0)
        ->items_updated->toBe(1)
        ->and($instance->refresh())
        ->collection_item_id->toBe($collectionItem->id)
        ->discogs_folder_id->toBe(9)
        ->last_seen_sync_run_id->toBe($syncRun->id);
});
