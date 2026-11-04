<?php

namespace App\Services\Analytics;

use Illuminate\Http\Request;

/** Device type, traffic source and bot detection for one request — from headers only. */
class VisitClassifier
{
    public static function isBot(?string $ua): bool
    {
        return $ua === null || $ua === '' || (bool) preg_match('/bot|crawl|spider|slurp|facebookexternalhit|headless|monitor|curl|wget|python-requests|preview/i', $ua);
    }

    public static function device(?string $ua): string
    {
        $ua = (string) $ua;
        if (preg_match('/ipad|tablet|(android(?!.*mobile))/i', $ua)) {
            return 'tablet';
        }

        return preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|iemobile/i', $ua) ? 'mobile' : 'desktop';
    }

    /** utm_source / ref wins; otherwise the referring site's host; otherwise "Direct". Visits from our own site count as direct. */
    public static function source(Request $request): string
    {
        $utm = trim((string) ($request->query('utm_source') ?? $request->query('ref') ?? ''));
        if ($utm !== '') {
            return mb_substr(strtolower($utm), 0, 100);
        }
        $host = strtolower((string) parse_url((string) $request->headers->get('referer'), PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);
        $own = preg_replace('/^www\./', '', strtolower($request->getHost()));

        return $host === '' || $host === $own ? 'Direct' : mb_substr($host, 0, 150);
    }
}
