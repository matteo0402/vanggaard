<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('home'))
        ->assertRedirectToRoute('login');
});

test('guests can view the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

test('the owner can log in', function () {
    $owner = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $owner->email,
        'password' => 'password',
    ])->assertRedirectToRoute('home');

    $this->assertAuthenticatedAs($owner);
});

test('invalid credentials do not authenticate', function () {
    $owner = User::factory()->create();

    $this->from(route('login'))->post(route('login.store'), [
        'email' => $owner->email,
        'password' => 'incorrect-password',
    ])->assertRedirectToRoute('login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('repeated invalid credentials are rate limited', function () {
    $owner = User::factory()->create();
    Event::fake([Lockout::class]);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), [
            'email' => $owner->email,
            'password' => 'incorrect-password',
        ]);
    }

    $this->post(route('login.store'), [
        'email' => $owner->email,
        'password' => 'incorrect-password',
    ])->assertSessionHasErrors('email');

    Event::assertDispatched(Lockout::class);
    $this->assertGuest();
});

test('authenticated users can access collection data', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});

test('authenticated users cannot view the login page', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->get(route('login'))
        ->assertRedirectToRoute('home');
});

test('the owner can log out', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('logout'))
        ->assertRedirectToRoute('login');

    $this->assertGuest();
});

test('public registration is unavailable', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
});
