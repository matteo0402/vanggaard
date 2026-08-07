<?php

use App\Models\CollectionItem;
use App\Models\CollectionItemStorageAssignment;
use App\Models\DiscogsAccount;
use App\Models\DiscogsCollectionInstance;
use App\Models\DiscogsSyncRun;
use App\Models\PersonalReleaseMetadata;
use App\Models\Release;
use App\Models\Riddim;
use App\Models\StorageLocation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);

test('ownership factories create isolated records', function () {
    $models = [
        DiscogsAccount::factory()->create(),
        DiscogsSyncRun::factory()->create(),
        CollectionItem::factory()->create(),
        DiscogsCollectionInstance::factory()->create(),
        PersonalReleaseMetadata::factory()->create(),
        Tag::factory()->create(),
        Riddim::factory()->create(),
        StorageLocation::factory()->create(),
        CollectionItemStorageAssignment::factory()->create(),
    ];

    foreach ($models as $model) {
        $this->assertModelExists($model);
    }
});

test('one user can own multiple physical copies of a release', function () {
    $user = User::factory()->create();
    $release = Release::factory()->create();

    $items = CollectionItem::factory()
        ->count(2)
        ->for($user)
        ->for($release)
        ->create();

    expect($items)->toHaveCount(2)
        ->each(fn ($item) => $item->user->is($user) && $item->release->is($release));
});

test('personal release metadata supplies a corrected effective year without changing Discogs data', function () {
    $release = Release::factory()->create(['released_year' => 1974]);
    $metadata = PersonalReleaseMetadata::factory()
        ->for($release)
        ->create(['corrected_year' => null]);

    expect($metadata->effectiveYear())->toBe(1974);

    $metadata->update(['corrected_year' => 1975]);

    expect($metadata->effectiveYear())->toBe(1975)
        ->and($release->refresh()->released_year)->toBe(1974);
});

test('effective year hides stale Discogs data but preserves a personal correction', function () {
    $release = Release::factory()->create([
        'released_year' => 1974,
        'fetched_at' => now()->subHours(6)->subSecond(),
    ]);
    $metadata = PersonalReleaseMetadata::factory()
        ->for($release)
        ->create(['corrected_year' => null]);

    expect($metadata->effectiveYear())->toBeNull();

    $metadata->update(['corrected_year' => 1975]);

    expect($metadata->effectiveYear())->toBe(1975);
});

test('personal release metadata is unique per user and release', function () {
    $metadata = PersonalReleaseMetadata::factory()->create();

    expect(fn () => PersonalReleaseMetadata::factory()->create([
        'user_id' => $metadata->user_id,
        'release_id' => $metadata->release_id,
    ]))->toThrow(QueryException::class);
});

test('Discogs collection identifiers remain separate and unique within an account', function () {
    $account = DiscogsAccount::factory()->create();
    $instance = DiscogsCollectionInstance::factory()->create([
        'discogs_account_id' => $account->id,
        'collection_item_id' => CollectionItem::factory()->create(['user_id' => $account->user_id])->id,
        'discogs_instance_id' => 12345,
    ]);

    expect(fn () => DiscogsCollectionInstance::factory()->create([
        'discogs_account_id' => $account->id,
        'collection_item_id' => CollectionItem::factory()->create(['user_id' => $account->user_id])->id,
        'discogs_instance_id' => $instance->discogs_instance_id,
    ]))->toThrow(QueryException::class);

    expect(Schema::hasColumns('collection_items', ['user_id', 'release_id']))->toBeTrue()
        ->and(Schema::hasColumns('collection_items', ['discogs_instance_id', 'discogs_folder_id']))->toBeFalse();
});

