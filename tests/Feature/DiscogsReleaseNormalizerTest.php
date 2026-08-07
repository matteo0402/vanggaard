<?php

use App\DiscogsReleaseNormalizer;
use App\Models\Artist;
use App\Models\CollectionItem;
use App\Models\Genre;
use App\Models\Label;
use App\Models\PersonalReleaseMetadata;
use App\Models\Release;
use App\Models\Style;
use App\Models\Track;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

test('a full Discogs release response is normalized into the external catalog', function () {
    $fetchedAt = CarbonImmutable::parse('2026-08-07 18:00:00');
    $payload = completeReleasePayload();

    $release = app(DiscogsReleaseNormalizer::class)->normalize($payload, $fetchedAt);

    expect($release->discogs_id)->toBe(249504)
        ->and($release->master_discogs_id)->toBe(8486)
        ->and($release->title)->toBe('King Tubby Meets Rockers Uptown')
        ->and($release->released_year)->toBe(1976)
        ->and($release->discogs_changed_at?->toIso8601String())->toBe('2025-05-27T12:34:56+00:00')
        ->and($release->fetched_at->equalTo($fetchedAt))->toBeTrue()
        ->and($release->source_url)->toBe('https://www.discogs.com/release/249504')
        ->and($release->source_hash)->toHaveLength(64)
        ->and($release->raw_payload)->toBe($payload)
        ->and($release->image_urls)->toBe([
            'https://i.discogs.com/primary.jpg',
            'https://i.discogs.com/secondary.jpg',
        ]);

    expect($release->artists()->count())->toBe(1)
        ->and($release->artists()->firstOrFail()->pivot->credited_name)->toBe('Augustus Pablo')
        ->and($release->labels()->count())->toBe(1)
        ->and($release->labels()->firstOrFail()->pivot->catalog_number)->toBe('none')
        ->and($release->credits()->firstOrFail()->role)->toBe('Producer')
        ->and($release->tracks()->firstOrFail()->title)->toBe('Keep On Dubbing')
        ->and($release->tracks()->firstOrFail()->type)->toBe('track')
        ->and($release->formats()->firstOrFail()->quantity)->toBe(1)
        ->and($release->formats()->firstOrFail()->descriptions)->toBe(['LP', 'Album'])
        ->and($release->videos()->firstOrFail()->embed)->toBeTrue();

    expect(Artist::where('discogs_id', 10348)->value('name'))->toBe('Augustus Pablo')
        ->and(Artist::where('discogs_id', 194)->value('name'))->toBe('King Tubby')
        ->and(Label::where('discogs_id', 895)->value('name'))->toBe('Clocktower Records')
        ->and(Genre::pluck('name')->all())->toBe(['Reggae'])
        ->and(Style::pluck('name')->all())->toBe(['Dub']);
});

test('an unchanged response refreshes source data without duplicating catalog children', function () {
    $normalizer = app(DiscogsReleaseNormalizer::class);
    $payload = completeReleasePayload();
    $release = $normalizer->normalize($payload, CarbonImmutable::parse('2026-08-07 18:00:00'));
    $sourceHash = $release->source_hash;
    $trackId = $release->tracks()->sole()->id;

    $release = $normalizer->normalize(
        array_reverse($payload, preserve_keys: true),
        CarbonImmutable::parse('2026-08-07 19:00:00'),
    );

    expect($release->source_hash)->toBe($sourceHash)
        ->and($release->fetched_at->toDateTimeString())->toBe('2026-08-07 19:00:00')
        ->and(Artist::where('discogs_id', 10348)->firstOrFail()->fetched_at->toDateTimeString())->toBe('2026-08-07 19:00:00')
        ->and(Label::where('discogs_id', 895)->firstOrFail()->fetched_at->toDateTimeString())->toBe('2026-08-07 19:00:00')
        ->and($release->artists()->count())->toBe(1)
        ->and($release->labels()->count())->toBe(1)
        ->and($release->credits()->count())->toBe(1)
        ->and($release->tracks()->count())->toBe(1)
        ->and($release->tracks()->sole()->id)->toBe($trackId)
        ->and($release->formats()->count())->toBe(1)
        ->and($release->genres()->count())->toBe(1)
        ->and($release->styles()->count())->toBe(1)
        ->and($release->videos()->count())->toBe(1);
});

