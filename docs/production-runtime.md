# Production runtime

This application uses MySQL for durable data and Redis for queues, cache, distributed locks, and rate limiting.

## Services and extensions

Production requires a supported MySQL server, a supported Redis server, and PHP with the `pdo_mysql` and `redis` extensions. Redis must be shared by every application, worker, and scheduler instance so locks and rate limits remain deployment-wide.

The deployment platform must inject secrets and connection details through environment variables. Do not commit production environment files or credentials.

Required application settings:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=<generated-secret>

DB_CONNECTION=mysql
DB_URL=<mysql-connection-url>

QUEUE_CONNECTION=redis
CACHE_STORE=redis
CACHE_LIMITER=redis
REDIS_URL=<redis-connection-url>
REDIS_QUEUE_RETRY_AFTER=90
REDIS_QUEUE_BLOCK_FOR=5
```

Connection URLs may be replaced by the corresponding host, port, database, username, and password variables supported by `config/database.php`. Set application-specific `CACHE_PREFIX` and `REDIS_PREFIX` values when Redis is shared with another deployment.

## Deployment

A release must install production dependencies, build frontend assets, migrate MySQL, cache Laravel metadata, and restart long-lived workers:

```shell
composer install --no-dev --classmap-authoritative
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

The deployment platform must provide `APP_KEY` before Laravel commands run. Use zero-downtime migration practices once schema changes can conflict with the currently running release.

Do not use `composer setup` as a production release command. It installs development dependencies, creates a local environment file, generates a new application key, and performs developer-oriented setup.

## Queue worker

Run at least one continuously supervised Redis worker:

```shell
php artisan queue:work redis --sleep=1 --tries=3 --timeout=60 --max-time=3600
```

The process manager must restart failed or gracefully exited workers, capture their logs, and allow at least the longest job duration during shutdown. `REDIS_QUEUE_RETRY_AFTER` must remain greater than the worker timeout and every job-specific timeout to prevent duplicate processing.

Workers are long-lived and must be restarted after each deployment or configuration change. Monitor failed jobs, worker availability, queue depth, and the age of the oldest pending job.

## Scheduler

Invoke Laravel's scheduler once per minute on exactly one scheduler process:

```cron
* * * * * cd /path/to/vanggaard && php artisan schedule:run >> /dev/null 2>&1
```

A supervised `php artisan schedule:work` process is an acceptable alternative when the deployment platform supports long-lived scheduler processes. Scheduled work that may outlive its interval must use `withoutOverlapping()`, and work running on multiple application nodes must use `onOneServer()`.

Monitor the scheduler process independently from the web application and queue workers. The `/up` endpoint confirms only that the web process can serve Laravel requests; it does not prove that MySQL, Redis, workers, or the scheduler are healthy.