test('user-owned relationships reject cross-owner records', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $account = DiscogsAccount::factory()->create(['user_id' => $firstUser->id]);
    $otherUsersItem = CollectionItem::factory()->create(['user_id' => $secondUser->id]);

    expect(fn () => DiscogsCollectionInstance::factory()->create([
        'discogs_account_id' => $account->id,
        'collection_item_id' => $otherUsersItem->id,
        'user_id' => $firstUser->id,
    ]))->toThrow(QueryException::class);

    $tag = Tag::factory()->create(['user_id' => $secondUser->id]);

    expect(fn () => $otherUsersItem->tags()->attach($tag))->not->toThrow(QueryException::class);

    $firstUsersItem = CollectionItem::factory()->create(['user_id' => $firstUser->id]);

    expect(fn () => $firstUsersItem->tags()->attach($tag))->toThrow(QueryException::class);

    $otherUsersLocation = StorageLocation::factory()->create(['user_id' => $secondUser->id]);

    expect(fn () => CollectionItemStorageAssignment::factory()->create([
        'collection_item_id' => $firstUsersItem->id,
        'storage_location_id' => $otherUsersLocation->id,
        'user_id' => $firstUser->id,
    ]))->toThrow(QueryException::class);

    $otherAccount = DiscogsAccount::factory()->create(['user_id' => $secondUser->id]);
    $otherAccountsRun = DiscogsSyncRun::factory()->create(['discogs_account_id' => $otherAccount->id]);

    expect(fn () => DiscogsCollectionInstance::factory()->create([
        'discogs_account_id' => $account->id,
        'collection_item_id' => $firstUsersItem->id,
        'user_id' => $firstUser->id,
        'last_seen_sync_run_id' => $otherAccountsRun->id,
    ]))->toThrow(QueryException::class);
});

test('purging a Discogs account retains user-owned collection data', function () {
    $account = DiscogsAccount::factory()->create();
    $item = CollectionItem::factory()->create(['user_id' => $account->user_id]);
    $metadata = PersonalReleaseMetadata::factory()->create([
        'user_id' => $account->user_id,
        'release_id' => $item->release_id,
    ]);
    $run = DiscogsSyncRun::factory()->create(['discogs_account_id' => $account->id]);
    $instance = DiscogsCollectionInstance::factory()->create([
        'discogs_account_id' => $account->id,
        'collection_item_id' => $item->id,
        'user_id' => $account->user_id,
        'last_seen_sync_run_id' => $run->id,
    ]);

    $account->delete();

    $this->assertModelMissing($account);
    $this->assertModelMissing($instance);
    $this->assertModelMissing($run);
    $this->assertModelExists($item);
    $this->assertModelExists($metadata);
});

test('tags and riddims are user-owned and assignable to physical copies', function () {
    $item = CollectionItem::factory()->create();
    $tag = Tag::factory()->create(['user_id' => $item->user_id]);
    $riddim = Riddim::factory()->create(['user_id' => $item->user_id]);

    $item->tags()->attach($tag);
    $item->riddims()->attach($riddim);

    expect($item->tags()->firstOrFail()->is($tag))->toBeTrue()
        ->and($item->riddims()->firstOrFail()->is($riddim))->toBeTrue();
});

test('storage assignments preserve physical location history', function () {
    $item = CollectionItem::factory()->create();
    $room = StorageLocation::factory()->create(['user_id' => $item->user_id]);
    $box = StorageLocation::factory()->create([
        'user_id' => $item->user_id,
        'parent_id' => $room->id,
        'kind' => 'box',
    ]);
    $previousAssignment = CollectionItemStorageAssignment::factory()->create([
        'collection_item_id' => $item->id,
        'storage_location_id' => $room->id,
        'removed_at' => now()->subDay(),
    ]);
    $currentAssignment = CollectionItemStorageAssignment::factory()->create([
        'collection_item_id' => $item->id,
        'storage_location_id' => $box->id,
    ]);

    expect($previousAssignment->removed_at)->not->toBeNull()
        ->and($currentAssignment->removed_at)->toBeNull()
        ->and($item->storageAssignments()->count())->toBe(2)
        ->and($box->parent->is($room))->toBeTrue();
});

test('storage location names are unique among siblings including root locations', function () {
    $user = User::factory()->create();
    $room = StorageLocation::factory()->create([
        'user_id' => $user->id,
        'kind' => 'room',
        'name' => 'Studio',
    ]);

    expect(fn () => StorageLocation::factory()->create([
        'user_id' => $user->id,
        'kind' => 'room',
        'name' => $room->name,
    ]))->toThrow(QueryException::class);

    StorageLocation::factory()->create([
        'user_id' => $user->id,
        'parent_id' => $room->id,
        'kind' => 'box',
        'name' => 'A',
    ]);

    expect(fn () => StorageLocation::factory()->create([
        'user_id' => $user->id,
        'parent_id' => $room->id,
        'kind' => 'box',
        'name' => 'A',
    ]))->toThrow(QueryException::class);
});

test('sync run defaults are available before and after persistence', function () {
    $run = DiscogsSyncRun::factory()->make();

    expect($run->status)->toBe('pending')
        ->and($run->is_full_reconciliation)->toBeFalse()
        ->and($run->items_seen)->toBe(0);

    $run->save();

    expect($run->refresh()->status)->toBe('pending')
        ->and($run->is_full_reconciliation)->toBeFalse();
});
