<?php

namespace Tests\Unit\Crawler;

use App\Services\Crawler\Security\IpRangeChecker;
use PHPUnit\Framework\TestCase;

class IpRangeCheckerTest extends TestCase
{
    public function test_accepts_public_ipv4_addresses(): void
    {
        $checker = new IpRangeChecker;

        $this->assertTrue($checker->isPublic('93.184.216.34'));
    }

    public function test_rejects_loopback_private_link_local_and_metadata_addresses(): void
    {
        $checker = new IpRangeChecker;

        $this->assertFalse($checker->isPublic('127.0.0.1'));
        $this->assertFalse($checker->isPublic('10.0.0.5'));
        $this->assertFalse($checker->isPublic('172.16.0.5'));
        $this->assertFalse($checker->isPublic('192.168.1.5'));
        $this->assertFalse($checker->isPublic('169.254.169.254'));
        $this->assertFalse($checker->isPublic('0.0.0.0'));
        $this->assertFalse($checker->isPublic('::1'));
    }

    public function test_rejects_malformed_ip_strings(): void
    {
        $checker = new IpRangeChecker;

        $this->assertFalse($checker->isPublic('not-an-ip'));
    }
}
