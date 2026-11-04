<?php

namespace App\DTO\Crawler;

class EvidenceItemDto
{
    public function __construct(
        public readonly string $evidenceKey,
        public readonly string $pageUrl,
        public readonly string $evidenceType,
        public readonly string $label,
        public readonly string $excerpt,
        public readonly ?array $locator = null,
    ) {}
}
