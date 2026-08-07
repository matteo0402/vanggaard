<?php

use App\DiscogsFailure;
use App\DiscogsGateway;
use App\DiscogsRequestException;
use App\Models\DiscogsAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Config::set('services.discogs', [
        'base_url' => 'https://api.discogs.test',
        'user_agent' => 'Vanggaard Test/1.0 +https://vanggaard.test',
        'consumer_key' => '',
        'consumer_secret' => '',
        'connect_timeout' => 2,
        'timeout' => 5,
        'requests_per_minute' => 50,
        'retry_attempts' => 3,
        'retry_base_delay' => 100,
        'retry_max_delay' => 1000,
    ]);
    Config::set('cache.limiter', 'array');

    Http::preventStrayRequests();
    Sleep::fake(syncWithCarbon: true);
});

function captureDiscogsException(Closure $callback): DiscogsRequestException
{
    try {
        $callback();
    } catch (DiscogsRequestException $exception) {
        return $exception;
    }

    throw new LogicException('Expected the Discogs request to fail.');
}

test('identity requests authenticate with required Discogs headers', function () {
    $account = DiscogsAccount::factory()->create(['personal_access_token' => 'secret-token']);

    Http::fake([
        'https://api.discogs.test/oauth/identity' => Http::response(['username' => $account->username]),
    ]);

    $identity = app(DiscogsGateway::class)->identity($account);

    expect($identity)->toBe(['username' => $account->username])
        ->and($account->toArray())->not->toHaveKey('personal_access_token');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://api.discogs.test/oauth/identity'
        && $request->hasHeader('Accept', 'application/vnd.discogs.v2.discogs+json')
        && $request->hasHeader('Authorization', 'Discogs token=secret-token')
        && $request->hasHeader('User-Agent', 'Vanggaard Test/1.0 +https://vanggaard.test'));
    Http::assertSentCount(1);
});

test('public collection requests can authenticate with consumer credentials', function () {
    Config::set('services.discogs.consumer_key', 'consumer-key');
    Config::set('services.discogs.consumer_secret', 'consumer-secret');

    $account = DiscogsAccount::factory()->create([
        'username' => 'selector',
        'personal_access_token' => null,
    ]);

    Http::fake([
        'https://api.discogs.test/users/selector/collection/folders' => Http::response([
            'folders' => [['id' => 0, 'name' => 'All']],
        ]),
    ]);

    app(DiscogsGateway::class)->folders($account);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader(
        'Authorization',
        'Discogs key=consumer-key, secret=consumer-secret',
    ));
});

test('folders requests use the account collection endpoint', function () {
    $account = DiscogsAccount::factory()->create(['username' => 'selector']);

    Http::fake([
        'https://api.discogs.test/users/selector/collection/folders' => Http::response([
            'folders' => [['id' => 0, 'name' => 'All']],
        ]),
    ]);

    $folders = app(DiscogsGateway::class)->folders($account);

    expect($folders['folders'])->toBe([['id' => 0, 'name' => 'All']]);
    Http::assertSentCount(1);
});

test('collection page requests include folder and pagination parameters', function () {
    $account = DiscogsAccount::factory()->create(['username' => 'selector']);

    Http::fake([
        'https://api.discogs.test/users/selector/collection/folders/12/releases?page=2&per_page=50' => Http::response([
            'pagination' => ['page' => 2, 'pages' => 4],
            'releases' => [['instance_id' => 99]],
        ]),
    ]);

    $page = app(DiscogsGateway::class)->collectionPage($account, folderId: 12, page: 2, perPage: 50);

    expect($page['pagination'])->toBe(['page' => 2, 'pages' => 4])
        ->and($page['releases'])->toBe([['instance_id' => 99]]);
    Http::assertSentCount(1);
});

test('release instance requests use the account instance endpoint', function () {
    $account = DiscogsAccount::factory()->create(['username' => 'selector']);

    Http::fake([
        'https://api.discogs.test/users/selector/collection/releases/42/instances/99' => Http::response([
            'instance_id' => 99,
            'folder_id' => 12,
        ]),
    ]);

    $instance = app(DiscogsGateway::class)->releaseInstance($account, releaseId: 42, instanceId: 99);

    expect($instance)->toBe(['instance_id' => 99, 'folder_id' => 12]);
    Http::assertSentCount(1);
});

test('full release requests use the database release endpoint', function () {
    $account = DiscogsAccount::factory()->create();

    Http::fake([
        'https://api.discogs.test/releases/42' => Http::response([
            'id' => 42,
            'title' => 'King Tubby Meets Rockers Uptown',
        ]),
    ]);

    $release = app(DiscogsGateway::class)->release($account, 42);

    expect($release)->toBe([
        'id' => 42,
        'title' => 'King Tubby Meets Rockers Uptown',
    ]);
    Http::assertSentCount(1);
});

