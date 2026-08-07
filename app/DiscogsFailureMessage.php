<?php

namespace App;

use Throwable;

class DiscogsFailureMessage
{
    public static function collection(?Throwable $exception): string
    {
        return self::message(
            $exception,
            'Collection synchronization failed. It will resume automatically.',
        );
    }

    public static function release(?Throwable $exception): string
    {
        return self::message(
            $exception,
            'Release refresh failed. It will be retried later.',
        );
    }

    private static function message(?Throwable $exception, string $fallback): string
    {
        if ($exception instanceof DiscogsRequestException) {
            return $exception->getMessage();
        }

        return $fallback;
    }
}
