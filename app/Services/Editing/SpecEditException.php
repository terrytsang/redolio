<?php

namespace App\Services\Editing;

use RuntimeException;

/** An edit that cannot be applied. `stale` means the page moved on (another tab/session saved first). */
class SpecEditException extends RuntimeException
{
    /** @param string[] $errors */
    public function __construct(string $message, public readonly array $errors = [], public readonly bool $stale = false)
    {
        parent::__construct($message);
    }
}
