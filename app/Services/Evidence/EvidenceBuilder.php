<?php

namespace App\Services\Evidence;

use App\DTO\Crawler\EvidenceItemDto;
use App\DTO\Crawler\ExtractedPage;

/**
 * Turns extracted page content into stable, traceable evidence items.
 * Evidence keys are deterministic per page index so the same page content
 * yields the same key within a given crawl run.
 */
class EvidenceBuilder
{
    private const MAX_EXCERPT_LENGTH = 500;

    /**
     * @param  ExtractedPage[]  $pages
     * @return EvidenceItemDto[]
     */
    public function build(array $pages): array
    {
        $evidence = [];

        foreach (array_values($pages) as $pageIndex => $page) {
            $prefix = "p{$pageIndex}";

            if ($page->title) {
                $evidence[] = new EvidenceItemDto(
                    "{$prefix}-title", $page->url, 'title', 'Page title', $this->truncate($page->title)
                );
            }

            if ($page->metaDescription) {
                $evidence[] = new EvidenceItemDto(
                    "{$prefix}-meta", $page->url, 'meta', 'Meta description', $this->truncate($page->metaDescription)
                );
            }

            foreach ($page->headings as $i => $heading) {
                $evidence[] = new EvidenceItemDto(
                    "{$prefix}-h{$i}", $page->url, 'heading', "Heading #{$i}", $this->truncate($heading),
                    ['index' => $i]
                );
            }

            foreach ($this->paragraphs($page->visibleText) as $i => $paragraph) {
                $evidence[] = new EvidenceItemDto(
                    "{$prefix}-para{$i}", $page->url, 'paragraph', "Body text excerpt #{$i}", $this->truncate($paragraph),
                    ['index' => $i]
                );
            }

            foreach (array_values($page->ctas) as $i => $cta) {
                $evidence[] = new EvidenceItemDto(
                    "{$prefix}-cta{$i}", $page->url, 'cta', "CTA #{$i}", $this->truncate($cta),
                    ['index' => $i]
                );
            }

            foreach ($page->forms as $i => $form) {
                $label = 'Form with fields: '.implode(', ', $form['input_types']);
                $evidence[] = new EvidenceItemDto(
                    "{$prefix}-form{$i}", $page->url, 'form', "Form #{$i}", $this->truncate($label),
                    ['index' => $i]
                );
            }

            foreach (array_values($page->imageAlt) as $i => $alt) {
                $evidence[] = new EvidenceItemDto(
                    "{$prefix}-img{$i}", $page->url, 'image_alt', "Image alt text #{$i}", $this->truncate($alt),
                    ['index' => $i]
                );
            }

            foreach (array_values($page->contactDetails) as $i => $contact) {
                $evidence[] = new EvidenceItemDto(
                    "{$prefix}-contact{$i}", $page->url, 'contact', "Contact detail #{$i}", $this->truncate($contact),
                    ['index' => $i]
                );
            }

            foreach (array_values($page->warnings) as $i => $warning) {
                $evidence[] = new EvidenceItemDto(
                    "{$prefix}-warn{$i}", $page->url, 'crawl_warning', "Crawl warning #{$i}", $this->truncate($warning),
                    ['index' => $i]
                );
            }
        }

        return $evidence;
    }

    /**
     * @return string[]
     */
    private function paragraphs(string $visibleText): array
    {
        if (trim($visibleText) === '') {
            return [];
        }

        $chunks = preg_split('/(?<=[.!?])\s+(?=[A-Z])/u', $visibleText) ?: [$visibleText];
        $chunks = array_values(array_filter(array_map('trim', $chunks), fn ($c) => $c !== ''));

        // Cap paragraph evidence volume per page to keep evidence packs bounded.
        return array_slice($chunks, 0, 20);
    }

    private function truncate(string $text): string
    {
        $text = trim($text);

        return mb_strlen($text) > self::MAX_EXCERPT_LENGTH
            ? mb_substr($text, 0, self::MAX_EXCERPT_LENGTH).'…'
            : $text;
    }
}
