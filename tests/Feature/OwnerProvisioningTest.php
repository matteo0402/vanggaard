<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('database seeding does not create default credentials', function () {
    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(User::query()->exists())->toBeFalse();
});

test('the initial owner can be provisioned securely', function () {
    $this->artisan('app:create-owner', [
        'email' => 'OWNER@example.com',
        '--name' => 'Collection Owner',
    ])->expectsQuestion('Password (minimum 12 characters)', 'secure-password')
        ->expectsQuestion('Confirm password', 'secure-password')
        ->assertSuccessful();

    $owner = User::query()->sole();

    expect($owner->name)->toBe('Collection Owner')
        ->and($owner->email)->toBe('owner@example.com')
        ->and(Hash::check('secure-password', $owner->password))->toBeTrue();
});

test('a second owner cannot be provisioned', function () {
    $owner = User::factory()->create();

    $this->artisan('app:create-owner', [
        'email' => 'another-owner@example.com',
    ])->assertFailed();

    expect(User::query()->sole()->is($owner))->toBeTrue();
});
