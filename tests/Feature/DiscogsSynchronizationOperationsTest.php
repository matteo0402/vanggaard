<?php

use App\DiscogsCollectionImporter;
use App\DiscogsFailure;
use App\DiscogsGateway;
use App\DiscogsReleaseNormalizer;
use App\DiscogsReleaseRefresher;
use App\DiscogsRequestException;
use App\Jobs\ImportDiscogsCollectionPage;
use App\Jobs\QueueStaleDiscogsReleaseRefreshes;
use App\Jobs\RefreshDiscogsRelease;
use App\Jobs\StartDiscogsCollectionReconciliations;
use App\Models\CollectionItem;
use App\Models\DiscogsAccount;
use App\Models\DiscogsSyncRun;
use App\Models\Release;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schedule;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Date::setTestNow('2026-08-07 21:00:00');
});

test('Discogs operations are scheduled with distributed overlap protection', function () {
    $events = collect(Schedule::events());
    $reconciliation = $events->first(fn (Event $event): bool => $event->description === 'discogs:reconcile-collections');
    $staleRefresh = $events->first(fn (Event $event): bool => $event->description === 'discogs:refresh-stale-releases');

    expect($reconciliation)->not->toBeNull()
        ->and($reconciliation->withoutOverlapping)->toBeTrue()
        ->and($reconciliation->onOneServer)->toBeTrue()
        ->and($staleRefresh)->not->toBeNull()
        ->and($staleRefresh->withoutOverlapping)->toBeTrue()
        ->and($staleRefresh->onOneServer)->toBeTrue();
});

test('scheduled reconciliation starts one resumable run per Discogs account', function () {
    Queue::fake();
    DiscogsAccount::factory()->count(2)->create();

    (new StartDiscogsCollectionReconciliations)->handle(app(DiscogsCollectionImporter::class));

    expect(DiscogsSyncRun::query()->where('kind', 'collection_reconciliation')->count())->toBe(2);
    Queue::assertPushedTimes(ImportDiscogsCollectionPage::class, 2);
});

test('stale release rotation is oldest first and bounded by the configured budget', function () {
    Queue::fake();
    config([
        'services.discogs.stale_after_hours' => 4,
        'services.discogs.stale_refresh_budget' => 2,
        'services.discogs.refresh_failure_cooldown_minutes' => 60,
    ]);
    $account = DiscogsAccount::factory()->create();
    $oldest = Release::factory()->create([
        'fetched_at' => now()->subDays(3),
        'raw_payload' => ['id' => 1],
    ]);
    $next = Release::factory()->create([
        'fetched_at' => now()->subDays(2),
        'raw_payload' => ['id' => 2],
    ]);
    $outsideBudget = Release::factory()->create([
        'fetched_at' => now()->subDay(),
        'raw_payload' => ['id' => 3],
    ]);
    $fresh = Release::factory()->create([
        'fetched_at' => now()->subHour(),
        'raw_payload' => ['id' => 4],
    ]);
    $inactive = Release::factory()->create([
        'fetched_at' => now()->subWeek(),
        'raw_payload' => ['id' => 5],
    ]);

    foreach ([$oldest, $next, $outsideBudget, $fresh] as $release) {
        CollectionItem::factory()->for($account->user)->for($release)->create();
    }
    CollectionItem::factory()->for($account->user)->for($inactive)->inactive()->create();

    $queued = app(DiscogsReleaseRefresher::class)->queueStale();

    expect($queued)->toBe(2)
        ->and($oldest->refresh()->refresh_status)->toBe('queued')
        ->and($next->refresh()->refresh_status)->toBe('queued')
        ->and($outsideBudget->refresh()->refresh_status)->toBe('idle')
        ->and($fresh->refresh()->refresh_status)->toBe('idle')
        ->and($inactive->refresh()->refresh_status)->toBe('idle');
    Queue::assertPushed(
        RefreshDiscogsRelease::class,
        fn (RefreshDiscogsRelease $job): bool => $job->releaseId === $oldest->discogs_id,
    );
    Queue::assertPushed(
        RefreshDiscogsRelease::class,
        fn (RefreshDiscogsRelease $job): bool => $job->releaseId === $next->discogs_id,
    );
    Queue::assertNotPushed(
        RefreshDiscogsRelease::class,
        fn (RefreshDiscogsRelease $job): bool => $job->releaseId === $outsideBudget->discogs_id,
    );
});

