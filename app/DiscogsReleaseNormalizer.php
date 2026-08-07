<?php

namespace App;

use App\Models\Artist;
use App\Models\ArtistRelease;
use App\Models\Credit;
use App\Models\Format;
use App\Models\Genre;
use App\Models\GenreRelease;
use App\Models\Label;
use App\Models\LabelRelease;
use App\Models\Release;
use App\Models\ReleaseStyle;
use App\Models\Style;
use App\Models\Track;
use App\Models\Video;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @phpstan-type DiscogsPerson array{id: int, name: string, anv?: string|null, join?: string|null, role?: string, resource_url?: string}
 * @phpstan-type DiscogsCredit array{id: int, name: string, role: string, anv?: string|null, join?: string|null, resource_url?: string}
 * @phpstan-type DiscogsLabel array{id: int, name: string, catno?: string|null, resource_url?: string}
 * @phpstan-type DiscogsSubTrack array{position?: string|null, type_?: string|null, title: string, duration?: string|null}
 * @phpstan-type DiscogsTrack array{position?: string|null, type_?: string|null, title: string, duration?: string|null, sub_tracks?: array<int, DiscogsSubTrack>}
 * @phpstan-type DiscogsFormat array{name: string, qty?: int|string|null, text?: string|null, descriptions?: array<int, string>|null}
 * @phpstan-type DiscogsVideo array{uri: string, title: string, description?: string|null, duration?: int|null, embed?: bool}
 * @phpstan-type DiscogsImage array{uri: string}
 * @phpstan-type DiscogsReleasePayload array{id: int, title: string, master_id?: int|null, country?: string|null, year?: int|null, released?: string|null, notes?: string|null, data_quality?: string|null, date_changed?: string|null, artists?: array<int, DiscogsPerson>, labels?: array<int, DiscogsLabel>, extraartists?: array<int, DiscogsCredit>, tracklist?: array<int, DiscogsTrack>, formats?: array<int, DiscogsFormat>, genres?: array<int, string>, styles?: array<int, string>, videos?: array<int, DiscogsVideo>, images?: array<int, DiscogsImage>}
 * @phpstan-type ReconciliationRow array{release_id: int, artist_id?: int, label_id?: int, genre_id?: int, style_id?: int, position?: int|string|null, sequence?: int, credited_name?: string|null, join_text?: string|null, catalog_number?: string|null, role?: string, title?: string, duration?: int|string|null, type?: string|null, name?: string, quantity?: int|null, text?: string|null, descriptions?: array<int, string>|null, uri?: string, description?: string|null, embed?: bool}
 */
class DiscogsReleaseNormalizer
{
    /**
     * @param  DiscogsReleasePayload  $payload
     */
    public function normalize(array $payload, CarbonInterface $fetchedAt): Release
    {
        $this->validate($payload);

        return DB::transaction(function () use ($payload, $fetchedAt): Release {
            $sourceHash = $this->sourceHash($payload);
            $release = Release::query()
                ->where('discogs_id', $payload['id'])
                ->lockForUpdate()
                ->firstOrNew();
            $isUnchanged = $release->exists && hash_equals($release->source_hash, $sourceHash);

            if (! $release->exists) {
                $release->fill(['image_urls' => []]);
            }

            $effectiveFetchedAt = $release->exists && $this->preservesExistingData($release, $payload)
                ? $release->fetched_at
                : $fetchedAt;

            $release->fill($this->releaseAttributes($payload, $effectiveFetchedAt, $sourceHash))->save();

            if ($isUnchanged) {
                $this->refreshReferencedResources($payload, $fetchedAt);

                return $release->refresh();
            }

            if (isset($payload['artists'])) {
                $this->normalizeArtists($release, $payload['artists'], $fetchedAt);
            }

            if (isset($payload['labels'])) {
                $this->normalizeLabels($release, $payload['labels'], $fetchedAt);
            }

            if (isset($payload['extraartists'])) {
                $this->normalizeCredits($release, $payload['extraartists'], $fetchedAt);
            }

            if (isset($payload['tracklist'])) {
                $this->normalizeTracks($release, $payload['tracklist'], $fetchedAt);
            }

            if (isset($payload['formats'])) {
                $this->normalizeFormats($release, $payload['formats'], $fetchedAt);
            }

            if (isset($payload['genres'])) {
                $this->normalizeGenres($release, $payload['genres'], $fetchedAt);
            }

            if (isset($payload['styles'])) {
                $this->normalizeStyles($release, $payload['styles'], $fetchedAt);
            }

            if (isset($payload['videos'])) {
                $this->normalizeVideos($release, $payload['videos'], $fetchedAt);
            }

            return $release->refresh();
        });
    }

