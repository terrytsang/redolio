<?php

namespace App\Services\Crawler\Security;

use App\Exceptions\SsrfViolationException;

/**
 * Validates that a URL is safe to fetch: HTTP(S) only, no embedded
 * credentials, and every DNS-resolved IP for the host is a public,
 * non-reserved address. Must be called again for every redirect hop,
 * not only for the original submitted URL.
 */
class SsrfGuard
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /** @param  \Closure(string): string[]|null  $resolver  Test seam: host => IPs. Defaults to real DNS (A + AAAA). */
    public function __construct(private readonly IpRangeChecker $ipRangeChecker, private readonly ?\Closure $resolver = null) {}

    /**
     * @return array{host: string, scheme: string, port: int, ips: string[]}
     */
    public function assertSafe(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new SsrfViolationException('Malformed URL.', $url);
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new SsrfViolationException("Scheme [{$scheme}] is not allowed.", $url);
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new SsrfViolationException('URLs with embedded credentials are not allowed.', $url);
        }

        $host = $parts['host'];
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        $ips = $this->resolveHost($host);

        if (empty($ips)) {
            throw new SsrfViolationException('Host could not be resolved.', $url);
        }

        foreach ($ips as $ip) {
            if (! $this->ipRangeChecker->isPublic($ip)) {
                throw new SsrfViolationException("Resolved IP [{$ip}] is not a public address.", $url);
            }
        }

        return [
            'host' => $host,
            'scheme' => $scheme,
            'port' => (int) $port,
            'ips' => $ips,
        ];
    }

    /**
     * @return string[]
     */
    private function resolveHost(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        if ($this->resolver !== null) {
            return array_values(array_unique(($this->resolver)($host)));
        }

        $ips = [];

        $aRecords = @dns_get_record($host, DNS_A);
        if (is_array($aRecords)) {
            foreach ($aRecords as $record) {
                if (! empty($record['ip'])) {
                    $ips[] = $record['ip'];
                }
            }
        }

        $aaaaRecords = @dns_get_record($host, DNS_AAAA);
        if (is_array($aaaaRecords)) {
            foreach ($aaaaRecords as $record) {
                if (! empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }
}
