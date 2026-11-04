<?php

namespace App\Services\VisualQa;

/**
 * Provider-independent browser inspection (PRD v1.5 BrowserService). Providers
 * capture a built, self-contained static page at desktop/tablet/mobile and
 * return the same result shape, so QA never depends on where Chromium runs.
 *
 * Security: providers only ever load artifacts REDO generated itself. Loading a
 * customer-supplied URL is a separate capability (capturesExternalUrls) that a
 * provider must opt into, and callers must pass the URL through SsrfGuard first.
 */
interface BrowserService
{
    public function id(): string;

    public function isAvailable(): bool;

    /** True only for isolated, remote providers that may be pointed at third-party URLs. */
    public function capturesExternalUrls(): bool;

    /**
     * @param  array|null  $expectations  Blueprint expectations (sections, allowed_text) enabling blueprint checks.
     * @return array<int, array{viewport: string, width: int, height: int, screenshot_path: ?string, load_error: ?string, console_errors: array, page_errors: array, failed_requests: array, has_horizontal_overflow: bool, image_count: int, broken_image_count: int, broken_image_srcs: array, blueprint: ?array, reduced_motion_ok: ?bool}>
     */
    public function capture(string $htmlPath, string $outputDir, ?array $expectations = null): array;
}