test('recently exhausted release refreshes do not monopolize stale rotation', function () {
    Queue::fake();
    config([
        'services.discogs.stale_refresh_budget' => 1,
        'services.discogs.refresh_failure_cooldown_minutes' => 60,
    ]);
    $account = DiscogsAccount::factory()->create();
    $failed = Release::factory()->create([
        'fetched_at' => now()->subWeek(),
        'raw_payload' => ['id' => 1],
        'refresh_status' => 'failed',
        'refresh_failed_at' => now()->subMinutes(10),
    ]);
    $eligible = Release::factory()->create([
        'fetched_at' => now()->subDay(),
        'raw_payload' => ['id' => 2],
    ]);
    CollectionItem::factory()->for($account->user)->for($failed)->create();
    CollectionItem::factory()->for($account->user)->for($eligible)->create();

    app(DiscogsReleaseRefresher::class)->queueStale();

    Queue::assertPushedTimes(RefreshDiscogsRelease::class, 1);
    Queue::assertPushed(
        RefreshDiscogsRelease::class,
        fn (RefreshDiscogsRelease $job): bool => $job->releaseId === $eligible->discogs_id,
    );
});

test('an owner can queue an immediate unique high priority release refresh', function () {
    Queue::fake();
    $account = DiscogsAccount::factory()->create();
    $release = Release::factory()->create();
    CollectionItem::factory()->for($account->user)->for($release)->create();

    $this->actingAs($account->user)
        ->post(route('releases.refresh', $release))
        ->assertRedirect();

    expect($release->refresh()->refresh_status)->toBe('queued');
    Queue::assertPushed(
        RefreshDiscogsRelease::class,
        fn (RefreshDiscogsRelease $job): bool => $job->releaseId === $release->discogs_id
            && $job->queue === 'high',
    );
});

test('a queue outage does not strand a release in queued state', function () {
    $account = DiscogsAccount::factory()->create();
    $release = Release::factory()->create();
    /** @var Dispatcher&MockInterface $dispatcher */
    $dispatcher = mock(Dispatcher::class);
    $dispatcher->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable.'));
    app()->instance(Dispatcher::class, $dispatcher);

    expect(fn () => app(DiscogsReleaseRefresher::class)->queue($account, $release))
        ->toThrow(RuntimeException::class, 'Queue unavailable.')
        ->and($release->refresh()->refresh_status)->toBe('idle');
});

