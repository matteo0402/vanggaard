<?php

use App\Models\CollectionItem;
use App\Models\PersonalReleaseMetadata;
use App\Models\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('an owner can save core personal metadata for a release', function () {
    $owner = User::factory()->create();
    $release = Release::factory()->create(['released_year' => 1976]);
    CollectionItem::factory()->for($owner)->for($release)->create();

    $this->actingAs($owner)
        ->patch(route('releases.personal_metadata.update', $release), [
            'personal_notes' => 'Warm-up copy with a clean intro.',
            'corrected_year' => 1975,
            'is_year_approximate' => true,
            'rating' => 5,
            'is_favourite' => true,
            'is_dj_ready' => true,
            'energy' => 4,
            'bpm' => 78.5,
        ])
        ->assertRedirect();

    $metadata = PersonalReleaseMetadata::query()->whereBelongsTo($owner)->sole();

    expect($metadata->release_id)->toBe($release->id)
        ->and($metadata->personal_notes)->toBe('Warm-up copy with a clean intro.')
        ->and($metadata->corrected_year)->toBe(1975)
        ->and($metadata->is_year_approximate)->toBeTrue()
        ->and($metadata->rating)->toBe(5)
        ->and($metadata->is_favourite)->toBeTrue()
        ->and($metadata->is_dj_ready)->toBeTrue()
        ->and($metadata->energy)->toBe(4)
        ->and($metadata->bpm)->toBe(78.5)
        ->and($release->fresh()->released_year)->toBe(1976);
});

test('an owner can clear optional metadata and boolean flags', function () {
    $owner = User::factory()->create();
    $release = Release::factory()->create();
    CollectionItem::factory()->for($owner)->for($release)->create();
    $metadata = PersonalReleaseMetadata::factory()->for($owner)->for($release)->create([
        'personal_notes' => 'Remove me',
        'corrected_year' => 1975,
        'is_year_approximate' => true,
        'rating' => 5,
        'is_favourite' => true,
        'is_dj_ready' => true,
        'energy' => 4,
        'bpm' => 78.5,
    ]);

    $this->actingAs($owner)
        ->patch(route('releases.personal_metadata.update', $release), [
            'personal_notes' => null,
            'corrected_year' => null,
            'is_year_approximate' => false,
            'rating' => null,
            'is_favourite' => false,
            'is_dj_ready' => false,
            'energy' => null,
            'bpm' => null,
        ])
        ->assertRedirect();

    expect($metadata->fresh())
        ->personal_notes->toBeNull()
        ->corrected_year->toBeNull()
        ->is_year_approximate->toBeFalse()
        ->rating->toBeNull()
        ->is_favourite->toBeFalse()
        ->is_dj_ready->toBeFalse()
        ->energy->toBeNull()
        ->bpm->toBeNull();
});

test('personal metadata validation is clear and leaves saved values unchanged', function () {
    $owner = User::factory()->create();
    $release = Release::factory()->create();
    CollectionItem::factory()->for($owner)->for($release)->create();
    $metadata = PersonalReleaseMetadata::factory()->for($owner)->for($release)->create([
        'corrected_year' => 1975,
        'rating' => 4,
    ]);

    $this->actingAs($owner)
        ->from(route('collection.show', $release))
        ->patch(route('releases.personal_metadata.update', $release), [
            'personal_notes' => str_repeat('a', 5001),
            'corrected_year' => null,
            'is_year_approximate' => true,
            'rating' => 6,
            'is_favourite' => false,
            'is_dj_ready' => false,
            'energy' => 6,
            'bpm' => 1000,
        ])
        ->assertRedirectToRoute('collection.show', $release)
        ->assertSessionHasErrors([
            'personal_notes',
            'corrected_year',
            'rating',
            'energy',
            'bpm',
        ]);

    expect($metadata->fresh()->corrected_year)->toBe(1975)
        ->and($metadata->fresh()->rating)->toBe(4);
});

test('users cannot edit personal metadata for releases outside their active collection', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $release = Release::factory()->create();
    CollectionItem::factory()->for($owner)->for($release)->create();

    $this->actingAs($otherUser)
        ->patch(route('releases.personal_metadata.update', $release), [
            'personal_notes' => null,
            'corrected_year' => null,
            'is_year_approximate' => false,
            'rating' => null,
            'is_favourite' => true,
            'is_dj_ready' => false,
            'energy' => null,
            'bpm' => null,
        ])
        ->assertForbidden();

    expect(PersonalReleaseMetadata::query()->exists())->toBeFalse();
});

test('users cannot edit metadata after their last active copy is removed', function () {
    $owner = User::factory()->create();
    $release = Release::factory()->create();
    CollectionItem::factory()->inactive()->for($owner)->for($release)->create();

    $this->actingAs($owner)
        ->patch(route('releases.personal_metadata.update', $release), [
            'personal_notes' => null,
            'corrected_year' => null,
            'is_year_approximate' => false,
            'rating' => null,
            'is_favourite' => true,
            'is_dj_ready' => false,
            'energy' => null,
            'bpm' => null,
        ])
        ->assertForbidden();

    expect(PersonalReleaseMetadata::query()->exists())->toBeFalse();
});

test('personal metadata updates require authentication', function () {
    $release = Release::factory()->create();

    $this->patch(route('releases.personal_metadata.update', $release), [])
        ->assertRedirectToRoute('login');
});