    /** @param DiscogsReleasePayload $payload */
    private function validate(array $payload): void
    {
        Validator::make($payload, [
            'id' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'master_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'year' => ['sometimes', 'nullable', 'integer', 'between:0,65535'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'released' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'data_quality' => ['sometimes', 'nullable', 'string', 'max:255'],
            'date_changed' => ['sometimes', 'nullable', 'date'],
            'artists' => ['sometimes', 'array'],
            'artists.*.id' => ['required', 'integer', 'min:1'],
            'artists.*.name' => ['required', 'string', 'max:255'],
            'labels' => ['sometimes', 'array'],
            'labels.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'labels.*.name' => ['required', 'string', 'max:255'],
            'extraartists' => ['sometimes', 'array'],
            'extraartists.*.id' => ['required', 'integer', 'min:1'],
            'extraartists.*.name' => ['required', 'string', 'max:255'],
            'extraartists.*.role' => ['required', 'string', 'max:255'],
            'tracklist' => ['sometimes', 'array'],
            'tracklist.*.title' => ['required', 'string', 'max:255'],
            'tracklist.*.sub_tracks' => ['sometimes', 'array'],
            'tracklist.*.sub_tracks.*.title' => ['required', 'string', 'max:255'],
            'formats' => ['sometimes', 'array'],
            'formats.*.name' => ['required', 'string', 'max:255'],
            'formats.*.qty' => ['sometimes', 'nullable', 'integer', 'between:1,65535'],
            'formats.*.descriptions' => ['sometimes', 'nullable', 'array'],
            'formats.*.descriptions.*' => ['string', 'max:255'],
            'genres' => ['sometimes', 'array'],
            'genres.*' => ['string', 'max:255', 'distinct'],
            'styles' => ['sometimes', 'array'],
            'styles.*' => ['string', 'max:255', 'distinct'],
            'videos' => ['sometimes', 'array'],
            'videos.*.uri' => ['required', 'url', 'max:255'],
            'videos.*.title' => ['required', 'string', 'max:255'],
            'videos.*.duration' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'videos.*.embed' => ['sometimes', 'boolean'],
            'images' => ['sometimes', 'array'],
            'images.*.uri' => ['required', 'url', 'max:255'],
        ])->validate();
    }

    /**
     * @param  array<int, DiscogsPerson>  $artists
     */
    private function normalizeArtists(Release $release, array $artists, CarbonInterface $fetchedAt): void
    {
        $rows = [];

        foreach (collect($artists)->unique('id')->values() as $position => $data) {
            $artist = $this->upsertArtist($data, $fetchedAt);

            $rows[] = [
                'release_id' => $release->id,
                'artist_id' => $artist->id,
                'position' => $position,
                'credited_name' => Arr::get($data, 'anv') ?: null,
                'join_text' => Arr::get($data, 'join') ?: null,
            ];
        }

        $this->reconcileRows(ArtistRelease::class, $release, $rows, 'position', $fetchedAt);
    }

