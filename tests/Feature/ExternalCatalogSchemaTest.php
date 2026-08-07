<?php

use App\Models\Artist;
use App\Models\ArtistRelease;
use App\Models\Credit;
use App\Models\Format;
use App\Models\Genre;
use App\Models\Label;
use App\Models\Release;
use App\Models\Style;
use App\Models\Track;
use App\Models\Video;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);

test('external catalog factories create isolated records', function () {
    $models = [
        Release::factory()->create(),
        Artist::factory()->create(),
        Label::factory()->create(),
        Credit::factory()->create(),
        Track::factory()->create(),
        Format::factory()->create(),
        Genre::factory()->create(),
        Style::factory()->create(),
        Video::factory()->create(),
    ];

    foreach ($models as $model) {
        $this->assertModelExists($model);
    }
});

test('Discogs identifiers are globally unique', function (string $modelClass) {
    $discogsId = 12345;

    $modelClass::factory()->create(['discogs_id' => $discogsId]);

    expect(fn () => $modelClass::factory()->create(['discogs_id' => $discogsId]))
        ->toThrow(QueryException::class);
})->with([
    'release' => Release::class,
    'artist' => Artist::class,
    'label' => Label::class,
]);

test('catalog children require valid parents', function () {
    expect(fn () => Track::factory()->create(['release_id' => PHP_INT_MAX]))
        ->toThrow(QueryException::class);
});

test('catalog children can be retired without deleting historical references', function () {
    $track = Track::factory()->create(['retired_at' => now()]);
    $release = $track->release;

    expect($track->retired_at)->not->toBeNull()
        ->and($track->release->is($release))->toBeTrue();

    expect(fn () => $release->delete())->toThrow(QueryException::class);

    $this->assertModelExists($track);
    $this->assertModelExists($release);
});

test('retired children preserve history when their position is replaced', function () {
    $release = Release::factory()->create();
    $retiredTrack = Track::factory()->create([
        'release_id' => $release->id,
        'sequence' => 0,
        'retired_at' => now(),
    ]);
    $replacementTrack = Track::factory()->create([
        'release_id' => $release->id,
        'sequence' => 0,
    ]);

    $this->assertModelExists($retiredTrack);
    $this->assertModelExists($replacementTrack);
    expect($release->tracks()->count())->toBe(2);
});

test('retired relationship dates use incrementing custom pivot models', function () {
    $release = Release::factory()->create();
    $artist = Artist::factory()->create();

    $release->artists()->attach($artist, [
        'position' => 0,
        'retired_at' => now(),
    ]);

    $pivot = $release->artists()->firstOrFail()->pivot;

    expect($pivot)->toBeInstanceOf(ArtistRelease::class)
        ->and($pivot->getKey())->toBeInt()
        ->and($pivot->retired_at)->toBeInstanceOf(CarbonInterface::class);
});

test('external tables contain no user-owned metadata', function () {
    $externalTables = [
        'artists',
        'labels',
        'releases',
        'genres',
        'styles',
        'artist_release',
        'label_release',
        'credits',
        'tracks',
        'formats',
        'genre_release',
        'release_style',
        'videos',
    ];
    $userOwnedColumns = [
        'user_id',
        'personal_notes',
        'rating',
        'tag_id',
        'storage_location_id',
        'riddim_id',
        'playlist_id',
        'dj_history_id',
    ];

    foreach ($externalTables as $table) {
        expect(collect(Schema::getColumnListing($table))->intersect($userOwnedColumns))
            ->toBeEmpty("{$table} contains user-owned metadata");
    }
});

test('stored Discogs resources include freshness and source metadata', function () {
    foreach (['artists', 'labels', 'releases'] as $table) {
        expect(Schema::hasColumns($table, ['source_url', 'fetched_at']))->toBeTrue();
    }
});
