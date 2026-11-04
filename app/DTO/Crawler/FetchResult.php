<?php

namespace App\DTO\Crawler;

class FetchResult
{
    public function __construct(
        public readonly string $finalUrl,
        public readonly int $httpStatus,
        public readonly string $body,
        public readonly string $contentType,
        /** @var string[] */
        public readonly array $warnings = [],
    ) {}
}