    /**
     * @param  array<int, DiscogsLabel>  $labels
     */
    private function normalizeLabels(Release $release, array $labels, CarbonInterface $fetchedAt): void
    {
        $rows = [];

        foreach ($labels as $position => $data) {
            $label = Label::query()->updateOrCreate(
                ['discogs_id' => $data['id']],
                [
                    'name' => $data['name'],
                    'source_url' => "https://www.discogs.com/label/{$data['id']}",
                    'fetched_at' => $fetchedAt,
                ],
            );

            $rows[] = [
                'release_id' => $release->id,
                'label_id' => $label->id,
                'position' => $position,
                'catalog_number' => Arr::get($data, 'catno') ?: null,
            ];
        }

        $this->reconcileRows(LabelRelease::class, $release, $rows, 'position', $fetchedAt);
    }

    /**
     * @param  array<int, DiscogsCredit>  $credits
     */
    private function normalizeCredits(Release $release, array $credits, CarbonInterface $fetchedAt): void
    {
        $rows = collect($credits)->map(function (array $data, int $position) use ($release, $fetchedAt): array {
            return [
                'release_id' => $release->id,
                'artist_id' => $this->upsertArtist($data, $fetchedAt)->id,
                'position' => $position,
                'role' => $data['role'],
                'credited_name' => Arr::get($data, 'anv') ?: null,
                'join_text' => Arr::get($data, 'join') ?: null,
            ];
        })->all();

        $this->reconcileRows(Credit::class, $release, $rows, 'position', $fetchedAt);
    }

    /**
     * @param  array<int, DiscogsTrack>  $tracks
     */
    private function normalizeTracks(Release $release, array $tracks, CarbonInterface $fetchedAt): void
    {
        $rows = collect($this->flattenTracks($tracks))->map(fn (array $data, int $sequence): array => [
            'release_id' => $release->id,
            'sequence' => $sequence,
            'position' => Arr::get($data, 'position') ?: null,
            'title' => $data['title'],
            'duration' => Arr::get($data, 'duration') ?: null,
            'type' => Arr::get($data, 'type_'),
        ])->all();

        $this->reconcileRows(Track::class, $release, $rows, 'sequence', $fetchedAt);
    }

    /**
     * @param  array<int, DiscogsTrack|DiscogsSubTrack>  $tracks
     * @return array<int, DiscogsTrack|DiscogsSubTrack>
     */
    private function flattenTracks(array $tracks): array
    {
        $flattened = [];

        foreach ($tracks as $track) {
            $subTracks = Arr::get($track, 'sub_tracks');

            if (is_array($subTracks) && $subTracks !== []) {
                array_push($flattened, ...$this->flattenTracks($subTracks));

                continue;
            }

            $flattened[] = $track;
        }

        return $flattened;
    }

    /**
     * @param  array<int, DiscogsFormat>  $formats
     */
    private function normalizeFormats(Release $release, array $formats, CarbonInterface $fetchedAt): void
    {
        $rows = collect($formats)->map(fn (array $data, int $position): array => [
            'release_id' => $release->id,
            'position' => $position,
            'name' => $data['name'],
            'quantity' => filled(Arr::get($data, 'qty')) ? (int) Arr::get($data, 'qty') : null,
            'text' => Arr::get($data, 'text') ?: null,
            'descriptions' => Arr::get($data, 'descriptions'),
        ])->all();

        $this->reconcileRows(Format::class, $release, $rows, 'position', $fetchedAt);
    }

    /** @param array<int, string> $genres */
    private function normalizeGenres(Release $release, array $genres, CarbonInterface $fetchedAt): void
    {
        $rows = [];

        foreach ($genres as $position => $name) {
            $genre = Genre::query()->firstOrCreate(['name' => $name]);
            $rows[] = [
                'release_id' => $release->id,
                'genre_id' => $genre->id,
                'position' => $position,
            ];
        }

        $this->reconcileRows(GenreRelease::class, $release, $rows, 'genre_id', $fetchedAt, true);
    }

    /** @param array<int, string> $styles */
    private function normalizeStyles(Release $release, array $styles, CarbonInterface $fetchedAt): void
    {
        $rows = [];

        foreach ($styles as $position => $name) {
            $style = Style::query()->firstOrCreate(['name' => $name]);
            $rows[] = [
                'release_id' => $release->id,
                'style_id' => $style->id,
                'position' => $position,
            ];
        }

        $this->reconcileRows(ReleaseStyle::class, $release, $rows, 'style_id', $fetchedAt, true);
    }

