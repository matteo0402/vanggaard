<?php

use App\CollectionCatalog;
use App\Events\PersonalMetadataChanged;
use App\Models\CollectionItem;
use App\Models\Release;
use App\Models\Riddim;
use App\Models\Tag;
use App\Models\Track;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(LazilyRefreshDatabase::class);

test('users can create normalized tags and riddims while duplicate names remain user scoped', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('vocabularies.tags.store'), ['name' => '  Late   Night  '])
        ->assertRedirect();
    $this->post(route('vocabularies.riddims.store'), ['name' => '  ÄBYSS   DUB  '])
        ->assertRedirect();

    $tag = Tag::query()->whereBelongsTo($owner)->sole();
    $riddim = Riddim::query()->whereBelongsTo($owner)->sole();

    expect($tag->name)->toBe('Late Night')
        ->and($tag->normalized_name)->toBe('late night')
        ->and($riddim->name)->toBe('ÄBYSS DUB')
        ->and($riddim->normalized_name)->toBe('äbyss dub');

    $this->post(route('vocabularies.tags.store'), ['name' => 'late night'])
        ->assertSessionHasErrors('name');
    $this->post(route('vocabularies.riddims.store'), ['name' => ' äbyss dub '])
        ->assertSessionHasErrors('name');

    $this->actingAs($otherUser)
        ->post(route('vocabularies.tags.store'), ['name' => 'late night'])
        ->assertRedirect();
    $this->post(route('vocabularies.riddims.store'), ['name' => 'ÄBYSS DUB'])
        ->assertRedirect();

    expect(Tag::query()->count())->toBe(2)
        ->and(Riddim::query()->count())->toBe(2);
});

test('owners can rename vocabularies and normalized duplicates are rejected', function () {
    $owner = User::factory()->create();
    $tag = Tag::factory()->for($owner)->create(['name' => 'Warm Up']);
    $otherTag = Tag::factory()->for($owner)->create(['name' => 'Roots']);
    $riddim = Riddim::factory()->for($owner)->create(['name' => 'Stalag']);
    $otherRiddim = Riddim::factory()->for($owner)->create(['name' => 'Real Rock']);

    $this->actingAs($owner)
        ->patch(route('vocabularies.tags.update', $tag), ['name' => '  Late   Set '])
        ->assertRedirect();
    $this->patch(route('vocabularies.riddims.update', $riddim), ['name' => '  Answer   Version '])
        ->assertRedirect();

    expect($tag->refresh()->name)->toBe('Late Set')
        ->and($tag->normalized_name)->toBe('late set')
        ->and($riddim->refresh()->name)->toBe('Answer Version')
        ->and($riddim->normalized_name)->toBe('answer version');

    $this->patch(route('vocabularies.tags.update', $tag), ['name' => ' roots '])
        ->assertSessionHasErrors('name');
    $this->patch(route('vocabularies.riddims.update', $riddim), ['name' => 'real   rock'])
        ->assertSessionHasErrors('name');

    expect($tag->refresh()->name)->toBe('Late Set')
        ->and($otherTag->refresh()->name)->toBe('Roots')
        ->and($riddim->refresh()->name)->toBe('Answer Version')
        ->and($otherRiddim->refresh()->name)->toBe('Real Rock');
});

