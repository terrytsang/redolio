<?php

namespace App\Services\MetaApi\Exceptions;

use RuntimeException;

class MetaApiException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'meta_api_error', ?\Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
