<?php

use App\DiscogsCollectionImporter;
use App\DiscogsFailure;
use App\DiscogsGateway;
use App\DiscogsRequestException;
use App\Jobs\FinalizeDiscogsCollectionReconciliation;
use App\Jobs\ImportDiscogsCollectionPage;
use App\Models\CollectionItem;
use App\Models\DiscogsAccount;
use App\Models\DiscogsCollectionInstance;
use App\Models\DiscogsSyncRun;
use App\Models\Release;
use App\Models\Tag;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

uses(LazilyRefreshDatabase::class);

function reconciliationPage(int $page = 1, int $pages = 1, int $items = 1, array $releases = []): array
{
    return [
        'pagination' => compact('page', 'pages', 'items'),
        'releases' => $releases,
    ];
}

function reconciliationRelease(int $instanceId, int $releaseId): array
{
    return [
        'instance_id' => $instanceId,
        'folder_id' => 0,
        'basic_information' => [
            'id' => $releaseId,
            'title' => "Release {$releaseId}",
        ],
    ];
}

test('starting reconciliation queues a resumable full collection run', function () {
    Queue::fake();
    $account = DiscogsAccount::factory()->create();

    $syncRun = app(DiscogsCollectionImporter::class)->startReconciliation($account);

    expect($syncRun)
        ->kind->toBe('collection_reconciliation')
        ->status->toBe('pending')
        ->is_full_reconciliation->toBeTrue();
    Queue::assertPushed(
        ImportDiscogsCollectionPage::class,
        fn (ImportDiscogsCollectionPage $job): bool => $job->syncRunId === $syncRun->id && $job->page === 1,
    );
});

test('the last manifest page queues finalization without completing the run', function () {
    Queue::fake();
    $account = DiscogsAccount::factory()->create();
    $syncRun = DiscogsSyncRun::factory()->for($account)->create([
        'kind' => 'collection_reconciliation',
        'is_full_reconciliation' => true,
    ]);
    /** @var DiscogsGateway&MockInterface $discogs */
    $discogs = mock(DiscogsGateway::class);
    $discogs->shouldReceive('collectionPage')->once()->andReturn(
        reconciliationPage(releases: [reconciliationRelease(101, 42)]),
    );

    (new ImportDiscogsCollectionPage($syncRun->id, 1))->handle(
        $discogs,
        app(DiscogsCollectionImporter::class),
    );

    expect($syncRun->refresh())
        ->status->toBe('running')
        ->completed_at->toBeNull()
        ->last_completed_page->toBe(1);
    Queue::assertPushed(
        FinalizeDiscogsCollectionReconciliation::class,
        fn (FinalizeDiscogsCollectionReconciliation $job): bool => $job->syncRunId === $syncRun->id,
    );
});

test('a missing instance is retired only after Discogs confirms its removal', function () {
    $account = DiscogsAccount::factory()->create();
    $release = Release::factory()->create(['discogs_id' => 42]);
    $collectionItem = CollectionItem::factory()->for($account->user)->for($release)->create();
    $tag = Tag::factory()->for($account->user)->create();
    $collectionItem->tags()->attach($tag);
    $instance = DiscogsCollectionInstance::factory()->for($account)->for($collectionItem)->create([
        'user_id' => $account->user_id,
        'discogs_instance_id' => 101,
        'last_seen_sync_run_id' => null,
    ]);
    $syncRun = DiscogsSyncRun::factory()->for($account)->create([
        'kind' => 'collection_reconciliation',
        'status' => 'running',
        'is_full_reconciliation' => true,
        'last_completed_page' => 1,
        'total_pages' => 1,
    ]);
    /** @var DiscogsGateway&MockInterface $discogs */
    $discogs = mock(DiscogsGateway::class);
    $discogs->shouldReceive('releaseInstance')
        ->once()
        ->withArgs(fn (DiscogsAccount $candidate, int $releaseId, int $instanceId): bool => $candidate->is($account)
            && $releaseId === 42
            && $instanceId === 101)
        ->andThrow(new DiscogsRequestException(DiscogsFailure::NotFound, 404));

    (new FinalizeDiscogsCollectionReconciliation($syncRun->id))->handle(
        $discogs,
        app(DiscogsCollectionImporter::class),
    );

    expect($syncRun->refresh())
        ->status->toBe('completed')
        ->items_removed->toBe(1)
        ->completed_at->not->toBeNull()
        ->and($collectionItem->refresh())
        ->is_active->toBeFalse()
        ->and($collectionItem->tags()->whereKey($tag)->exists())->toBeTrue()
        ->and(DiscogsCollectionInstance::query()->whereKey($instance)->exists())->toBeFalse();
});