test('a changed response retires removed source data without updating user-owned metadata', function () {
    $normalizer = app(DiscogsReleaseNormalizer::class);
    $payload = completeReleasePayload();
    $release = $normalizer->normalize($payload, CarbonImmutable::parse('2026-08-07 18:00:00'));
    $user = User::factory()->create();
    $collectionItem = CollectionItem::factory()->recycle($user)->create(['release_id' => $release->id]);
    $personalMetadata = PersonalReleaseMetadata::factory()->recycle($user)->create([
        'release_id' => $release->id,
        'personal_notes' => 'My original pressing.',
        'rating' => 5,
        'corrected_year' => 1975,
    ]);

    $payload['title'] = 'King Tubby Meets Rockers Uptown (Reissue)';
    $payload['artists'] = [];
    $payload['labels'] = [];
    $payload['extraartists'] = [];
    $payload['tracklist'][0]['title'] = 'Keep On Dubbing (Version)';
    $payload['formats'] = [];
    $payload['genres'] = [];
    $payload['styles'] = [];
    $payload['videos'] = [];

    $release = $normalizer->normalize($payload, CarbonImmutable::parse('2026-08-07 19:00:00'));

    expect($release->title)->toBe('King Tubby Meets Rockers Uptown (Reissue)')
        ->and($release->artists()->wherePivotNull('retired_at')->count())->toBe(0)
        ->and($release->labels()->wherePivotNull('retired_at')->count())->toBe(0)
        ->and($release->credits()->whereNull('retired_at')->count())->toBe(0)
        ->and($release->tracks()->count())->toBe(2)
        ->and($release->tracks()->whereNotNull('retired_at')->sole()->title)->toBe('Keep On Dubbing')
        ->and($release->tracks()->whereNull('retired_at')->sole()->title)->toBe('Keep On Dubbing (Version)')
        ->and($release->formats()->whereNull('retired_at')->count())->toBe(0)
        ->and($release->genres()->wherePivotNull('retired_at')->count())->toBe(0)
        ->and($release->styles()->wherePivotNull('retired_at')->count())->toBe(0)
        ->and($release->videos()->whereNull('retired_at')->count())->toBe(0);

    expect($collectionItem->fresh()->release_id)->toBe($release->id)
        ->and($personalMetadata->fresh()->personal_notes)->toBe('My original pressing.')
        ->and($personalMetadata->fresh()->rating)->toBe(5)
        ->and($personalMetadata->fresh()->corrected_year)->toBe(1975);
});

test('a partially missing response stores the release with empty optional relationships', function () {
    $payload = [
        'id' => 42,
        'title' => 'Minimal Release',
    ];

    $release = app(DiscogsReleaseNormalizer::class)->normalize(
        $payload,
        CarbonImmutable::parse('2026-08-07 20:00:00'),
    );

    expect($release->master_discogs_id)->toBeNull()
        ->and($release->released_year)->toBeNull()
        ->and($release->discogs_changed_at)->toBeNull()
        ->and($release->image_urls)->toBe([])
        ->and($release->raw_payload)->toBe($payload)
        ->and($release->artists()->count())->toBe(0)
        ->and($release->labels()->count())->toBe(0)
        ->and($release->tracks()->count())->toBe(0)
        ->and($release->videos()->count())->toBe(0);
});

test('a partially missing refresh preserves source fields and relationships that were not returned', function () {
    $normalizer = app(DiscogsReleaseNormalizer::class);
    $release = $normalizer->normalize(
        completeReleasePayload(),
        CarbonImmutable::parse('2026-08-07 18:00:00'),
    );

    $release = $normalizer->normalize([
        'id' => 249504,
        'title' => 'King Tubby Meets Rockers Uptown',
        'tracklist' => [],
    ], CarbonImmutable::parse('2026-08-07 19:00:00'));

    expect($release->released_year)->toBe(1976)
        ->and($release->notes)->toBe('A dub classic.')
        ->and($release->image_urls)->toHaveCount(2)
        ->and($release->fetched_at->toDateTimeString())->toBe('2026-08-07 18:00:00')
        ->and($release->artists()->wherePivotNull('retired_at')->count())->toBe(1)
        ->and($release->labels()->wherePivotNull('retired_at')->count())->toBe(1)
        ->and($release->tracks()->whereNull('retired_at')->count())->toBe(0)
        ->and($release->tracks()->whereNotNull('retired_at')->sole()->retired_at->toDateTimeString())->toBe('2026-08-07 19:00:00')
        ->and($release->videos()->whereNull('retired_at')->count())->toBe(1);
});