    /**
     * @param  array<int, DiscogsVideo>  $videos
     */
    private function normalizeVideos(Release $release, array $videos, CarbonInterface $fetchedAt): void
    {
        $rows = collect($videos)
            ->unique('uri')
            ->values()
            ->map(fn (array $data, int $position): array => [
                'release_id' => $release->id,
                'position' => $position,
                'uri' => $data['uri'],
                'title' => $data['title'],
                'description' => Arr::get($data, 'description'),
                'duration' => Arr::get($data, 'duration'),
                'embed' => Arr::get($data, 'embed', false),
            ])->all();

        $this->reconcileRows(Video::class, $release, $rows, 'uri', $fetchedAt, true);
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  array<int, ReconciliationRow>  $rows
     */
    private function reconcileRows(
        string $modelClass,
        Release $release,
        array $rows,
        string $identity,
        CarbonInterface $retiredAt,
        bool $reuseRetired = false,
    ): void {
        $query = $modelClass::query()->where('release_id', $release->id);

        if (! $reuseRetired) {
            $query->whereNull('retired_at');
        }

        $existingRows = $query->get()->keyBy($identity);

        foreach ($rows as $attributes) {
            $identityValue = $attributes[$identity];

            if (! is_int($identityValue) && ! is_string($identityValue)) {
                throw new \LogicException("Reconciliation identity [{$identity}] must be an integer or string.");
            }

            $existing = $existingRows->pull($identityValue);

            if ($existing === null) {
                $model = new $modelClass;
                $model->forceFill($attributes)->save();

                continue;
            }

            $matches = collect($attributes)->every(
                fn (mixed $value, string $attribute): bool => $existing->getAttribute($attribute) === $value,
            );

            if ($matches && $existing->getAttribute('retired_at') === null) {
                continue;
            }

            if ($reuseRetired) {
                $existing->forceFill([...$attributes, 'retired_at' => null])->save();

                continue;
            }

            $existing->forceFill(['retired_at' => $retiredAt])->save();
            $model = new $modelClass;
            $model->forceFill($attributes)->save();
        }

        $existingRows
            ->filter(fn (Model $model): bool => $model->getAttribute('retired_at') === null)
            ->each(function (Model $model) use ($retiredAt): void {
                $model->forceFill(['retired_at' => $retiredAt])->save();
            });
    }

    /**
     * @param  DiscogsReleasePayload  $payload
     * @return array<int, string>
     */
    private function imageUrls(array $payload): array
    {
        $images = Arr::get($payload, 'images', []);

        if (! is_array($images)) {
            return [];
        }

        $imageUrls = [];

        foreach ($images as $image) {
            if (! is_array($image)) {
                continue;
            }

            $uri = Arr::get($image, 'uri');

            if (is_string($uri)) {
                $imageUrls[] = $uri;
            }
        }

        return $imageUrls;
    }

    /**
     * @param  DiscogsReleasePayload  $payload
     * @return array{discogs_id: int, title: string, source_url: string, fetched_at: CarbonInterface, source_hash: string, raw_payload: DiscogsReleasePayload, master_discogs_id?: int|null, country?: string|null, released_year?: int|null, released?: string|null, notes?: string|null, data_quality?: string|null, discogs_changed_at?: CarbonImmutable|null, image_urls?: array<int, string>}
     */
    private function releaseAttributes(array $payload, CarbonInterface $fetchedAt, string $sourceHash): array
    {
        $attributes = [
            'discogs_id' => $payload['id'],
            'title' => $payload['title'],
            'source_url' => "https://www.discogs.com/release/{$payload['id']}",
            'fetched_at' => $fetchedAt,
            'source_hash' => $sourceHash,
            'raw_payload' => $payload,
        ];
        $optionalAttributes = [
            'master_id' => 'master_discogs_id',
            'country' => 'country',
            'year' => 'released_year',
            'released' => 'released',
            'notes' => 'notes',
            'data_quality' => 'data_quality',
        ];

        foreach ($optionalAttributes as $payloadKey => $attribute) {
            if (Arr::exists($payload, $payloadKey)) {
                $attributes[$attribute] = Arr::get($payload, $payloadKey) ?: null;
            }
        }

        if (Arr::exists($payload, 'date_changed')) {
            $attributes['discogs_changed_at'] = $this->changedAt($payload);
        }

        if (Arr::exists($payload, 'images')) {
            $attributes['image_urls'] = $this->imageUrls($payload);
        }

        return $attributes;
    }

    /** @param DiscogsReleasePayload $payload */
    private function refreshReferencedResources(array $payload, CarbonInterface $fetchedAt): void
    {
        foreach (Arr::get($payload, 'artists', []) as $artist) {
            $this->upsertArtist($artist, $fetchedAt);
        }

        foreach (Arr::get($payload, 'extraartists', []) as $artist) {
            $this->upsertArtist($artist, $fetchedAt);
        }

        foreach (Arr::get($payload, 'labels', []) as $data) {
            Label::query()->where('discogs_id', $data['id'])->update(['fetched_at' => $fetchedAt]);
        }
    }

    /** @param DiscogsReleasePayload $payload */
    private function preservesExistingData(Release $release, array $payload): bool
    {
        $optionalAttributes = [
            'master_id' => 'master_discogs_id',
            'country' => 'country',
            'year' => 'released_year',
            'released' => 'released',
            'notes' => 'notes',
            'data_quality' => 'data_quality',
            'date_changed' => 'discogs_changed_at',
        ];

        foreach ($optionalAttributes as $payloadKey => $attribute) {
            if (! Arr::exists($payload, $payloadKey) && $release->getAttribute($attribute) !== null) {
                return true;
            }
        }

        if (! Arr::exists($payload, 'images') && filled($release->image_urls)) {
            return true;
        }

        $activeRelationshipCounts = [
            'artists' => fn (): int => $release->artists()->wherePivotNull('retired_at')->count(),
            'labels' => fn (): int => $release->labels()->wherePivotNull('retired_at')->count(),
            'extraartists' => fn (): int => $release->credits()->whereNull('retired_at')->count(),
            'tracklist' => fn (): int => $release->tracks()->whereNull('retired_at')->count(),
            'formats' => fn (): int => $release->formats()->whereNull('retired_at')->count(),
            'genres' => fn (): int => $release->genres()->wherePivotNull('retired_at')->count(),
            'styles' => fn (): int => $release->styles()->wherePivotNull('retired_at')->count(),
            'videos' => fn (): int => $release->videos()->whereNull('retired_at')->count(),
        ];

        foreach ($activeRelationshipCounts as $payloadKey => $count) {
            if (! Arr::exists($payload, $payloadKey) && $count() > 0) {
                return true;
            }
        }

        return false;
    }

    /** @param DiscogsPerson $data */
    private function upsertArtist(array $data, CarbonInterface $fetchedAt): Artist
    {
        return Artist::query()->updateOrCreate(
            ['discogs_id' => $data['id']],
            [
                'name' => $data['name'],
                'source_url' => "https://www.discogs.com/artist/{$data['id']}",
                'fetched_at' => $fetchedAt,
            ],
        );
    }

    /** @param DiscogsReleasePayload $payload */
    private function changedAt(array $payload): ?CarbonImmutable
    {
        $changedAt = Arr::get($payload, 'date_changed');

        return filled($changedAt) ? CarbonImmutable::parse($changedAt) : null;
    }

    /** @param DiscogsReleasePayload $payload */
    private function sourceHash(array $payload): string
    {
        return hash('sha256', json_encode($this->canonicalize($payload), JSON_THROW_ON_ERROR));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! Arr::isList($value)) {
            ksort($value);
        }

        return Arr::map($value, fn (mixed $item): mixed => $this->canonicalize($item));
    }
}