test('an omitted instance that still exists remains active', function () {
    $account = DiscogsAccount::factory()->create();
    $release = Release::factory()->create(['discogs_id' => 42]);
    $collectionItem = CollectionItem::factory()->for($account->user)->for($release)->create();
    $instance = DiscogsCollectionInstance::factory()->for($account)->for($collectionItem)->create([
        'user_id' => $account->user_id,
        'discogs_instance_id' => 101,
        'last_seen_sync_run_id' => null,
    ]);
    $syncRun = DiscogsSyncRun::factory()->for($account)->create([
        'kind' => 'collection_reconciliation',
        'status' => 'running',
        'is_full_reconciliation' => true,
        'last_completed_page' => 1,
        'total_pages' => 1,
    ]);
    /** @var DiscogsGateway&MockInterface $discogs */
    $discogs = mock(DiscogsGateway::class);
    $discogs->shouldReceive('releaseInstance')
        ->once()
        ->withArgs(fn (DiscogsAccount $candidate, int $releaseId, int $instanceId): bool => $candidate->is($account)
            && $releaseId === 42
            && $instanceId === 101)
        ->andReturn(['id' => 42]);

    (new FinalizeDiscogsCollectionReconciliation($syncRun->id))->handle(
        $discogs,
        app(DiscogsCollectionImporter::class),
    );

    expect($syncRun->refresh())
        ->status->toBe('completed')
        ->items_removed->toBe(0)
        ->and($collectionItem->refresh()->is_active)->toBeTrue()
        ->and($instance->refresh()->last_seen_sync_run_id)->toBe($syncRun->id);
});

