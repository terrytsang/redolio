<?php

namespace Tests\Feature;

use App\Exceptions\SsrfViolationException;
use App\Services\Crawler\SafeHttpFetcher;
use App\Services\Crawler\Security\IpRangeChecker;
use App\Services\Crawler\Security\SsrfGuard;
use App\Services\Crawler\SiteCrawler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Phase 6: SSRF protections, redirect validation, DNS rebinding and crawl limits. */
class SsrfHardeningTest extends TestCase
{
    private const PUBLIC_IP = '93.184.216.34';

    private function guard(callable $resolver): SsrfGuard
    {
        return new SsrfGuard(new IpRangeChecker, \Closure::fromCallable($resolver));
    }

    private function fetcher(callable $resolver): SafeHttpFetcher
    {
        return new SafeHttpFetcher($this->guard($resolver));
    }

    #[DataProvider('internalAddresses')]
    public function test_internal_and_special_purpose_addresses_are_never_public(string $ip): void
    {
        $this->assertFalse((new IpRangeChecker)->isPublic($ip), "{$ip} must be blocked");
    }

    public static function internalAddresses(): array
    {
        return array_map(fn ($ip) => [$ip], [
            '127.0.0.1', '127.1.2.3', '0.0.0.0', '10.0.0.5', '172.16.0.1', '172.31.255.255', '192.168.1.1', '169.254.169.254', '169.254.170.2',
            '100.64.0.1', '100.100.100.200', '192.0.0.192', '198.18.0.1', '224.0.0.1', '240.0.0.1', '255.255.255.255',
            '::1', '::', 'fe80::1', 'fc00::1', 'fd12:3456:789a::1', 'fd00:ec2::254', 'ff02::1', '::ffff:127.0.0.1', '::ffff:10.0.0.1', '::ffff:169.254.169.254',
            '64:ff9b::7f00:1', '64:ff9b::a9fe:a9fe', '2002:7f00:1::1', '2001:0:4136:e378:8000:63bf:3fff:fdd2', '2001:db8::1',
            'not-an-ip', '', '999.1.1.1', '1.2.3',
        ]);
    }

    #[DataProvider('publicAddresses')]
    public function test_ordinary_public_addresses_remain_allowed(string $ip): void
    {
        $this->assertTrue((new IpRangeChecker)->isPublic($ip));
    }

    public static function publicAddresses(): array
    {
        return [['8.8.8.8'], ['1.1.1.1'], ['93.184.216.34'], ['2606:4700:4700::1111'], ['2a00:1450:4001::200e']];
    }

    #[DataProvider('hostForms')]
    public function test_obfuscated_numeric_hosts_fail_closed(string $url): void
    {
        $this->expectException(SsrfViolationException::class);
        // Real resolvers return nothing for these forms; the guard must not guess what a client library would do.
        $this->guard(fn () => [])->assertSafe($url);
    }

    public static function hostForms(): array
    {
        return array_map(fn ($u) => [$u], [
            'http://2130706433/', 'http://0x7f000001/', 'http://0177.0.0.1/', 'http://127.1/', 'http://0/', 'http://[::1]/', 'http://[::ffff:127.0.0.1]/',
            'http://localhost.localdomain/', 'file:///etc/passwd', 'gopher://127.0.0.1:6379/_x', 'ftp://example.com/', 'javascript:alert(1)', 'data:text/html,hi',
            'http://user:pass@example.com/', 'http://example.com@127.0.0.1/', 'http:///nohost', 'http://', '//example.com', 'example.com',
        ]);
    }

    public function test_hostnames_resolving_to_internal_addresses_are_blocked_including_mixed_answers(): void
    {
        foreach ([['10.0.0.5'], ['127.0.0.1'], ['169.254.169.254'], [self::PUBLIC_IP, '192.168.0.10'], ['::1'], [self::PUBLIC_IP, 'fd00::1']] as $answers) {
            try {
                $this->guard(fn () => $answers)->assertSafe('https://evil.example/');
                $this->fail('Expected block for '.json_encode($answers));
            } catch (SsrfViolationException) {
                $this->addToAssertionCount(1);
            }
        }

        $safe = $this->guard(fn () => [self::PUBLIC_IP, '2606:4700:4700::1111'])->assertSafe('https://good.example:8443/x');
        $this->assertSame([self::PUBLIC_IP, '2606:4700:4700::1111'], $safe['ips']);
        $this->assertSame(8443, $safe['port']);
    }

