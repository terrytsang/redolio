<?php

namespace Tests\Unit\Crawler;

use App\Exceptions\SsrfViolationException;
use App\Services\Crawler\Security\IpRangeChecker;
use App\Services\Crawler\Security\SsrfGuard;
use PHPUnit\Framework\TestCase;

class SsrfGuardTest extends TestCase
{
    public function test_rejects_non_http_schemes(): void
    {
        $guard = new SsrfGuard(new IpRangeChecker);

        $this->expectException(SsrfViolationException::class);
        $guard->assertSafe('ftp://example.com/');
    }

    public function test_rejects_urls_with_embedded_credentials(): void
    {
        $guard = new SsrfGuard(new IpRangeChecker);

        $this->expectException(SsrfViolationException::class);
        $guard->assertSafe('https://user:pass@example.com/');
    }

    public function test_rejects_literal_loopback_ip_as_host(): void
    {
        $guard = new SsrfGuard(new IpRangeChecker);

        $this->expectException(SsrfViolationException::class);
        $guard->assertSafe('http://127.0.0.1/');
    }

    public function test_rejects_literal_cloud_metadata_ip_as_host(): void
    {
        $guard = new SsrfGuard(new IpRangeChecker);

        $this->expectException(SsrfViolationException::class);
        $guard->assertSafe('http://169.254.169.254/latest/meta-data/');
    }

    public function test_rejects_malformed_url(): void
    {
        $guard = new SsrfGuard(new IpRangeChecker);

        $this->expectException(SsrfViolationException::class);
        $guard->assertSafe('not a url');
    }
}