test('renaming and deleting assigned vocabularies dispatches one change per affected release', function () {
    $owner = User::factory()->create();
    $firstRelease = Release::factory()->create();
    $secondRelease = Release::factory()->create();
    $tag = Tag::factory()->for($owner)->create();
    $riddim = Riddim::factory()->for($owner)->create();
    $now = now();

    DB::table('release_tag')->insert([
        'user_id' => $owner->id,
        'release_id' => $firstRelease->id,
        'tag_id' => $tag->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('release_riddim')->insert([
        'user_id' => $owner->id,
        'release_id' => $firstRelease->id,
        'riddim_id' => $riddim->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('track_riddim_override')->insert([
        'user_id' => $owner->id,
        'release_id' => $secondRelease->id,
        'track_sequence' => 3,
        'riddim_id' => $riddim->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    Event::fake([PersonalMetadataChanged::class]);

    $this->actingAs($owner)
        ->patch(route('vocabularies.riddims.update', $riddim), ['name' => 'Renamed Riddim'])
        ->assertRedirect();

    Event::assertDispatchedTimes(PersonalMetadataChanged::class, 2);
    Event::assertDispatched(fn (PersonalMetadataChanged $event): bool => $event->userId === $owner->id
        && $event->releaseId === $firstRelease->id);
    Event::assertDispatched(fn (PersonalMetadataChanged $event): bool => $event->userId === $owner->id
        && $event->releaseId === $secondRelease->id);

    Event::fake([PersonalMetadataChanged::class]);

    $this->delete(route('vocabularies.tags.destroy', $tag))->assertRedirect();

    Event::assertDispatchedTimes(PersonalMetadataChanged::class, 1);
    Event::assertDispatched(fn (PersonalMetadataChanged $event): bool => $event->userId === $owner->id
        && $event->releaseId === $firstRelease->id);
    $this->assertModelMissing($tag);

    Event::fake([PersonalMetadataChanged::class]);

    $this->delete(route('vocabularies.riddims.destroy', $riddim))->assertRedirect();

    Event::assertDispatchedTimes(PersonalMetadataChanged::class, 2);
    $this->assertModelMissing($riddim);
});

test('only vocabulary owners can rename or delete it', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $tag = Tag::factory()->for($owner)->create();
    $riddim = Riddim::factory()->for($owner)->create();

    $this->actingAs($otherUser)
        ->patch(route('vocabularies.tags.update', $tag), ['name' => 'Nope'])
        ->assertForbidden();
    $this->delete(route('vocabularies.tags.destroy', $tag))->assertForbidden();
    $this->patch(route('vocabularies.riddims.update', $riddim), ['name' => 'Nope'])
        ->assertForbidden();
    $this->delete(route('vocabularies.riddims.destroy', $riddim))->assertForbidden();

    $this->assertModelExists($tag);
    $this->assertModelExists($riddim);
});

test('owners can atomically assign and remove release vocabularies', function () {
    $owner = User::factory()->create();
    $release = Release::factory()->create();
    CollectionItem::factory()->for($owner)->for($release)->create();
    Track::factory()->for($release)->create(['sequence' => 1]);
    Track::factory()->for($release)->create(['sequence' => 2]);
    $tags = Tag::factory()->count(2)->for($owner)->create();
    $defaultRiddim = Riddim::factory()->for($owner)->create();
    $overrideRiddim = Riddim::factory()->for($owner)->create();

    Event::fake([PersonalMetadataChanged::class]);

    $this->actingAs($owner)
        ->put(route('releases.release_vocabulary.update', $release), [
            'tag_ids' => $tags->pluck('id')->all(),
            'release_riddim_id' => $defaultRiddim->id,
            'track_riddim_overrides' => [
                ['sequence' => 2, 'riddim_id' => $overrideRiddim->id],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseCount('release_tag', 2);
    $this->assertDatabaseHas('release_riddim', [
        'user_id' => $owner->id,
        'release_id' => $release->id,
        'riddim_id' => $defaultRiddim->id,
    ]);
    $this->assertDatabaseHas('track_riddim_override', [
        'user_id' => $owner->id,
        'release_id' => $release->id,
        'track_sequence' => 2,
        'riddim_id' => $overrideRiddim->id,
    ]);
    Event::assertDispatchedTimes(PersonalMetadataChanged::class, 1);

    $this->put(route('releases.release_vocabulary.update', $release), [
        'tag_ids' => [],
        'release_riddim_id' => null,
        'track_riddim_overrides' => [],
    ])->assertRedirect();

    $this->assertDatabaseCount('release_tag', 0);
    $this->assertDatabaseCount('release_riddim', 0);
    $this->assertDatabaseCount('track_riddim_override', 0);
});

test('release serialization exposes ordered vocabularies and effective track riddims', function () {
    $owner = User::factory()->create();
    $release = Release::factory()->create();
    CollectionItem::factory()->for($owner)->for($release)->create();
    $firstTrack = Track::factory()->for($release)->create(['sequence' => 1, 'title' => 'Default']);
    Track::factory()->for($release)->create(['sequence' => 2, 'title' => 'Override']);
    $secondTag = Tag::factory()->for($owner)->create(['name' => 'Zulu']);
    $firstTag = Tag::factory()->for($owner)->create(['name' => 'ambient']);
    $defaultRiddim = Riddim::factory()->for($owner)->create(['name' => 'Stalag']);
    $overrideRiddim = Riddim::factory()->for($owner)->create(['name' => 'Answer']);

    $this->actingAs($owner)->put(route('releases.release_vocabulary.update', $release), [
        'tag_ids' => [$secondTag->id, $firstTag->id],
        'release_riddim_id' => $defaultRiddim->id,
        'track_riddim_overrides' => [
            ['sequence' => 2, 'riddim_id' => $overrideRiddim->id],
        ],
    ])->assertRedirect();

    $serialized = app(CollectionCatalog::class)->release($owner, $release);

    expect($serialized['personal']['tags'])->toEqualCanonicalizing([$firstTag->id, $secondTag->id])
        ->and($serialized['personal']['release_riddim_id'])->toBe($defaultRiddim->id)
        ->and($serialized['personal']['track_riddim_overrides'])->toBe([
            ['sequence' => 2, 'riddim_id' => $overrideRiddim->id],
        ])
        ->and($serialized['vocabularies']['tags'])->toBe([
            ['id' => $firstTag->id, 'name' => 'ambient'],
            ['id' => $secondTag->id, 'name' => 'Zulu'],
        ])
        ->and($serialized['vocabularies']['riddims'])->toBe([
            ['id' => $overrideRiddim->id, 'name' => 'Answer'],
            ['id' => $defaultRiddim->id, 'name' => 'Stalag'],
        ])
        ->and($serialized['discogs']['tracks'][0]['sequence'])->toBe(1)
        ->and($serialized['discogs']['tracks'][0]['effective_riddim_id'])->toBe($defaultRiddim->id)
        ->and($serialized['discogs']['tracks'][0]['is_riddim_override'])->toBeFalse()
        ->and($serialized['discogs']['tracks'][1]['effective_riddim_id'])->toBe($overrideRiddim->id)
        ->and($serialized['discogs']['tracks'][1]['is_riddim_override'])->toBeTrue();

    $firstTrack->delete();
    Track::factory()->for($release)->create(['sequence' => 1, 'title' => 'Replacement']);

    expect(app(CollectionCatalog::class)->release($owner, $release)['discogs']['tracks'][0])
        ->title->toBe('Replacement')
        ->effective_riddim_id->toBe($defaultRiddim->id);
});

test('track overrides survive replacement of their Discogs track row', function () {
    $owner = User::factory()->create();
    $release = Release::factory()->create();
    CollectionItem::factory()->for($owner)->for($release)->create();
    $track = Track::factory()->for($release)->create(['sequence' => 4]);
    $riddim = Riddim::factory()->for($owner)->create();

    $this->actingAs($owner)->put(route('releases.release_vocabulary.update', $release), [
        'tag_ids' => [],
        'release_riddim_id' => null,
        'track_riddim_overrides' => [['sequence' => 4, 'riddim_id' => $riddim->id]],
    ])->assertRedirect();

    $track->delete();
    Track::factory()->for($release)->create(['sequence' => 4]);

    $this->assertDatabaseHas('track_riddim_override', [
        'user_id' => $owner->id,
        'release_id' => $release->id,
        'track_sequence' => 4,
        'riddim_id' => $riddim->id,
    ]);
});

test('release vocabulary validation rejects cross-user IDs duplicate values and foreign sequences', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $release = Release::factory()->create();
    $otherRelease = Release::factory()->create();
    CollectionItem::factory()->for($owner)->for($release)->create();
    Track::factory()->for($release)->create(['sequence' => 1]);
    Track::factory()->for($otherRelease)->create(['sequence' => 9]);
    $tag = Tag::factory()->for($owner)->create();
    $otherTag = Tag::factory()->for($otherUser)->create();
    $riddim = Riddim::factory()->for($owner)->create();
    $otherRiddim = Riddim::factory()->for($otherUser)->create();

    $this->actingAs($owner)
        ->put(route('releases.release_vocabulary.update', $release), [
            'tag_ids' => [$tag->id, $tag->id, $otherTag->id],
            'release_riddim_id' => $otherRiddim->id,
            'track_riddim_overrides' => [
                ['sequence' => 9, 'riddim_id' => $riddim->id],
                ['sequence' => 9, 'riddim_id' => $otherRiddim->id],
            ],
        ])
        ->assertSessionHasErrors([
            'tag_ids.0',
            'tag_ids.1',
            'tag_ids.2',
            'release_riddim_id',
            'track_riddim_overrides.0.sequence',
            'track_riddim_overrides.1.sequence',
            'track_riddim_overrides.1.riddim_id',
        ]);

    $this->assertDatabaseCount('release_tag', 0);
    $this->assertDatabaseCount('release_riddim', 0);
    $this->assertDatabaseCount('track_riddim_override', 0);
});

test('release vocabulary updates require active ownership', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $release = Release::factory()->create();
    CollectionItem::factory()->for($owner)->for($release)->create();
    $inactiveRelease = CollectionItem::factory()->inactive()->for($owner)->create()->release;
    $payload = [
        'tag_ids' => [],
        'release_riddim_id' => null,
        'track_riddim_overrides' => [],
    ];

    $this->actingAs($otherUser)
        ->put(route('releases.release_vocabulary.update', $release), $payload)
        ->assertForbidden();
    $this->actingAs($owner)
        ->put(route('releases.release_vocabulary.update', $inactiveRelease), $payload)
        ->assertForbidden();
});

test('vocabulary writes require authentication', function () {
    $tag = Tag::factory()->create();
    $riddim = Riddim::factory()->create();
    $release = Release::factory()->create();

    $this->post(route('vocabularies.tags.store'), [])->assertRedirectToRoute('login');
    $this->patch(route('vocabularies.tags.update', $tag), [])->assertRedirectToRoute('login');
    $this->delete(route('vocabularies.tags.destroy', $tag))->assertRedirectToRoute('login');
    $this->post(route('vocabularies.riddims.store'), [])->assertRedirectToRoute('login');
    $this->patch(route('vocabularies.riddims.update', $riddim), [])->assertRedirectToRoute('login');
    $this->delete(route('vocabularies.riddims.destroy', $riddim))->assertRedirectToRoute('login');
    $this->put(route('releases.release_vocabulary.update', $release), [])->assertRedirectToRoute('login');
});

test('personal metadata changes have a stable post-commit contract', function () {
    $event = new PersonalMetadataChanged(userId: 12, releaseId: 34);

    expect($event)->toBeInstanceOf(ShouldDispatchAfterCommit::class)
        ->and($event->userId)->toBe(12)
        ->and($event->releaseId)->toBe(34);
});