    public function test_a_redirect_to_an_internal_target_is_blocked_on_every_hop(): void
    {
        foreach (['http://169.254.169.254/latest/meta-data/', 'http://127.0.0.1:8000/admin', 'http://[::1]/', 'http://metadata.internal/', 'file:///etc/passwd', 'http://u:p@good.example/'] as $target) {
            Http::fake(['*' => Http::response('', 302, ['Location' => $target])]);
            try {
                $this->fetcher(fn ($h) => $h === 'metadata.internal' ? ['100.100.100.200'] : [self::PUBLIC_IP])->fetch('https://good.example/');
                $this->fail("Redirect to {$target} must be refused");
            } catch (SsrfViolationException) {
                $this->addToAssertionCount(1);
            }
            Http::assertSentCount(1); // only the first, validated hop was ever requested
        }
    }

    public function test_dns_rebinding_between_hops_is_caught_because_every_hop_is_re_resolved(): void
    {
        $calls = [];
        // good.example is public on the first lookup, then flips to an internal address.
        $resolver = function (string $host) use (&$calls) {
            $calls[] = $host;

            return count(array_keys($calls, $host)) === 1 ? [self::PUBLIC_IP] : ['10.0.0.5'];
        };
        Http::fake(['https://good.example/start' => Http::response('', 302, ['Location' => 'https://good.example/next'])]);

        $this->expectException(SsrfViolationException::class);
        try {
            $this->fetcher($resolver)->fetch('https://good.example/start');
        } finally {
            $this->assertSame(['good.example', 'good.example'], $calls, 'each hop resolves again');
            Http::assertSentCount(1);
        }
    }

    public function test_redirect_chains_are_bounded(): void
    {
        config(['crawler.max_redirects' => 3]);
        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://good.example/again'])]);

        try {
            $this->fetcher(fn () => [self::PUBLIC_IP])->fetch('https://good.example/');
            $this->fail('Expected redirect loop to be refused');
        } catch (SsrfViolationException $e) {
            $this->assertStringContainsString('Too many redirects', $e->getMessage());
        }
        Http::assertSentCount(4); // the original request plus three followed hops, never more
    }

