<?php

use App\CollectionBrowse;
use App\Models\Artist;
use App\Models\CollectionItem;
use App\Models\Label;
use App\Models\PersonalReleaseMetadata;
use App\Models\Release;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);

test('browse pages require authentication', function () {
    $this->get(route('browse'))->assertRedirectToRoute('login');
});

test('browse dimensions count active physical copies for only the current owner', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $release = Release::factory()->create(['released_year' => 1976]);
    $label = Label::factory()->create(['name' => 'Pressure Sounds']);
    $artist = Artist::factory()->create(['name' => 'Augustus Pablo']);

    $release->labels()->attach($label, ['position' => 0]);
    $release->artists()->attach($artist, ['position' => 0]);
    CollectionItem::factory()->count(2)->for($owner)->for($release)->create();
    CollectionItem::factory()->inactive()->for($owner)->for($release)->create();
    CollectionItem::factory()->for($otherUser)->for($release)->create();
    PersonalReleaseMetadata::factory()->for($owner)->for($release)->create([
        'corrected_year' => 1975,
    ]);
    Video::factory()->for($release)->create();

    $this->actingAs($owner)
        ->get(route('browse', 'labels'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Browse/Index')
            ->where('dimension', 'labels')
            ->where('entries.data', [[
                'value' => $label->id,
                'label' => 'Pressure Sounds',
                'count' => 2,
            ]]));

    $this->actingAs($owner)
        ->get(route('browse', 'artists'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.value', $artist->id)
            ->where('entries.data.0.label', 'Augustus Pablo')
            ->where('entries.data.0.count', 2));

    $this->actingAs($owner)
        ->get(route('browse', 'years'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.value', 1975)
            ->where('entries.data.0.count', 2));

    $this->actingAs($owner)
        ->get(route('browse', 'videos'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.value', 'with')
            ->where('entries.data.0.count', 2));
});

test('retired and stale source facts do not appear in browse dimensions', function () {
    $owner = User::factory()->create();
    $label = Label::factory()->create();
    $staleRelease = Release::factory()->create(['fetched_at' => now()->subHours(6)]);
    $retiredRelease = Release::factory()->create();

    $staleRelease->labels()->attach($label, ['position' => 0]);
    $retiredRelease->labels()->attach($label, ['position' => 0, 'retired_at' => now()]);
    CollectionItem::factory()->for($owner)->for($staleRelease)->create();
    CollectionItem::factory()->for($owner)->for($retiredRelease)->create();

    $this->actingAs($owner)
        ->get(route('browse', 'labels'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('entries.data', []));
});

test('personal year corrections remain browsable when source facts are stale', function () {
    $owner = User::factory()->create();
    $release = Release::factory()->create([
        'released_year' => 1976,
        'fetched_at' => now()->subHours(6),
    ]);

    CollectionItem::factory()->for($owner)->for($release)->create();
    PersonalReleaseMetadata::factory()->for($owner)->for($release)->create([
        'corrected_year' => 1975,
    ]);

    $this->actingAs($owner)
        ->get(route('browse', 'years'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.value', 1975)
            ->where('entries.data.0.count', 1));
});

test('recent dimensions group duplicate copies and order releases by activity', function () {
    $owner = User::factory()->create();
    $olderRelease = Release::factory()->create([
        'title' => 'Older update',
        'fetched_at' => now()->subHours(2),
    ]);
    $newerRelease = Release::factory()->create([
        'title' => 'Newer update',
        'fetched_at' => now()->subHour(),
    ]);

    CollectionItem::factory()->count(2)->for($owner)->for($olderRelease)->create([
        'created_at' => now()->subDays(3),
    ]);
    CollectionItem::factory()->for($owner)->for($newerRelease)->create([
        'created_at' => now()->subDay(),
    ]);

    $this->actingAs($owner)
        ->get(route('browse', 'recently-added'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.label', 'Newer update')
            ->where('entries.data.0.count', 1)
            ->where('entries.data.1.label', 'Older update')
            ->where('entries.data.1.count', 2));

    $this->actingAs($owner)
        ->get(route('browse', 'recently-updated'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.label', 'Newer update')
            ->where('entries.data.1.label', 'Older update'));

    $this->actingAs($owner)
        ->get(route('collection', ['filter' => 'recently-added', 'value' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('releases.data.0.id', $newerRelease->id)
            ->where('releases.data.1.id', $olderRelease->id));

    $this->actingAs($owner)
        ->get(route('collection', ['filter' => 'recently-updated', 'value' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('releases.data.0.id', $newerRelease->id)
            ->where('releases.data.1.id', $olderRelease->id));
});

test('recent dimensions include stale active inventory without exposing stale titles', function () {
    $owner = User::factory()->create();
    $release = Release::factory()->create([
        'title' => 'Hidden stale title',
        'fetched_at' => now()->subHours(6),
    ]);

    CollectionItem::factory()->for($owner)->for($release)->create();

    $this->actingAs($owner)
        ->get(route('browse', 'recently-added'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.value', $release->id)
            ->where('entries.data.0.label', 'Discogs details hidden')
            ->where('entries.data.0.count', 1));
});

test('browse filters open the matching catalog releases', function (string $filter, string $value) {
    $owner = User::factory()->create();
    $matchingRelease = Release::factory()->create(['released_year' => 1975]);
    $otherRelease = Release::factory()->create(['released_year' => 1980]);
    $label = Label::factory()->create();
    $artist = Artist::factory()->create();

    CollectionItem::factory()->for($owner)->for($matchingRelease)->create();
    CollectionItem::factory()->for($owner)->for($otherRelease)->create();
    $matchingRelease->labels()->attach($label, ['position' => 0]);
    $matchingRelease->artists()->attach($artist, ['position' => 0]);
    Video::factory()->for($matchingRelease)->create();

    $resolvedValue = match ($filter) {
        'label' => (string) $label->id,
        'artist' => (string) $artist->id,
        default => $value,
    };

    $this->actingAs($owner)
        ->get(route('collection', ['filter' => $filter, 'value' => $resolvedValue]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('releases.total', 1)
            ->where('releases.data.0.id', $matchingRelease->id)
            ->where('filters.filter', $filter)
            ->where('filters.value', $resolvedValue));
})->with([
    'label' => ['label', ''],
    'artist' => ['artist', ''],
    'year' => ['year', '1975'],
    'video' => ['video', 'with'],
]);

test('each browse dimension uses one aggregate query regardless of collection size', function () {
    $owner = User::factory()->create();
    CollectionItem::factory()->count(10)->for($owner)->create();

    $this->expectsDatabaseQueryCount(6);

    $browse = app(CollectionBrowse::class);

    foreach (array_keys(CollectionBrowse::DIMENSIONS) as $dimension) {
        $browse->for($owner, $dimension);
    }
});

test('unknown browse dimensions are not found', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)->get('/browse/unknown')->assertNotFound();
});
