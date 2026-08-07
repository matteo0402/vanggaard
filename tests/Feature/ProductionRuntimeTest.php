<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\Fixtures\Jobs\MarkRuntimeQueueAsProcessed;

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! filter_var($_SERVER['PRODUCTION_RUNTIME_TESTS'] ?? false, FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Production runtime services are not enabled.');
    }

    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and(config('cache.default'))->toBe('redis')
        ->and(config('cache.limiter'))->toBe('redis')
        ->and(config('queue.default'))->toBe('redis');
});

test('models persist in MySQL', function () {
    $user = User::factory()->create();

    $this->assertModelExists($user);
});

test('Redis provides cache and distributed locks', function () {
    $key = 'runtime-test:'.Str::uuid();
    $lockName = $key.':lock';
    $lock = Cache::lock($lockName, 10);

    try {
        Cache::put($key, 'available', 60);

        expect(Cache::get($key))->toBe('available')
            ->and($lock->get())->toBeTrue()
            ->and(Cache::lock($lockName, 10)->get())->toBeFalse();
    } finally {
        $lock->release();
        Cache::forget($key);
    }
});

test('Redis provides rate limiting', function () {
    $key = 'runtime-test:'.Str::uuid();

    try {
        expect(RateLimiter::attempt($key, 1, fn () => true, 60))->toBeTrue()
            ->and(RateLimiter::attempt($key, 1, fn () => true, 60))->toBeFalse();
    } finally {
        RateLimiter::clear($key);
    }
});

test('a Redis worker processes queued jobs', function () {
    $key = 'runtime-test:'.Str::uuid();
    $queue = 'runtime-test-'.Str::uuid();

    try {
        MarkRuntimeQueueAsProcessed::dispatch($key)
            ->onConnection('redis')
            ->onQueue($queue);

        expect(Queue::connection('redis')->size($queue))->toBe(1);

        $this->artisan('queue:work', [
            'connection' => 'redis',
            '--queue' => $queue,
            '--once' => true,
            '--sleep' => 0,
            '--tries' => 1,
            '--timeout' => 10,
        ])->assertSuccessful();

        expect(Cache::get($key))->toBeTrue()
            ->and(Queue::connection('redis')->size($queue))->toBe(0);
    } finally {
        Cache::forget($key);
    }
});
