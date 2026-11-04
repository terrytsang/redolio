<?php

namespace App\Services\Crawler\Security;

class IpRangeChecker
{
    /**
     * Explicit deny list kept in addition to the FILTER_FLAG_NO_PRIV_RANGE /
     * FILTER_FLAG_NO_RES_RANGE checks below, in case those flags ever change
     * behaviour across PHP versions. Covers well-known cloud metadata hosts.
     */
    private const DENYLIST_CIDRS = [
        '169.254.169.254/32', // AWS/GCP/Azure/DigitalOcean metadata
        '169.254.170.2/32',   // AWS ECS task metadata
        'fd00:ec2::254/128',  // AWS IMDSv2 IPv6
        // Ranges PHP's private/reserved flags treat as public but that reach internal networks
        // or cloud metadata in practice (verified against filter_var):
        '100.64.0.0/10',      // carrier-grade NAT / shared space (e.g. Alibaba metadata 100.100.100.200)
        '192.0.0.0/24',       // IETF protocol assignments
        '198.18.0.0/15',      // benchmarking
        '224.0.0.0/4',        // multicast
        '64:ff9b::/96',       // NAT64: embeds an IPv4 address (64:ff9b::7f00:1 = 127.0.0.1)
        '64:ff9b:1::/48',     // local-use NAT64
        '2002::/16',          // 6to4: embeds an IPv4 address
        '2001::/32',          // Teredo
        '2001:db8::/32',      // documentation
        'ff00::/8',           // IPv6 multicast
    ];

    public function isPublic(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        foreach (self::DENYLIST_CIDRS as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) {
                return false;
            }
        }

        $valid = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        return $valid !== false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $bits = (int) $bits;

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        $remainderBits = $bits % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        if ($remainderBits === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $remainderBits) & 0xFF;

        return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
    }
}
