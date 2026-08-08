<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PersonalReleaseMetadataController;
use App\Http\Controllers\ReleaseRefreshController;
use App\Http\Controllers\ReleaseVocabularyController;
use App\Http\Controllers\RiddimController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/collection', [CollectionController::class, 'index'])->name('collection');
    Route::get('/collection/{release}', [CollectionController::class, 'show'])->name('collection.show');
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
    Route::post('/releases/{release}/refresh', ReleaseRefreshController::class)
        ->name('releases.refresh');
    Route::patch('/releases/{release}/personal-metadata', PersonalReleaseMetadataController::class)
        ->name('releases.personal_metadata.update');
    Route::put('/releases/{release}/vocabulary', [ReleaseVocabularyController::class, 'update'])
        ->name('releases.release_vocabulary.update');
    Route::post('/vocabularies/tags', [TagController::class, 'store'])
        ->name('vocabularies.tags.store');
    Route::patch('/vocabularies/tags/{tag}', [TagController::class, 'update'])
        ->name('vocabularies.tags.update');
    Route::delete('/vocabularies/tags/{tag}', [TagController::class, 'destroy'])
        ->name('vocabularies.tags.destroy');
    Route::post('/vocabularies/riddims', [RiddimController::class, 'store'])
        ->name('vocabularies.riddims.store');
    Route::patch('/vocabularies/riddims/{riddim}', [RiddimController::class, 'update'])
        ->name('vocabularies.riddims.update');
    Route::delete('/vocabularies/riddims/{riddim}', [RiddimController::class, 'destroy'])
        ->name('vocabularies.riddims.destroy');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
