<?php

namespace App\Services\Crawler;

use App\DTO\Crawler\ExtractedPage;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;

/**
 * Extracts normalized, evidence-ready content from raw HTML using
 * Symfony DomCrawler (PHP DOMDocument under the hood). Strips script,
 * style, noscript and template content before producing visible text,
 * since that text is later treated as untrusted evidence sent to the
 * model, never as instructions.
 */
class HtmlPageExtractor
{
    public function extract(string $url, int $httpStatus, string $html): ExtractedPage
    {
        $warnings = [];

        if (trim($html) === '') {
            return new ExtractedPage(
                url: $url,
                httpStatus: $httpStatus,
                title: null,
                metaDescription: null,
                canonicalUrl: null,
                headings: [],
                visibleText: '',
                links: [],
                ctas: [],
                forms: [],
                imageAlt: [],
                contactDetails: [],
                fetchStatus: 'failed',
                warnings: ['Empty response body.'],
            );
        }

        $crawler = new DomCrawler($html, $url);

        $this->stripNonContentNodes($crawler);

        $title = $this->firstText($crawler, 'title');
        $metaDescription = $this->metaContent($crawler, 'description');
        $canonical = $this->linkHref($crawler, 'canonical');

        $headings = [];
        $crawler->filter('h1, h2, h3, h4, h5, h6')->each(function (DomCrawler $node) use (&$headings) {
            $text = trim($node->text(''));
            if ($text !== '') {
                $headings[] = $text;
            }
        });

        $visibleText = $this->extractVisibleText($crawler);

        $maxChars = (int) config('crawler.max_total_text_characters', 30000);
        if (strlen($visibleText) > $maxChars) {
            $visibleText = substr($visibleText, 0, $maxChars);
            $warnings[] = 'Visible text truncated to configured character limit.';
        }

        $links = $this->extractLinks($crawler);
        $ctas = $this->extractCtas($crawler);
        $forms = $this->extractForms($crawler);
        $imageAlt = $this->extractImageAlt($crawler);
        $contactDetails = $this->extractContactDetails($crawler, $visibleText);
        $imageCandidates = $this->extractImageCandidates($crawler);
        $faviconUrl = $this->extractFaviconUrl($crawler);
        $themeColor = $this->metaContent($crawler, 'theme-color');

        if (empty($visibleText) && empty($headings)) {
            $warnings[] = 'Page produced little or no extractable text; it may rely on client-side rendering.';
        }

        if ($this->looksLikeBotChallenge($html)) {
            $warnings[] = 'bot_challenge_detected';
        }

        return new ExtractedPage(
            url: $url,
            httpStatus: $httpStatus,
            title: $title,
            metaDescription: $metaDescription,
            canonicalUrl: $canonical,
            headings: $headings,
            visibleText: $visibleText,
            links: $links,
            ctas: $ctas,
            forms: $forms,
            imageAlt: $imageAlt,
            contactDetails: $contactDetails,
            fetchStatus: $httpStatus >= 200 && $httpStatus < 400 ? 'success' : 'partial',
            warnings: $warnings,
            imageCandidates: $imageCandidates,
            faviconUrl: $faviconUrl,
            themeColor: $themeColor,
        );
    }

    /** Markers of bot-protection interstitials (proof-of-work, Cloudflare, Sucuri, Imperva, DataDome, PerimeterX …). */
    private function looksLikeBotChallenge(string $html): bool
    {
        return (bool) preg_match('/onehop-proof-of-work|cf-chl-|cf_chl_|challenge-platform|__cf_chl|sucuri_cloudproxy|_Incapsula_Resource|captcha-delivery\.com|px-captcha|datadome|ddos-guard|\/cdn-cgi\/challenge/i', substr($html, 0, 60000));
    }

