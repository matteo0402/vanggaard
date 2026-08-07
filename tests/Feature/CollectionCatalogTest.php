<?php

use App\CollectionCatalog;
use App\Models\CollectionItem;
use App\Models\CollectionItemStorageAssignment;
use App\Models\DiscogsAccount;
use App\Models\DiscogsCollectionInstance;
use App\Models\PersonalReleaseMetadata;
use App\Models\Release;
use App\Models\StorageLocation;
use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);

test('collection pages require authentication', function () {
    $release = Release::factory()->create();

    $this->get(route('collection'))->assertRedirectToRoute('login');
    $this->get(route('collection.show', $release))->assertRedirectToRoute('login');
});

test('the catalog is paginated and scoped to the owners active physical copies', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $ownedRelease = Release::factory()->create(['title' => 'African Roots']);

    CollectionItem::factory()->count(2)->for($owner)->for($ownedRelease)->create();
    CollectionItem::factory()->inactive()->for($owner)->create();
    CollectionItem::factory()->for($otherUser)->create();

    Release::factory()
        ->count(24)
        ->create(['title' => 'Zion Collection Release'])
        ->each(fn (Release $release) => CollectionItem::factory()->for($owner)->for($release)->create());

    $this->actingAs($owner)
        ->get(route('collection'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Collection/Index')
            ->where('releases.total', 25)
            ->where('releases.per_page', 24)
            ->has('releases.data', 24)
            ->where('releases.data.0.discogs.title', 'African Roots')
            ->where('releases.data.0.physical_copies_count', 2)
            ->has('releases.data.0.collection_item_ids', 2)
            ->where('releases.last_page', 2));
});

test('catalog search includes fresh source facts and personal notes without exposing stale source facts', function () {
    $owner = User::factory()->create();
    $freshRelease = Release::factory()->create(['title' => 'Fresh Dubplate']);
    $staleRelease = Release::factory()->create([
        'title' => 'Stale Dubplate',
        'fetched_at' => now()->subHours(6),
    ]);
    $notedRelease = Release::factory()->create(['title' => 'Different title']);

    CollectionItem::factory()->for($owner)->for($freshRelease)->create();
    CollectionItem::factory()->for($owner)->for($staleRelease)->create();
    CollectionItem::factory()->for($owner)->for($notedRelease)->create();
    PersonalReleaseMetadata::factory()->for($owner)->for($notedRelease)->create([
        'personal_notes' => 'Dubplate selection',
    ]);

    $this->actingAs($owner)
        ->get(route('collection', ['q' => 'Dubplate']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', 'Dubplate')
            ->where('releases.total', 2)
            ->where('releases.data.0.discogs.title', 'Different title')
            ->where('releases.data.1.discogs.title', 'Fresh Dubplate'));
});

test('release details show effective and Discogs values, ordered tracks, and current copy locations', function () {
    $owner = User::factory()->create();
    $release = Release::factory()->create([
        'title' => 'King Tubby Meets Rockers Uptown',
        'released_year' => 1976,
        'source_url' => 'https://www.discogs.com/release/123',
        'refresh_status' => 'idle',
    ]);
    $firstCopy = CollectionItem::factory()->for($owner)->for($release)->create();
    CollectionItem::factory()->for($owner)->for($release)->create();
    PersonalReleaseMetadata::factory()->for($owner)->for($release)->create([
        'corrected_year' => 1975,
        'personal_notes' => 'Warm-up copy',
        'rating' => 5,
    ]);
    Track::factory()->for($release)->create(['sequence' => 2, 'position' => 'A2', 'title' => 'Second']);
    Track::factory()->for($release)->create(['sequence' => 1, 'position' => 'A1', 'title' => 'First']);
    Track::factory()->for($release)->create(['sequence' => 0, 'title' => 'Retired', 'retired_at' => now()]);

    $room = StorageLocation::factory()->for($owner)->create(['name' => 'Studio']);
    $shelf = StorageLocation::factory()->for($owner)->create([
        'parent_id' => $room->id,
        'kind' => 'shelf',
        'name' => 'Reggae shelf',
    ]);
    $box = StorageLocation::factory()->for($owner)->create([
        'parent_id' => $shelf->id,
        'kind' => 'box',
        'name' => 'Box A',
    ]);
    CollectionItemStorageAssignment::factory()->for($firstCopy)->create([
        'user_id' => $owner->id,
        'storage_location_id' => $room->id,
        'removed_at' => now()->subDay(),
    ]);
    CollectionItemStorageAssignment::factory()->for($firstCopy)->create([
        'user_id' => $owner->id,
        'storage_location_id' => $box->id,
    ]);

    $this->actingAs($owner)
        ->get(route('collection.show', $release))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Collection/Show')
            ->where('release.values.title.effective', 'King Tubby Meets Rockers Uptown')
            ->where('release.values.title.discogs', 'King Tubby Meets Rockers Uptown')
            ->where('release.values.year.effective', 1975)
            ->where('release.values.year.discogs', 1976)
            ->where('release.values.year.is_corrected', true)
            ->where('release.personal.notes', 'Warm-up copy')
            ->where('release.personal.rating', 5)
            ->where('release.discogs.source_url', 'https://www.discogs.com/release/123')
            ->has('release.physical_copies', 2)
            ->where('release.physical_copies.0.locations', ['Studio / Reggae shelf / Box A'])
            ->has('release.discogs.tracks', 2)
            ->where('release.discogs.tracks.0.title', 'First')
            ->where('release.discogs.tracks.1.title', 'Second'));
});

