<?php

use App\Http\Controllers\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::inertia('/', 'Welcome')->name('home');
    Route::inertia('/collection', 'Section', [
        'title' => 'Collection',
        'description' => 'Browse the records and release details in your local catalog.',
        'dataKind' => 'mixed',
    ])->name('collection');
    Route::inertia('/dj-assistant', 'Section', [
        'title' => 'DJ Assistant',
        'description' => 'Build sets from records you own and the context you add.',
        'dataKind' => 'personal',
    ])->name('assistant');
    Route::inertia('/boxes', 'Section', [
        'title' => 'Boxes',
        'description' => 'Find records by their real-world room, shelf, and box.',
        'dataKind' => 'personal',
    ])->name('boxes');
    Route::inertia('/labels', 'Section', [
        'title' => 'Labels',
        'description' => 'Explore the labels represented across your collection.',
        'dataKind' => 'discogs',
    ])->name('labels');
    Route::inertia('/statistics', 'Section', [
        'title' => 'Statistics',
        'description' => 'See the shape, strengths, and gaps in your collection.',
        'dataKind' => 'mixed',
    ])->name('statistics');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
