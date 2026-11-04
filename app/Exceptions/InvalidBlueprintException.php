<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidBlueprintException extends RuntimeException
{
    /** @param string[] $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Invalid blueprint or content: '.implode('; ', $errors));
    }
}