test('a user cannot refresh a release outside their active collection', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $release = Release::factory()->create();
    CollectionItem::factory()->for($owner)->for($release)->create();

    $this->actingAs($otherUser)
        ->post(route('releases.refresh', $release))
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('release refresh state is cleared after success and sanitized after retry exhaustion', function () {
    $account = DiscogsAccount::factory()->create();
    $release = Release::factory()->create(['refresh_status' => 'queued']);
    /** @var DiscogsGateway&MockInterface $discogs */
    $discogs = mock(DiscogsGateway::class);
    $discogs->shouldReceive('release')->once()->andReturn(['id' => $release->discogs_id]);
    /** @var DiscogsReleaseNormalizer&MockInterface $normalizer */
    $normalizer = mock(DiscogsReleaseNormalizer::class);
    $normalizer->shouldReceive('normalize')->once();
    $job = new RefreshDiscogsRelease($account->id, $release->discogs_id);

    $job->handle($discogs, $normalizer);

    expect($release->refresh())
        ->refresh_status->toBe('idle')
        ->refresh_error->toBeNull()
        ->refresh_failed_at->toBeNull()
        ->refresh_attempted_at->not->toBeNull();

    $job->failed(new RuntimeException('database-password=secret'));

    expect($release->refresh())
        ->refresh_status->toBe('failed')
        ->refresh_error->toBe('Release refresh failed. It will be retried later.')
        ->refresh_error->not->toContain('secret')
        ->refresh_failed_at->not->toBeNull();

    $job->failed(new DiscogsRequestException(DiscogsFailure::Authentication));

    expect($release->refresh()->refresh_error)->toBe('Discogs rejected the configured credentials.');
});

test('the home page exposes current progress last success and sanitized failures', function () {
    $account = DiscogsAccount::factory()->create();
    DiscogsSyncRun::factory()->for($account)->create([
        'status' => 'completed',
        'items_seen' => 120,
        'total_items' => 120,
        'completed_at' => now()->subDay(),
    ]);
    DiscogsSyncRun::factory()->for($account)->create([
        'status' => 'running',
        'items_seen' => 45,
        'total_items' => 120,
        'last_completed_page' => 2,
        'total_pages' => 6,
    ]);
    $failedRelease = Release::factory()->create([
        'refresh_status' => 'failed',
        'refresh_error' => 'Discogs is temporarily unavailable.',
        'refresh_failed_at' => now(),
    ]);
    CollectionItem::factory()->for($account->user)->for($failedRelease)->create();

    $this->actingAs($account->user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('synchronization.current.status', 'running')
            ->where('synchronization.current.items_seen', 45)
            ->where('synchronization.current.total_items', 120)
            ->where('synchronization.last_success.items_seen', 120)
            ->where('synchronization.release_refreshes.failed', 1)
            ->where('refreshable_releases.data.0.id', $failedRelease->id)
            ->where(
                'synchronization.release_refreshes.latest_error',
                'Discogs is temporarily unavailable.',
            ));
});

test('manual refresh pages include every owned release while suppressing stale Discogs content', function () {
    $account = DiscogsAccount::factory()->create();
    $staleRelease = Release::factory()->create([
        'title' => 'Must not be displayed',
        'fetched_at' => now()->subHours(6),
    ]);
    $staleItem = CollectionItem::factory()->for($account->user)->for($staleRelease)->create();
    $freshReleases = Release::factory()->count(5)->create(['fetched_at' => now()->subHour()]);

    foreach ($freshReleases as $release) {
        CollectionItem::factory()->for($account->user)->for($release)->create();
    }

    $this->actingAs($account->user)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('refreshable_releases.current_page', 1)
            ->where('refreshable_releases.last_page', 2)
            ->where('refreshable_releases.data.0.collection_item_id', $staleItem->id)
            ->where('refreshable_releases.data.0.is_fresh', false)
            ->where('refreshable_releases.data.0.title', null)
            ->where('refreshable_releases.data.0.discogs_id', null)
            ->where('refreshable_releases.data.0.source_url', null));

    $this->actingAs($account->user)
        ->get(route('home', ['refresh_page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('refreshable_releases.current_page', 2)
            ->has('refreshable_releases.data', 1)
            ->where('refreshable_releases.data.0.is_fresh', true)
            ->where('refreshable_releases.data.0.source_url', $freshReleases->last()->source_url));
});

test('collection retry exhaustion stores a safe failure message', function () {
    $syncRun = DiscogsSyncRun::factory()->create();

    (new ImportDiscogsCollectionPage($syncRun->id, 1))
        ->failed(new RuntimeException('database-password=secret'));

    expect($syncRun->refresh())
        ->status->toBe('failed')
        ->error_message->toBe('Collection synchronization failed. It will resume automatically.');
});

test('the stale refresh scheduling job delegates to the bounded refresher', function () {
    /** @var DiscogsReleaseRefresher&MockInterface $refresher */
    $refresher = mock(DiscogsReleaseRefresher::class);
    $refresher->shouldReceive('queueStale')->once()->andReturn(3);

    (new QueueStaleDiscogsReleaseRefreshes)->handle($refresher);
});