test('a confirmation failure leaves every candidate active', function () {
    $account = DiscogsAccount::factory()->create();
    $firstRelease = Release::factory()->create(['discogs_id' => 42]);
    $secondRelease = Release::factory()->create(['discogs_id' => 43]);
    $firstItem = CollectionItem::factory()->for($account->user)->for($firstRelease)->create();
    $secondItem = CollectionItem::factory()->for($account->user)->for($secondRelease)->create();
    DiscogsCollectionInstance::factory()->for($account)->for($firstItem)->create([
        'user_id' => $account->user_id,
        'discogs_instance_id' => 101,
        'last_seen_sync_run_id' => null,
    ]);
    DiscogsCollectionInstance::factory()->for($account)->for($secondItem)->create([
        'user_id' => $account->user_id,
        'discogs_instance_id' => 102,
        'last_seen_sync_run_id' => null,
    ]);
    $syncRun = DiscogsSyncRun::factory()->for($account)->create([
        'kind' => 'collection_reconciliation',
        'status' => 'running',
        'is_full_reconciliation' => true,
        'last_completed_page' => 1,
        'total_pages' => 1,
    ]);
    /** @var DiscogsGateway&MockInterface $discogs */
    $discogs = mock(DiscogsGateway::class);
    $discogs->shouldReceive('releaseInstance')
        ->once()
        ->withArgs(fn (DiscogsAccount $candidate, int $releaseId, int $instanceId): bool => $candidate->is($account)
            && $releaseId === 42
            && $instanceId === 101)
        ->andThrow(new DiscogsRequestException(DiscogsFailure::NotFound, 404));
    $discogs->shouldReceive('releaseInstance')
        ->once()
        ->withArgs(fn (DiscogsAccount $candidate, int $releaseId, int $instanceId): bool => $candidate->is($account)
            && $releaseId === 43
            && $instanceId === 102)
        ->andThrow(new DiscogsRequestException(DiscogsFailure::Transient));

    expect(fn () => (new FinalizeDiscogsCollectionReconciliation($syncRun->id))->handle(
        $discogs,
        app(DiscogsCollectionImporter::class),
    ))->toThrow(DiscogsRequestException::class)
        ->and(CollectionItem::query()->where('is_active', true)->count())->toBe(2)
        ->and(DiscogsCollectionInstance::query()->count())->toBe(2)
        ->and($syncRun->refresh()->status)->toBe('running');

    /** @var DiscogsGateway&MockInterface $discogs */
    $discogs = mock(DiscogsGateway::class);
    $discogs->shouldReceive('releaseInstance')
        ->never()
        ->withArgs(fn (DiscogsAccount $candidate, int $releaseId, int $instanceId): bool => $candidate->is($account)
            && $releaseId === 42
            && $instanceId === 101);
    $discogs->shouldReceive('releaseInstance')
        ->once()
        ->withArgs(fn (DiscogsAccount $candidate, int $releaseId, int $instanceId): bool => $candidate->is($account)
            && $releaseId === 43
            && $instanceId === 102)
        ->andReturn(['id' => 43]);

    (new FinalizeDiscogsCollectionReconciliation($syncRun->id))->handle(
        $discogs,
        app(DiscogsCollectionImporter::class),
    );

    expect($syncRun->refresh())
        ->status->toBe('completed')
        ->items_removed->toBe(1)
        ->and($firstItem->refresh()->is_active)->toBeFalse()
        ->and($secondItem->refresh()->is_active)->toBeTrue();
});

test('partial reconciliation runs cannot remove collection items', function () {
    $account = DiscogsAccount::factory()->create();
    $collectionItem = CollectionItem::factory()->for($account->user)->create();
    DiscogsCollectionInstance::factory()->for($account)->for($collectionItem)->create([
        'user_id' => $account->user_id,
        'last_seen_sync_run_id' => null,
    ]);
    $syncRun = DiscogsSyncRun::factory()->for($account)->create([
        'kind' => 'collection_reconciliation',
        'status' => 'failed',
        'is_full_reconciliation' => true,
        'last_completed_page' => 1,
        'total_pages' => 2,
    ]);
    /** @var DiscogsGateway&MockInterface $discogs */
    $discogs = mock(DiscogsGateway::class);
    $discogs->shouldNotReceive('releaseInstance');

    expect(fn () => app(DiscogsCollectionImporter::class)->finalizeReconciliation($syncRun, $discogs, now()))
        ->toThrow(LogicException::class)
        ->and($collectionItem->refresh()->is_active)->toBeTrue()
        ->and(DiscogsCollectionInstance::query()->count())->toBe(1);
});

test('a returning instance restores the retained physical copy and its metadata', function () {
    $account = DiscogsAccount::factory()->create();
    $release = Release::factory()->create(['discogs_id' => 42]);
    $collectionItem = CollectionItem::factory()->inactive()->for($account->user)->for($release)->create();
    $tag = Tag::factory()->for($account->user)->create();
    $collectionItem->tags()->attach($tag);
    $syncRun = DiscogsSyncRun::factory()->for($account)->create(['kind' => 'initial_collection_import']);

    app(DiscogsCollectionImporter::class)->importPage(
        $syncRun,
        1,
        reconciliationPage(releases: [reconciliationRelease(999, 42)]),
        now(),
    );

    expect($syncRun->refresh())
        ->items_created->toBe(0)
        ->items_updated->toBe(1)
        ->and($collectionItem->refresh())
        ->is_active->toBeTrue()
        ->and($collectionItem->tags()->whereKey($tag)->exists())->toBeTrue()
        ->and(CollectionItem::query()->count())->toBe(1)
        ->and(DiscogsCollectionInstance::query()->firstOrFail()->collection_item_id)->toBe($collectionItem->id);
});

