<?php

namespace App\Exceptions;

use RuntimeException;

class SsrfViolationException extends RuntimeException
{
    public function __construct(string $reason, public readonly string $url)
    {
        parent::__construct($reason);
    }
}