test('response failures are classified without exposing credentials', function (int $status, DiscogsFailure $failure) {
    $token = 'never-log-this-token';
    $account = DiscogsAccount::factory()->create(['personal_access_token' => $token]);

    Http::fake([
        'https://api.discogs.test/*' => Http::response(['message' => 'Discogs error'], $status),
    ]);

    $exception = captureDiscogsException(
        fn () => app(DiscogsGateway::class)->identity($account),
    );

    expect($exception->failure)->toBe($failure)
        ->and($exception->statusCode)->toBe($status)
        ->and($exception->getMessage())->not->toContain($token)
        ->and($exception->context())->not->toContain($token);
})->with([
    'unauthorized' => [401, DiscogsFailure::Authentication],
    'forbidden' => [403, DiscogsFailure::Authentication],
    'not found' => [404, DiscogsFailure::NotFound],
    'rate limited' => [429, DiscogsFailure::Transient],
    'server failure' => [503, DiscogsFailure::Transient],
    'validation failure' => [422, DiscogsFailure::Unexpected],
]);

test('connection failures are transient and sanitized', function () {
    $token = 'never-log-this-token';
    $account = DiscogsAccount::factory()->create(['personal_access_token' => $token]);

    Http::fake([
        'https://api.discogs.test/*' => Http::failedConnection(),
    ]);

    $exception = captureDiscogsException(
        fn () => app(DiscogsGateway::class)->identity($account),
    );

    expect($exception->failure)->toBe(DiscogsFailure::Transient)
        ->and($exception->statusCode)->toBeNull()
        ->and($exception->getMessage())->not->toContain($token)
        ->and($exception->context())->not->toContain($token);
});

test('all client instances share one request budget', function () {
    Config::set('services.discogs.requests_per_minute', 1);

    $firstAccount = DiscogsAccount::factory()->create(['username' => 'first-selector']);
    $secondAccount = DiscogsAccount::factory()->create(['username' => 'second-selector']);

    Http::fake([
        'https://api.discogs.test/oauth/identity' => Http::response(['username' => $firstAccount->username]),
        'https://api.discogs.test/users/second-selector/collection/folders' => Http::response(['folders' => []]),
    ]);

    app(DiscogsGateway::class)->identity($firstAccount);
    app()->forgetInstance(DiscogsGateway::class);
    app(DiscogsGateway::class)->folders($secondAccount);

    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
    Sleep::assertSlept(fn ($duration): bool => $duration->totalSeconds === 60.0);
});

test('Discogs response headers can exhaust the shared budget early', function () {
    Config::set('services.discogs.requests_per_minute', 2);

    $account = DiscogsAccount::factory()->create();

    Http::fakeSequence()
        ->push(['username' => $account->username], 200, [
            'X-Discogs-Ratelimit' => '3',
            'X-Discogs-Ratelimit-Remaining' => '1',
        ])
        ->push(['username' => $account->username]);

    app(DiscogsGateway::class)->identity($account);
    app(DiscogsGateway::class)->identity($account);

    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});

test('transient responses are retried', function (int $status) {
    $account = DiscogsAccount::factory()->create();

    Http::fakeSequence()
        ->push(['message' => 'Try again'], $status)
        ->push(['id' => 42]);

    expect(app(DiscogsGateway::class)->release($account, 42))->toBe(['id' => 42]);

    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
})->with([429, 500, 502, 503, 504]);

test('connection failures are retried', function () {
    $account = DiscogsAccount::factory()->create();

    Http::fakeSequence()
        ->pushFailedConnection()
        ->push(['id' => 42]);

    expect(app(DiscogsGateway::class)->release($account, 42))->toBe(['id' => 42]);

    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
});

test('normal client failures are not retried', function (int $status) {
    $account = DiscogsAccount::factory()->create();

    Http::fake([
        'https://api.discogs.test/*' => Http::response(['message' => 'Do not retry'], $status),
    ]);

    captureDiscogsException(fn () => app(DiscogsGateway::class)->identity($account));

    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
})->with([400, 401, 403, 404, 422, 501]);

test('retry after headers take precedence over exponential backoff', function () {
    $account = DiscogsAccount::factory()->create();

    Http::fakeSequence()
        ->push(['message' => 'Slow down'], 429, ['Retry-After' => '3'])
        ->push(['id' => 42]);

    app(DiscogsGateway::class)->release($account, 42);

    Sleep::assertSlept(
        fn ($duration): bool => $duration->totalMilliseconds >= 3000.0
            && $duration->totalMilliseconds <= 3100.0,
    );
});

test('transient retry delays include bounded jitter', function () {
    $account = DiscogsAccount::factory()->create();

    Http::fakeSequence()
        ->push(['message' => 'Try again'], 503)
        ->push(['id' => 42]);

    app(DiscogsGateway::class)->release($account, 42);

    Sleep::assertSlept(
        fn ($duration): bool => $duration->totalMilliseconds >= 100.0
            && $duration->totalMilliseconds <= 200.0,
    );
});

test('successful non-object responses are rejected as unexpected', function () {
    $account = DiscogsAccount::factory()->create();

    Http::fake([
        'https://api.discogs.test/*' => Http::response('not-json'),
    ]);

    $exception = captureDiscogsException(
        fn () => app(DiscogsGateway::class)->identity($account),
    );

    expect($exception->failure)->toBe(DiscogsFailure::Unexpected)
        ->and($exception->statusCode)->toBe(200);
});

test('unfaked endpoints cannot make stray requests', function () {
    $account = DiscogsAccount::factory()->create();

    expect(fn () => app(DiscogsGateway::class)->identity($account))
        ->toThrow(RuntimeException::class);

    Http::assertNothingSent();
});