    private function stripNonContentNodes(DomCrawler $crawler): void
    {
        foreach ($crawler->filter('script, style, noscript, template, svg')->getIterator() as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function firstText(DomCrawler $crawler, string $selector): ?string
    {
        $nodes = $crawler->filter($selector);

        if ($nodes->count() === 0) {
            return null;
        }

        $text = trim($nodes->first()->text(''));

        return $text === '' ? null : $text;
    }

    private function metaContent(DomCrawler $crawler, string $name): ?string
    {
        $nodes = $crawler->filter("meta[name=\"{$name}\"]");

        if ($nodes->count() === 0) {
            return null;
        }

        $content = trim((string) $nodes->first()->attr('content'));

        return $content === '' ? null : $content;
    }

    private function linkHref(DomCrawler $crawler, string $rel): ?string
    {
        $nodes = $crawler->filter("link[rel=\"{$rel}\"]");

        if ($nodes->count() === 0) {
            return null;
        }

        $href = trim((string) $nodes->first()->attr('href'));

        return $href === '' ? null : $href;
    }

    private function extractVisibleText(DomCrawler $crawler): string
    {
        $bodyNodes = $crawler->filter('body');

        if ($bodyNodes->count() === 0) {
            return '';
        }

        $text = $bodyNodes->first()->text('');
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }

    /**
     * @return array<int, array{href: string, label: string}>
     */
    private function extractLinks(DomCrawler $crawler): array
    {
        $links = [];
        $normalizer = new UrlNormalizer;

        $crawler->filter('a[href]')->each(function (DomCrawler $node) use (&$links, $normalizer, $crawler) {
            $href = trim((string) $node->attr('href'));
            $label = trim($node->text(''));

            if ($href === '') {
                return;
            }

            $resolved = $normalizer->resolve($crawler->getUri() ?? '', $href);

            if ($resolved !== null) {
                $links[] = ['href' => $resolved, 'label' => $label];
            }
        });

        return $links;
    }

    /**
     * @return string[]
     */
    private function extractCtas(DomCrawler $crawler): array
    {
        $ctas = [];

        $crawler->filter('a, button, input[type="submit"], input[type="button"]')->each(function (DomCrawler $node) use (&$ctas) {
            $tag = $node->nodeName();
            $label = $tag === 'input'
                ? trim((string) $node->attr('value'))
                : trim($node->text(''));

            if ($label !== '') {
                $ctas[] = $label;
            }
        });

        return array_values(array_unique($ctas));
    }

    /**
     * @return array<int, array{labels: string[], input_types: string[], action_origin: string|null}>
     */
    private function extractForms(DomCrawler $crawler): array
    {
        $forms = [];

        $crawler->filter('form')->each(function (DomCrawler $node) use (&$forms, $crawler) {
            $labels = [];
            $node->filter('label')->each(function (DomCrawler $label) use (&$labels) {
                $text = trim($label->text(''));
                if ($text !== '') {
                    $labels[] = $text;
                }
            });

            $inputTypes = [];
            $node->filter('input, select, textarea')->each(function (DomCrawler $input) use (&$inputTypes) {
                $type = $input->nodeName() === 'input'
                    ? ((string) $input->attr('type') ?: 'text')
                    : $input->nodeName();
                $inputTypes[] = $type;
            });

            $action = trim((string) $node->attr('action'));
            $actionOrigin = null;

            if ($action !== '') {
                $resolved = (new UrlNormalizer)->resolve($crawler->getUri() ?? '', $action);
                if ($resolved !== null) {
                    $parts = parse_url($resolved);
                    $actionOrigin = isset($parts['host']) ? ($parts['scheme'].'://'.$parts['host']) : null;
                }
            }

            $forms[] = [
                'labels' => $labels,
                'input_types' => array_values(array_unique($inputTypes)),
                'action_origin' => $actionOrigin,
            ];
        });

        return $forms;
    }

    /**
     * @return string[]
     */
    private function extractImageAlt(DomCrawler $crawler): array
    {
        $alts = [];

        $crawler->filter('img[alt]')->each(function (DomCrawler $node) use (&$alts) {
            $alt = trim((string) $node->attr('alt'));
            if ($alt !== '') {
                $alts[] = $alt;
            }
        });

        return $alts;
    }

    /**
     * @return array<int, array{src: string, alt: string|null, width: int|null, height: int|null, dom_context: string, nearby_heading: string|null}>
     */
    private function extractImageCandidates(DomCrawler $crawler): array
    {
        $candidates = [];
        $seenSrc = [];
        $normalizer = new UrlNormalizer;
        $sectionTags = ['header', 'nav', 'footer', 'main', 'section', 'article', 'aside'];

        // Covers every <img>, not just ones with a usable `src` — most
        // real-world sites (WordPress, Squarespace, Webflow...) lazy-load
        // images behind a 1x1 placeholder `src` and put the real URL in a
        // data-* attribute or srcset, which a naive src-only extractor
        // misses entirely, leaving the redesign with no real photos.
        $crawler->filter('img')->each(function (DomCrawler $node) use (&$candidates, &$seenSrc, $normalizer, $crawler, $sectionTags) {
            $src = $this->resolveImageSrc($node);

            if ($src === null) {
                return;
            }

            $resolved = $normalizer->resolve($crawler->getUri() ?? '', $src);

            if ($resolved === null || isset($seenSrc[$resolved])) {
                return;
            }
            $seenSrc[$resolved] = true;

            $alt = trim((string) $node->attr('alt'));
            $width = is_numeric($node->attr('width')) ? (int) $node->attr('width') : null;
            $height = is_numeric($node->attr('height')) ? (int) $node->attr('height') : null;

            [$domContext, $nearbyHeading] = $this->domContextFor($node, $sectionTags);

            $candidates[] = [
                'src' => $resolved,
                'alt' => $alt !== '' ? $alt : null,
                'width' => $width,
                'height' => $height,
                'dom_context' => $domContext,
                'nearby_heading' => $nearbyHeading,
            ];
        });

        // CSS background-image on inline styles — the other extremely
        // common way real-world sites show a hero banner or section photo
        // without ever using an <img> tag.
        $crawler->filter('[style*="background-image"]')->each(function (DomCrawler $node) use (&$candidates, &$seenSrc, $normalizer, $crawler, $sectionTags) {
            $style = (string) $node->attr('style');

            if (! preg_match('/background-image\s*:\s*url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $style, $m)) {
                return;
            }

            $resolved = $normalizer->resolve($crawler->getUri() ?? '', trim($m[1]));

            if ($resolved === null || isset($seenSrc[$resolved])) {
                return;
            }
            $seenSrc[$resolved] = true;

            [$domContext, $nearbyHeading] = $this->domContextFor($node, $sectionTags);

            $candidates[] = [
                'src' => $resolved,
                'alt' => null,
                'width' => null,
                'height' => null,
                'dom_context' => $domContext,
                'nearby_heading' => $nearbyHeading,
            ];
        });

        return $candidates;
    }

    /**
     * Tries, in order: a real `src`, common lazy-load data-* attributes,
     * then the largest candidate in a (data-)srcset — never a data: URI,
     * which is an inline placeholder, not a real asset to fetch.
     */
    private function resolveImageSrc(DomCrawler $node): ?string
    {
        $src = trim((string) $node->attr('src'));
        if ($src !== '' && ! str_starts_with($src, 'data:')) {
            return $src;
        }

        foreach (['data-src', 'data-lazy-src', 'data-original'] as $attr) {
            $candidate = trim((string) $node->attr($attr));
            if ($candidate !== '' && ! str_starts_with($candidate, 'data:')) {
                return $candidate;
            }
        }

        foreach (['data-srcset', 'srcset'] as $attr) {
            $largest = $this->largestFromSrcset((string) $node->attr($attr));
            if ($largest !== null) {
                return $largest;
            }
        }

        return null;
    }

    /**
     * srcset format: "url1 200w, url2 300w, url3 500w" (or density
     * descriptors like "1x"/"2x") — picks the widest/densest candidate so
     * a stored asset is good enough quality to actually use.
     */
    private function largestFromSrcset(string $srcset): ?string
    {
        $srcset = trim($srcset);
        if ($srcset === '') {
            return null;
        }

        $best = null;
        $bestScore = -1;

        foreach (explode(',', $srcset) as $entry) {
            $parts = preg_split('/\s+/', trim($entry));
            $url = $parts[0] ?? '';
            if ($url === '' || str_starts_with($url, 'data:')) {
                continue;
            }

            $descriptor = $parts[1] ?? '';
            $score = (float) rtrim($descriptor, 'wx');

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $url;
            }
        }

        return $best;
    }

    /**
     * @param  string[]  $sectionTags
     * @return array{0: string, 1: ?string}
     */
    private function domContextFor(DomCrawler $node, array $sectionTags): array
    {
        $domContext = 'body';
        $nearbyHeading = null;

        foreach ($node->ancestors() as $ancestor) {
            $tag = strtolower($ancestor->nodeName ?? '');

            if (in_array($tag, $sectionTags, true)) {
                $domContext = $tag;
                $ancestorCrawler = new DomCrawler($ancestor);
                $headingNodes = $ancestorCrawler->filter('h1, h2, h3, h4, h5, h6');

                if ($headingNodes->count() > 0) {
                    $nearbyHeading = trim($headingNodes->first()->text('')) ?: null;
                }

                break;
            }
        }

        return [$domContext, $nearbyHeading];
    }

    private function extractFaviconUrl(DomCrawler $crawler): ?string
    {
        foreach (['icon', 'shortcut icon', 'apple-touch-icon'] as $rel) {
            $nodes = $crawler->filter("link[rel=\"{$rel}\"]");

            if ($nodes->count() === 0) {
                continue;
            }

            $href = trim((string) $nodes->first()->attr('href'));

            if ($href === '') {
                continue;
            }

            $resolved = (new UrlNormalizer)->resolve($crawler->getUri() ?? '', $href);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function extractContactDetails(DomCrawler $crawler, string $visibleText): array
    {
        $details = [];

        $crawler->filter('a[href^="mailto:"]')->each(function (DomCrawler $node) use (&$details) {
            $details[] = trim(str_ireplace('mailto:', '', (string) $node->attr('href')));
        });

        $crawler->filter('a[href^="tel:"]')->each(function (DomCrawler $node) use (&$details) {
            $details[] = trim(str_ireplace('tel:', '', (string) $node->attr('href')));
        });

        if (preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $visibleText, $matches)) {
            $details = array_merge($details, $matches[0]);
        }

        return array_values(array_unique(array_filter($details)));
    }
}