    public function test_safe_redirects_are_followed_and_reported(): void
    {
        Http::fake([
            'https://good.example/old' => Http::response('', 301, ['Location' => '/new']),
            'https://good.example/new' => Http::response('<html>ok</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $result = $this->fetcher(fn () => [self::PUBLIC_IP])->fetch('https://good.example/old');
        $this->assertSame('https://good.example/new', $result->finalUrl);
        $this->assertSame(200, $result->httpStatus);
        $this->assertContains('Followed redirect to https://good.example/new', $result->warnings);
    }

    public function test_oversized_responses_are_truncated_with_a_warning(): void
    {
        config(['crawler.max_response_bytes' => 1000]);
        Http::fake(['*' => Http::response(str_repeat('a', 5000), 200, ['Content-Type' => 'text/html'])]);

        $result = $this->fetcher(fn () => [self::PUBLIC_IP])->fetch('https://good.example/');
        $this->assertSame(1000, strlen($result->body));
        $this->assertContains('Response truncated: exceeded max response size.', $result->warnings);
    }

    public function test_provider_failures_do_not_leak_internal_detail_as_a_violation_and_do_not_retry_forever(): void
    {
        Http::fake(['*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: timed out')]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Fetch failed');
        $this->fetcher(fn () => [self::PUBLIC_IP])->fetch('https://good.example/');
    }

    private function crawler(): SiteCrawler
    {
        Cache::flush();
        $this->app->instance(SsrfGuard::class, $this->guard(fn () => [self::PUBLIC_IP]));

        return $this->app->make(SiteCrawler::class);
    }

    private function page(string $title, array $links): \Illuminate\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface
    {
        $anchors = implode('', array_map(fn ($l) => "<a href=\"{$l}\">link</a>", $links));

        return Http::response("<html><head><title>{$title}</title></head><body><h1>{$title}</h1><p>".str_repeat('Some real page text. ', 12)."</p>{$anchors}</body></html>", 200, ['Content-Type' => 'text/html']);
    }

    public function test_the_crawl_is_bounded_same_origin_and_survives_a_failing_subpage(): void
    {
        config(['crawler.max_pages' => 3, 'crawler.max_depth' => 2]);
        Http::fake([
            'https://site.example/robots.txt' => Http::response('', 404),
            'https://site.example/' => $this->page('Home', ['/a', '/b', '/c', '/d', 'https://other.example/evil', 'http://169.254.169.254/', 'mailto:x@y.z', 'javascript:alert(1)']),
            'https://site.example/a' => Http::response('boom', 500),
            'https://site.example/b' => $this->page('B', ['/e']),
            'https://site.example/c' => $this->page('C', []),
            'https://site.example/*' => $this->page('Other', []),
            'https://other.example/*' => $this->page('SHOULD NOT BE FETCHED', []),
            'http://169.254.169.254/*' => $this->page('METADATA', []),
        ]);

        $snapshot = $this->crawler()->crawl('https://site.example/');
        $urls = array_map(fn ($p) => $p->url, $snapshot->pages);

        $this->assertLessThanOrEqual(3, count($urls), 'max_pages is a hard cap');
        $this->assertSame('https://site.example/', $urls[0]);
        foreach ($urls as $u) {
            $this->assertStringStartsWith('https://site.example/', $u, 'only the submitted origin is crawled');
        }
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'other.example') || str_contains($r->url(), '169.254'));
        $this->assertNotEmpty(array_filter($snapshot->warnings, fn ($w) => str_contains($w, '/a')), 'the failing subpage is recorded, not fatal');
    }

    public function test_key_pages_are_crawled_before_blog_posts_and_legal_pages(): void
    {
        config(['crawler.max_pages' => 4, 'crawler.max_depth' => 2]);
        Http::fake([
            'https://site.example/robots.txt' => Http::response('', 404),
            'https://site.example/' => $this->page('Home', ['/blog/2024/05/our-news', '/privacy-policy', '/tag/dental', '/blog', '/about-us', '/services', '/contact', '/login']),
            'https://site.example/*' => $this->page('Page', []),
        ]);

        $snapshot = $this->crawler()->crawl('https://site.example/');
        $paths = array_map(fn ($p) => parse_url($p->url, PHP_URL_PATH) ?: '/', $snapshot->pages);

        $this->assertSame('/', $paths[0]);
        $this->assertEqualsCanonicalizing(['/', '/about-us', '/services', '/contact'], $paths, 'the budget goes to the pages that explain the business');
        $this->assertEmpty(array_filter($snapshot->warnings, fn ($w) => str_contains($w, 'Page limit')), 'only blog/legal/login pages were left out: nothing to warn about');
    }

    public function test_a_page_limit_warning_appears_only_when_key_pages_were_left_out(): void
    {
        config(['crawler.max_pages' => 2, 'crawler.max_depth' => 2]);
        Http::fake([
            'https://site.example/robots.txt' => Http::response('', 404),
            'https://site.example/' => $this->page('Home', ['/about', '/services', '/pricing']),
            'https://site.example/*' => $this->page('Page', []),
        ]);

        $snapshot = $this->crawler()->crawl('https://site.example/');
        $this->assertNotEmpty(array_filter($snapshot->warnings, fn ($w) => str_contains($w, 'key pages')));
    }

    public function test_robots_txt_disallow_is_respected(): void
    {
        config(['crawler.max_pages' => 5]);
        Http::fake([
            'https://site.example/robots.txt' => Http::response("User-agent: *\nDisallow: /private\n", 200),
            'https://site.example/' => $this->page('Home', ['/private/area', '/public']),
            'https://site.example/public' => $this->page('Public', []),
            'https://site.example/private/area' => $this->page('SECRET', []),
        ]);

        $snapshot = $this->crawler()->crawl('https://site.example/');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/private/area'));
        $this->assertNotEmpty(array_filter($snapshot->warnings, fn ($w) => str_contains($w, 'robots.txt')));
    }

    public function test_a_homepage_that_resolves_internally_is_refused_before_any_request(): void
    {
        Http::fake();
        $this->app->instance(SsrfGuard::class, $this->guard(fn () => ['10.1.2.3']));
        Cache::flush();

        $this->expectException(SsrfViolationException::class);
        try {
            $this->app->make(SiteCrawler::class)->crawl('https://intranet.example/');
        } finally {
            Http::assertNothingSent();
        }
    }
}
