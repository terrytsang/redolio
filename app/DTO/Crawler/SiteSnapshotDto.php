<?php

namespace App\DTO\Crawler;

class SiteSnapshotDto
{
    public function __construct(
        public readonly string $snapshotHash,
        public readonly string $homepageUrl,
        /** @var ExtractedPage[] */
        public readonly array $pages,
        /** @var EvidenceItemDto[] */
        public readonly array $evidence,
        /** @var string[] */
        public readonly array $warnings,
    ) {}

    public function toSnapshotJson(): array
    {
        return [
            'snapshot_hash' => $this->snapshotHash,
            'homepage_url' => $this->homepageUrl,
            'pages' => array_map(fn (ExtractedPage $page) => [
                'page_url' => $page->url,
                'status' => $page->fetchStatus,
                'title' => $page->title,
                'meta_description' => $page->metaDescription,
                'canonical_url' => $page->canonicalUrl,
                'headings' => $page->headings,
                'visible_text' => $page->visibleText,
                'links' => array_map(fn ($l) => $l['href'], $page->links),
                'ctas' => $page->ctas,
                'forms' => $page->forms,
                'image_alt' => $page->imageAlt,
                'evidence_ids' => $this->evidenceIdsForPage($page->url),
            ], $this->pages),
            'warnings' => $this->warnings,
            'screenshot_path' => null,
        ];
    }

    /**
     * @return string[]
     */
    public function evidenceIdsForPage(string $pageUrl): array
    {
        return array_values(array_map(
            fn (EvidenceItemDto $e) => $e->evidenceKey,
            array_filter($this->evidence, fn (EvidenceItemDto $e) => $e->pageUrl === $pageUrl)
        ));
    }

    /**
     * @return string[]
     */
    public function allEvidenceKeys(): array
    {
        return array_map(fn (EvidenceItemDto $e) => $e->evidenceKey, $this->evidence);
    }
}
