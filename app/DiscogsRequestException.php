<?php

namespace App;

use RuntimeException;

class DiscogsRequestException extends RuntimeException
{
    public function __construct(
        public readonly DiscogsFailure $failure,
        public readonly ?int $statusCode = null,
    ) {
        parent::__construct(match ($failure) {
            DiscogsFailure::Authentication => 'Discogs rejected the configured credentials.',
            DiscogsFailure::NotFound => 'The requested Discogs resource was not found.',
            DiscogsFailure::Transient => 'Discogs is temporarily unavailable.',
            DiscogsFailure::Unexpected => 'Discogs returned an unexpected response.',
        });
    }

    /** @return array{failure: string, status_code: int|null} */
    public function context(): array
    {
        return [
            'failure' => $this->failure->value,
            'status_code' => $this->statusCode,
        ];
    }
}
