<?php

namespace App\Services\Crawler;

class UrlNormalizer
{
    public function normalize(string $url): ?string
    {
        $parts = parse_url(trim($url));

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? null;
        $defaultPort = $scheme === 'https' ? 443 : 80;

        $path = $parts['path'] ?? '/';
        $path = $path === '' ? '/' : $path;

        $normalized = "{$scheme}://{$host}";

        if ($port !== null && $port !== $defaultPort) {
            $normalized .= ":{$port}";
        }

        $normalized .= $path;

        if (! empty($parts['query'])) {
            $normalized .= '?'.$parts['query'];
        }

        // Fragments are intentionally dropped.
        return $normalized;
    }

    /**
     * Resolve a possibly-relative href against a base URL. Returns null for
     * non-crawlable schemes (mailto, tel, javascript, etc.) or malformed URLs.
     */
    public function resolve(string $baseUrl, string $href): ?string
    {
        $href = trim($href);

        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }

        $lower = strtolower($href);
        foreach (['mailto:', 'tel:', 'javascript:', 'data:', 'ftp:'] as $unsupported) {
            if (str_starts_with($lower, $unsupported)) {
                return null;
            }
        }

        $base = parse_url($baseUrl);
        if ($base === false || empty($base['scheme']) || empty($base['host'])) {
            return null;
        }

        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $href)) {
            return $this->normalize($href);
        }

        $baseScheme = $base['scheme'];
        $baseHost = $base['host'];
        $basePort = isset($base['port']) ? ':'.$base['port'] : '';
        $baseAuthority = "{$baseScheme}://{$baseHost}{$basePort}";

        if (str_starts_with($href, '//')) {
            return $this->normalize("{$baseScheme}:{$href}");
        }

        if (str_starts_with($href, '/')) {
            return $this->normalize($baseAuthority.$href);
        }

        $basePath = $base['path'] ?? '/';
        $baseDir = substr($basePath, 0, strrpos($basePath, '/') + 1) ?: '/';

        return $this->normalize($baseAuthority.$this->collapseDots($baseDir.$href));
    }

    public function isSameOrigin(string $a, string $b): bool
    {
        $pa = parse_url($a);
        $pb = parse_url($b);

        if ($pa === false || $pb === false) {
            return false;
        }

        $schemeA = strtolower($pa['scheme'] ?? '');
        $schemeB = strtolower($pb['scheme'] ?? '');
        $hostA = strtolower($pa['host'] ?? '');
        $hostB = strtolower($pb['host'] ?? '');
        $portA = $pa['port'] ?? ($schemeA === 'https' ? 443 : 80);
        $portB = $pb['port'] ?? ($schemeB === 'https' ? 443 : 80);

        return $schemeA === $schemeB && $hostA === $hostB && $portA === $portB;
    }

    private function collapseDots(string $path): string
    {
        $segments = explode('/', $path);
        $resolved = [];

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '') {
                continue;
            }

            if ($segment === '..') {
                array_pop($resolved);

                continue;
            }

            $resolved[] = $segment;
        }

        return '/'.implode('/', $resolved);
    }
}
