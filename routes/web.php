<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\BrowseController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ReleaseRefreshController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/collection', [CollectionController::class, 'index'])->name('collection');
    Route::get('/collection/{release}', [CollectionController::class, 'show'])->name('collection.show');
    Route::get('/browse/{dimension?}', BrowseController::class)
        ->where('dimension', 'labels|artists|years|recently-added|recently-updated|videos')
        ->name('browse');
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
    Route::inertia('/statistics', 'Section', [
        'title' => 'Statistics',
        'description' => 'See the shape, strengths, and gaps in your collection.',
        'dataKind' => 'mixed',
    ])->name('statistics');
    Route::post('/releases/{release}/refresh', ReleaseRefreshController::class)
        ->name('releases.refresh');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
