<?php

use App\Jobs\QueueStaleDiscogsReleaseRefreshes;
use App\Jobs\StartDiscogsCollectionReconciliations;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new StartDiscogsCollectionReconciliations)
    ->name('discogs:reconcile-collections')
    ->everyFourHours()
    ->withoutOverlapping(240)
    ->onOneServer();

Schedule::job(new QueueStaleDiscogsReleaseRefreshes)
    ->name('discogs:refresh-stale-releases')
    ->hourly()
    ->withoutOverlapping(60)
    ->onOneServer();