test('stale release details suppress Discogs content but preserve personal inventory', function () {
    $owner = User::factory()->create();
    $release = Release::factory()->create([
        'title' => 'Hidden title',
        'released_year' => 1974,
        'source_url' => 'https://www.discogs.com/release/456',
        'image_urls' => ['https://i.discogs.com/cover.jpg'],
        'fetched_at' => now()->subHours(6),
    ]);
    $copy = CollectionItem::factory()->for($owner)->for($release)->create();
    PersonalReleaseMetadata::factory()->for($owner)->for($release)->create([
        'corrected_year' => 1975,
        'personal_notes' => 'Keep this personal note',
    ]);
    Track::factory()->for($release)->create(['title' => 'Hidden track']);

    $this->actingAs($owner)
        ->get(route('collection.show', $release))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('release.discogs', null)
            ->where('release.values.title.effective', null)
            ->where('release.values.title.discogs', null)
            ->where('release.values.year.effective', 1975)
            ->where('release.values.year.discogs', null)
            ->where('release.personal.notes', 'Keep this personal note')
            ->where('release.physical_copies.0.id', $copy->id));
});

test('owners cannot view releases outside their active collection', function () {
    $owner = User::factory()->create();
    $otherUsersRelease = CollectionItem::factory()->create()->release;
    $inactiveRelease = CollectionItem::factory()->inactive()->for($owner)->create()->release;

    $this->actingAs($owner)
        ->get(route('collection.show', $otherUsersRelease))
        ->assertForbidden();
    $this->actingAs($owner)
        ->get(route('collection.show', $inactiveRelease))
        ->assertForbidden();
});

test('stale Discogs collection membership is not displayed', function () {
    $owner = User::factory()->create();
    $account = DiscogsAccount::factory()->for($owner)->create();
    $release = Release::factory()->create();
    $copy = CollectionItem::factory()->for($owner)->for($release)->create();
    DiscogsCollectionInstance::factory()->create([
        'discogs_account_id' => $account->id,
        'collection_item_id' => $copy->id,
        'user_id' => $owner->id,
        'fetched_at' => now()->subHours(6),
    ]);

    $this->actingAs($owner)
        ->get(route('collection'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('releases.total', 0));
    $this->actingAs($owner)
        ->get(route('collection.show', $release))
        ->assertForbidden();
});

test('fresh release details support missing optional metadata', function () {
    $owner = User::factory()->create();
    $release = Release::factory()->create([
        'country' => null,
        'released_year' => null,
        'released' => null,
        'image_urls' => [],
    ]);
    CollectionItem::factory()->for($owner)->for($release)->create();

    $this->actingAs($owner)
        ->get(route('collection.show', $release))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('release.values.year.effective', null)
            ->where('release.discogs.country', null)
            ->where('release.discogs.released', null)
            ->where('release.discogs.image_url', null)
            ->where('release.discogs.artists', [])
            ->where('release.discogs.labels', [])
            ->where('release.discogs.formats', [])
            ->where('release.discogs.tracks', [])
            ->where('release.personal.notes', null)
            ->where('release.physical_copies.0.locations', []));
});

test('Discogs attribution links remain canonical and ranking-permitting', function () {
    $attribution = file_get_contents(resource_path('js/components/DiscogsAttribution.vue'));
    $index = file_get_contents(resource_path('js/pages/Collection/Index.vue'));
    $show = file_get_contents(resource_path('js/pages/Collection/Show.vue'));

    expect($attribution)
        ->toContain('Data provided by Discogs.')
        ->toContain(':href="sourceUrl"')
        ->toContain('rel="noopener"')
        ->not->toContain('nofollow')
        ->and($index)->toContain('<DiscogsAttribution')
        ->and(substr_count($show, '<DiscogsAttribution'))->toBeGreaterThanOrEqual(2);
});

test('catalog serialization does not lazy load relationships', function () {
    $owner = User::factory()->create();
    $release = Release::factory()->create();
    CollectionItem::factory()->count(3)->for($owner)->for($release)->create();

    Model::preventLazyLoading();

    try {
        $catalog = app(CollectionCatalog::class);

        expect($catalog->for($owner)->items())->toHaveCount(1)
            ->and($catalog->release($owner, $release)['physical_copies'])->toHaveCount(3);
    } finally {
        Model::preventLazyLoading(false);
    }
});
