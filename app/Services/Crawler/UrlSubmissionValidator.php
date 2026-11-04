<?php

namespace App\Services\Crawler;

use App\Exceptions\SsrfViolationException;
use App\Services\Crawler\Security\SsrfGuard;

/**
 * Entry-point validation for a submitted URL (FR-002). Performs the same
 * scheme/DNS/IP checks the crawler performs per-request, so an obviously
 * unsafe destination is rejected before a run is even created.
 */
class UrlSubmissionValidator
{
    public function __construct(
        private readonly UrlNormalizer $normalizer,
        private readonly SsrfGuard $ssrfGuard,
    ) {}

    /**
     * @throws SsrfViolationException
     */
    public function validateAndNormalize(string $url): string
    {
        $normalized = $this->normalizer->normalize($url);

        if ($normalized === null) {
            throw new SsrfViolationException('URL must be a valid http or https address.', $url);
        }

        $this->ssrfGuard->assertSafe($normalized);

        return $normalized;
    }
}
