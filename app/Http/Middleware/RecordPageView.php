<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use App\Services\Analytics\GeoLocator;
use App\Services\Analytics\VisitClassifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Visitor analytics: one row per page visit with date, place (country/city), device and where the visitor came from.
 * Recorded in terminate(), i.e. after the response has been sent, so the place lookup never slows a page down.
 * No IP address is stored.
 */
class RecordPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $ua = $request->userAgent();
        // Internal previews, the admin area, health checks and robots are not visitor traffic.
        if (! $request->isMethod('get') || $request->is('blueprints/*', 'admin', 'admin/*', 'up') || $request->ajax()
            || ! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html') || VisitClassifier::isBot($ua)) {
            return;
        }

        try {
            $geo = app(GeoLocator::class)->locate($request->ip());
            PageView::create([
                'path' => mb_substr($request->path(), 0, 2000),
                'user_id' => $request->user()?->id,
                'visitor_hash' => hash('sha256', $request->ip().'|'.$ua),
                'country' => $geo['country'],
                'city' => $geo['city'],
                'device' => VisitClassifier::device($ua),
                'source' => VisitClassifier::source($request),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e); // analytics must never break a page
        }
    }
}
