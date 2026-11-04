<?php

namespace App\DTO\Crawler;

class ExtractedPage
{
    public function __construct(
        public readonly string $url,
        public readonly int $httpStatus,
        public readonly ?string $title,
        public readonly ?string $metaDescription,
        public readonly ?string $canonicalUrl,
        /** @var string[] */
        public readonly array $headings,
        public readonly string $visibleText,
        /** @var array<int, array{href: string, label: string}> */
        public readonly array $links,
        /** @var string[] */
        public readonly array $ctas,
        /** @var array<int, array{labels: string[], input_types: string[], action_origin: string|null}> */
        public readonly array $forms,
        /** @var string[] */
        public readonly array $imageAlt,
        /** @var string[] */
        public readonly array $contactDetails,
        public readonly string $fetchStatus,
        /** @var string[] */
        public readonly array $warnings = [],
        /**
         * Raw image candidates for asset inventory (Phase A). The crawler
         * only observes and describes — it never decides which of these
         * is a logo/hero/portrait; that classification happens in
         * WebsiteAssetInventoryService, downstream of this DTO.
         *
         * @var array<int, array{src: string, alt: string|null, width: int|null, height: int|null, dom_context: string, nearby_heading: string|null}>
         */
        public readonly array $imageCandidates = [],
        public readonly ?string $faviconUrl = null,
        public readonly ?string $themeColor = null,
    ) {}
}
