<?php

namespace App\Providers;

use App\DiscogsClient;
use App\DiscogsGateway;
use App\DiscogsRateLimiter;
use Carbon\CarbonImmutable;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            DiscogsGateway::class,
            function (): DiscogsClient {
                $limiterStore = $this->app->make(CacheManager::class)
                    ->store(Config::string('cache.limiter'))
                    ->getStore();

                if (! $limiterStore instanceof LockProvider) {
                    throw new \LogicException('The Discogs rate limiter cache store must support atomic locks.');
                }

                return new DiscogsClient(
                    baseUrl: Config::string('services.discogs.base_url'),
                    userAgent: Config::string('services.discogs.user_agent'),
                    connectTimeout: Config::integer('services.discogs.connect_timeout'),
                    timeout: Config::integer('services.discogs.timeout'),
                    rateLimiter: new DiscogsRateLimiter(
                        limiter: $this->app->make(RateLimiter::class),
                        locks: $limiterStore,
                        requestsPerMinute: Config::integer('services.discogs.requests_per_minute'),
                    ),
                    retryAttempts: Config::integer('services.discogs.retry_attempts'),
                    retryBaseDelay: Config::integer('services.discogs.retry_base_delay'),
                    retryMaxDelay: Config::integer('services.discogs.retry_max_delay'),
                );
            },
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
