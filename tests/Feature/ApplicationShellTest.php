<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('application shell destinations require authentication', function (string $route) {
    $this->get(route($route))->assertRedirectToRoute('login');
})->with(['home', 'collection', 'assistant', 'boxes', 'labels', 'statistics']);

test('the owner can open application shell destinations', function (
    string $route,
    string $title,
    string $dataKind,
) {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Section')
            ->where('title', $title)
            ->where('dataKind', $dataKind)
            ->has('description'));
})->with([
    'DJ assistant' => ['assistant', 'DJ Assistant', 'personal'],
    'boxes' => ['boxes', 'Boxes', 'personal'],
    'labels' => ['labels', 'Labels', 'discogs'],
    'statistics' => ['statistics', 'Statistics', 'mixed'],
]);
