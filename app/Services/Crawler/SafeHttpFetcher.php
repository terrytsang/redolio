<?php

namespace App\Services\Crawler;

use App\DTO\Crawler\FetchResult;
use App\Exceptions\SsrfViolationException;
use App\Services\Crawler\Security\SsrfGuard;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches a single URL with SSRF protections applied to the original
 * destination and to every redirect hop, with bounded response size,
 * timeouts and a pinned DNS resolution to reduce DNS-rebinding risk.
 */
class SafeHttpFetcher
{
    public function __construct(private readonly SsrfGuard $ssrfGuard) {}

    /**
     * @throws SsrfViolationException
     */
    public function fetch(string $url): FetchResult
    {
        $maxRedirects = (int) config('crawler.max_redirects', 5);
        $connectTimeout = (int) config('crawler.connect_timeout_seconds', 5);
        $requestTimeout = (int) config('crawler.page_timeout_seconds', 15);
        $maxBytes = (int) config('crawler.max_response_bytes', 2_000_000);
        $userAgent = (string) config('crawler.user_agent', 'REDOLIO Website Analysis Bot');

        $warnings = [];
        $currentUrl = $url;

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $safety = $this->ssrfGuard->assertSafe($currentUrl);

            $curlResolve = array_map(
                fn (string $ip) => "{$safety['host']}:{$safety['port']}:{$ip}",
                $safety['ips']
            );

            try {
                $response = Http::withOptions([
                    'curl' => [
                        CURLOPT_RESOLVE => $curlResolve,
                    ],
                ])
                    ->connectTimeout($connectTimeout)
                    ->timeout($requestTimeout)
                    ->withHeaders(['User-Agent' => $userAgent])
                    ->withoutRedirecting()
                    ->get($currentUrl);
            } catch (Throwable $e) {
                throw new \RuntimeException("Fetch failed for [{$currentUrl}]: {$e->getMessage()}", previous: $e);
            }

            $status = $response->status();

            if (in_array($status, [301, 302, 303, 307, 308], true) && $response->header('Location')) {
                $next = (new UrlNormalizer)->resolve($currentUrl, $response->header('Location'));

                if ($next === null) {
                    throw new SsrfViolationException('Redirect target could not be resolved.', $currentUrl);
                }

                $currentUrl = $next;
                $warnings[] = "Followed redirect to {$currentUrl}";

                continue;
            }

            $contentType = (string) $response->header('Content-Type');
            $body = $response->body();

            if (strlen($body) > $maxBytes) {
                $body = substr($body, 0, $maxBytes);
                $warnings[] = 'Response truncated: exceeded max response size.';
            }

            return new FetchResult(
                finalUrl: $currentUrl,
                httpStatus: $status,
                body: $body,
                contentType: $contentType,
                warnings: $warnings,
            );
        }

        throw new SsrfViolationException('Too many redirects.', $url);
    }
}
