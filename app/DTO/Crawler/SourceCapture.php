<?php

namespace App\DTO\Crawler;

/** Result of rendering a website in a real browser. */
class SourceCapture
{
    /**
     * @param  array<int, array{url: string, status: int, html: string, cleared: bool}>  $pages
     * @param  string[]  $errors
     * @param  string[]  $blocked  hosts the egress proxy refused (internal addresses etc.)
     */
    public function __construct(
        public readonly array $pages,
        public readonly ?string $screenshotUrl,
        public readonly bool $challengeCleared,
        public readonly array $errors = [],
        public readonly array $blocked = [],
    ) {}
}