test('duplicate copies remain distinguishable when they return', function () {
    $account = DiscogsAccount::factory()->create();
    $release = Release::factory()->create(['discogs_id' => 42]);
    $items = CollectionItem::factory()->inactive()->count(2)->for($account->user)->for($release)->create();
    $syncRun = DiscogsSyncRun::factory()->for($account)->create(['kind' => 'initial_collection_import']);

    app(DiscogsCollectionImporter::class)->importPage(
        $syncRun,
        1,
        reconciliationPage(items: 2, releases: [
            reconciliationRelease(101, 42),
            reconciliationRelease(102, 42),
        ]),
        now(),
    );

    expect(CollectionItem::query()->count())->toBe(2)
        ->and(CollectionItem::query()->where('is_active', true)->count())->toBe(2)
        ->and(DiscogsCollectionInstance::query()->pluck('collection_item_id')->sort()->values()->all())
        ->toBe($items->modelKeys());
});

test('instances repeated across manifest pages do not inflate progress', function () {
    $account = DiscogsAccount::factory()->create();
    $syncRun = DiscogsSyncRun::factory()->for($account)->create([
        'kind' => 'collection_reconciliation',
        'is_full_reconciliation' => true,
    ]);
    $importer = app(DiscogsCollectionImporter::class);

    $importer->importPage(
        $syncRun,
        1,
        reconciliationPage(page: 1, pages: 2, releases: [reconciliationRelease(101, 42)]),
        now(),
    );
    $result = $importer->importPage(
        $syncRun->refresh(),
        2,
        reconciliationPage(page: 2, pages: 2, releases: [reconciliationRelease(101, 42)]),
        now(),
    );

    expect($syncRun->refresh())
        ->items_seen->toBe(1)
        ->items_created->toBe(1)
        ->items_updated->toBe(0)
        ->and($result['should_finalize'])->toBeTrue();
});

test('large candidate sets continue in bounded confirmation jobs', function () {
    Queue::fake();
    $account = DiscogsAccount::factory()->create();
    $release = Release::factory()->create(['discogs_id' => 42]);
    $items = CollectionItem::factory()->count(26)->for($account->user)->for($release)->create();

    foreach ($items as $index => $item) {
        DiscogsCollectionInstance::factory()->for($account)->for($item)->create([
            'user_id' => $account->user_id,
            'discogs_instance_id' => $index + 1,
            'last_seen_sync_run_id' => null,
        ]);
    }

    $syncRun = DiscogsSyncRun::factory()->for($account)->create([
        'kind' => 'collection_reconciliation',
        'status' => 'running',
        'is_full_reconciliation' => true,
        'last_completed_page' => 1,
        'total_pages' => 1,
    ]);
    /** @var DiscogsGateway&MockInterface $discogs */
    $discogs = mock(DiscogsGateway::class);
    $discogs->shouldReceive('releaseInstance')->times(25)->andReturn(['id' => 42]);

    (new FinalizeDiscogsCollectionReconciliation($syncRun->id))->handle(
        $discogs,
        app(DiscogsCollectionImporter::class),
    );

    expect(DiscogsCollectionInstance::query()->where('last_seen_sync_run_id', $syncRun->id)->count())->toBe(25)
        ->and(DiscogsCollectionInstance::query()->whereNull('last_seen_sync_run_id')->count())->toBe(1)
        ->and($syncRun->refresh()->status)->toBe('running');
    Queue::assertPushed(
        FinalizeDiscogsCollectionReconciliation::class,
        fn (FinalizeDiscogsCollectionReconciliation $job): bool => $job->syncRunId === $syncRun->id,
    );
});
