<?php

namespace Tests\Unit\Crawler;

use App\Services\Crawler\UrlNormalizer;
use PHPUnit\Framework\TestCase;

class UrlNormalizerTest extends TestCase
{
    public function test_normalizes_scheme_host_and_strips_fragment(): void
    {
        $normalizer = new UrlNormalizer;

        $this->assertSame(
            'https://example.com/path',
            $normalizer->normalize('HTTPS://Example.com:443/path#section')
        );
    }

    public function test_rejects_non_http_scheme(): void
    {
        $normalizer = new UrlNormalizer;

        $this->assertNull($normalizer->normalize('javascript:alert(1)'));
    }

    public function test_resolves_relative_and_absolute_paths(): void
    {
        $normalizer = new UrlNormalizer;

        $this->assertSame(
            'https://example.com/about',
            $normalizer->resolve('https://example.com/contact', '/about')
        );

        $this->assertSame(
            'https://example.com/blog/post',
            $normalizer->resolve('https://example.com/blog/', 'post')
        );
    }

    public function test_ignores_unsupported_schemes_in_links(): void
    {
        $normalizer = new UrlNormalizer;

        $this->assertNull($normalizer->resolve('https://example.com/', 'mailto:hi@example.com'));
        $this->assertNull($normalizer->resolve('https://example.com/', 'tel:+15550001234'));
        $this->assertNull($normalizer->resolve('https://example.com/', '#top'));
    }

    public function test_same_origin_check(): void
    {
        $normalizer = new UrlNormalizer;

        $this->assertTrue($normalizer->isSameOrigin('https://example.com/a', 'https://example.com/b'));
        $this->assertFalse($normalizer->isSameOrigin('https://example.com/a', 'https://other.com/b'));
        $this->assertFalse($normalizer->isSameOrigin('https://example.com/a', 'http://example.com/b'));
    }
}
