<?php

namespace App\Services\Crawler;

use App\DTO\Crawler\ExtractedPage;
use App\DTO\Crawler\SiteSnapshotDto;
use App\Exceptions\SsrfViolationException;
use App\Services\Evidence\EvidenceBuilder;
use Throwable;

/**
 * Bounded, same-origin, Laravel-native crawler. Fetches the homepage first,
 * then discovers and fetches up to max_pages internal pages via breadth-first
 * traversal. A failed subpage never aborts the whole run — it is recorded as
 * a warning and traversal continues (CR-02/CR-03/CR-04, FR-003 acceptance).
 */
class SiteCrawler
{
    public function __construct(
        private readonly SafeHttpFetcher $fetcher,
        private readonly HtmlPageExtractor $extractor,
        private readonly UrlNormalizer $normalizer,
        private readonly RobotsPolicy $robotsPolicy,
        private readonly EvidenceBuilder $evidenceBuilder,
    ) {}

    public function crawl(string $homepageUrl): SiteSnapshotDto
    {
        $maxPages = (int) config('crawler.max_pages', 5);
        $maxDepth = (int) config('crawler.max_depth', 2);
        $userAgent = (string) config('crawler.user_agent');

        $normalizedHome = $this->normalizer->normalize($homepageUrl);

        if ($normalizedHome === null) {
            throw new SsrfViolationException('Homepage URL could not be normalized.', $homepageUrl);
        }

        $visited = [];
        $queue = [[$normalizedHome, 0, 1000]]; // [url, depth, priority] — the homepage always goes first
        $queued = [$normalizedHome => true];
        $priority = new LinkPriority;
        $pages = [];
        $warnings = [];

        while (! empty($queue) && count($pages) < $maxPages) {
            // The most useful waiting page first (about, services, pricing, contact…); ties keep the order they were found in.
            $best = 0;
            foreach ($queue as $i => $q) {
                if ($q[2] > $queue[$best][2]) {
                    $best = $i;
                }
            }
            [$currentUrl, $depth] = array_splice($queue, $best, 1)[0];

            if (isset($visited[$currentUrl])) {
                continue;
            }

            $visited[$currentUrl] = true;

            $origin = $this->origin($currentUrl);
            $path = parse_url($currentUrl, PHP_URL_PATH) ?: '/';

            if (! $this->robotsPolicy->isAllowed($origin, $path, $userAgent)) {
                $warnings[] = "Skipped {$currentUrl}: disallowed by robots.txt.";

                continue;
            }

            try {
                $page = $this->fetchAndExtract($currentUrl);
            } catch (SsrfViolationException $e) {
                if ($currentUrl === $normalizedHome) {
                    throw $e;
                }

                $warnings[] = "Skipped {$currentUrl}: {$e->getMessage()}";

                continue;
            } catch (Throwable $e) {
                if ($currentUrl === $normalizedHome) {
                    throw $e;
                }

                $warnings[] = "Failed to fetch {$currentUrl}: {$e->getMessage()}";

                continue;
            }

            // An error page is not site content: never let its text become evidence or use up the page budget.
            if ($currentUrl !== $normalizedHome && ($page->httpStatus < 200 || $page->httpStatus >= 400)) {
                $warnings[] = "Skipped {$currentUrl}: HTTP {$page->httpStatus}.";

                continue;
            }

            $pages[] = $page;
            $warnings = array_merge($warnings, $page->warnings);

            if ($depth >= $maxDepth) {
                continue;
            }

            foreach ($page->links as $link) {
                $href = $link['href'];

                if (isset($visited[$href]) || isset($queued[$href]) || ! $this->normalizer->isSameOrigin($normalizedHome, $href)) {
                    continue;
                }

                $queued[$href] = true;
                $queue[] = [$href, $depth + 1, $priority->score($href, (string) ($link['label'] ?? ''))];
            }
        }

        // Only worth a warning when pages that explain the business were left out; skipped blog posts and legal pages are normal.
        if (count($pages) >= $maxPages && collect($queue)->contains(fn ($q) => $q[2] > 0)) {
            $warnings[] = 'Page limit reached; some key pages (e.g. about, services, contact) were not crawled.';
        }

        $evidence = $this->evidenceBuilder->build($pages);
        $snapshotHash = hash('sha256', implode('|', array_map(fn (ExtractedPage $p) => $p->url.$p->visibleText, $pages)));

        return new SiteSnapshotDto(
            snapshotHash: $snapshotHash,
            homepageUrl: $normalizedHome,
            pages: $pages,
            evidence: $evidence,
            warnings: array_values(array_unique($warnings)),
        );
    }

    /**
     * Builds a snapshot from pages a real browser rendered (same extractor, evidence and hashing as a plain
     * crawl). Error pages and pages that were still a challenge are dropped, never analysed.
     *
     * @param  array<int, array{url: string, status: int, html: string}>  $rendered
     * @param  string[]  $warnings
     */
    public function snapshotFromRendered(array $rendered, string $homepageUrl, array $warnings = []): SiteSnapshotDto
    {
        $pages = [];
        foreach ($rendered as $r) {
            $page = $this->extractor->extract($r['url'], $r['status'] ?: 200, $r['html']);
            if ($page->httpStatus >= 400 || in_array('bot_challenge_detected', $page->warnings, true)) {
                $warnings[] = "Skipped {$r['url']}: it was an access check or error page.";

                continue;
            }
            $pages[] = $page;
            $warnings = array_merge($warnings, array_filter($page->warnings, fn ($w) => $w !== 'bot_challenge_detected'));
        }

        return new SiteSnapshotDto(
            snapshotHash: hash('sha256', implode('|', array_map(fn (ExtractedPage $p) => $p->url.$p->visibleText, $pages))),
            homepageUrl: $homepageUrl,
            pages: $pages,
            evidence: $this->evidenceBuilder->build($pages),
            warnings: array_values(array_unique($warnings)),
        );
    }

    private function fetchAndExtract(string $url): ExtractedPage
    {
        $result = $this->fetcher->fetch($url);

        return $this->extractor->extract($result->finalUrl, $result->httpStatus, $result->body);
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
    }
}