test('nested Discogs sub-tracks are flattened into playable tracks', function () {
    $payload = [
        'id' => 43,
        'title' => 'Medley Release',
        'tracklist' => [[
            'position' => 'A',
            'type_' => 'index',
            'title' => 'Dub Medley',
            'sub_tracks' => [
                ['position' => 'A.1', 'type_' => 'track', 'title' => 'First Dub'],
                ['position' => 'A.2', 'type_' => 'track', 'title' => 'Second Dub'],
            ],
        ]],
    ];

    $release = app(DiscogsReleaseNormalizer::class)->normalize(
        $payload,
        CarbonImmutable::parse('2026-08-07 20:00:00'),
    );

    expect($release->tracks()->orderBy('sequence')->pluck('title')->all())
        ->toBe(['First Dub', 'Second Dub']);
});

test('a malformed response is rejected without changing the catalog', function () {
    $payload = completeReleasePayload();
    $payload['genres'] = ['Reggae', 'Reggae'];

    expect(fn () => app(DiscogsReleaseNormalizer::class)->normalize(
        $payload,
        CarbonImmutable::parse('2026-08-07 20:00:00'),
    ))->toThrow(ValidationException::class);

    expect(Release::query()->count())->toBe(0)
        ->and(Artist::query()->count())->toBe(0)
        ->and(Genre::query()->count())->toBe(0);
});

test('normalization rolls back when a child cannot be persisted', function () {
    Track::creating(fn () => throw new RuntimeException('Track persistence failed.'));

    expect(fn () => app(DiscogsReleaseNormalizer::class)->normalize(
        completeReleasePayload(),
        CarbonImmutable::parse('2026-08-07 20:00:00'),
    ))->toThrow(RuntimeException::class, 'Track persistence failed.');

    expect(Release::query()->count())->toBe(0)
        ->and(Artist::query()->count())->toBe(0)
        ->and(Label::query()->count())->toBe(0);
});

/**
 * @return array{
 *     id: int,
 *     master_id: int,
 *     title: string,
 *     country: string,
 *     year: int,
 *     released: string,
 *     notes: string,
 *     data_quality: string,
 *     date_changed: string,
 *     uri: string,
 *     artists: array<int, array{id: int, name: string, anv: string, join: string, resource_url: string}>,
 *     labels: array<int, array{id: int, name: string, catno: string, resource_url: string}>,
 *     extraartists: array<int, array{id: int, name: string, anv: string, join: string, role: string, resource_url: string}>,
 *     tracklist: array<int, array{position: string, type_: string, title: string, duration: string}>,
 *     formats: array<int, array{name: string, qty: string, text: string, descriptions: array<int, string>}>,
 *     genres: array<int, string>,
 *     styles: array<int, string>,
 *     videos: array<int, array{uri: string, title: string, description: string, duration: int, embed: bool}>,
 *     images: array<int, array{uri: string}>
 * }
 */
function completeReleasePayload(): array
{
    return [
        'id' => 249504,
        'master_id' => 8486,
        'title' => 'King Tubby Meets Rockers Uptown',
        'country' => 'Jamaica',
        'year' => 1976,
        'released' => '1976',
        'notes' => 'A dub classic.',
        'data_quality' => 'Correct',
        'date_changed' => '2025-05-27T12:34:56-00:00',
        'uri' => 'https://www.discogs.com/release/249504-King-Tubby-Meets-Rockers-Uptown',
        'artists' => [[
            'id' => 10348,
            'name' => 'Augustus Pablo',
            'anv' => 'Augustus Pablo',
            'join' => '',
            'resource_url' => 'https://api.discogs.com/artists/10348',
        ]],
        'labels' => [[
            'id' => 895,
            'name' => 'Clocktower Records',
            'catno' => 'none',
            'resource_url' => 'https://api.discogs.com/labels/895',
        ]],
        'extraartists' => [[
            'id' => 194,
            'name' => 'King Tubby',
            'anv' => '',
            'join' => '',
            'role' => 'Producer',
            'resource_url' => 'https://api.discogs.com/artists/194',
        ]],
        'tracklist' => [[
            'position' => 'A1',
            'type_' => 'track',
            'title' => 'Keep On Dubbing',
            'duration' => '3:12',
        ]],
        'formats' => [[
            'name' => 'Vinyl',
            'qty' => '1',
            'text' => 'Black vinyl',
            'descriptions' => ['LP', 'Album'],
        ]],
        'genres' => ['Reggae'],
        'styles' => ['Dub'],
        'videos' => [[
            'uri' => 'https://www.youtube.com/watch?v=abc123',
            'title' => 'Keep On Dubbing',
            'description' => 'Audio',
            'duration' => 192,
            'embed' => true,
        ]],
        'images' => [
            ['uri' => 'https://i.discogs.com/primary.jpg'],
            ['uri' => 'https://i.discogs.com/secondary.jpg'],
        ],
    ];
}
