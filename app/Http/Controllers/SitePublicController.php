<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves published static artifacts from isolated, non-public storage.
 * Never trusts an arbitrary slug/Host header — only a verified
 * analysis_runs.published_slug with a live active_deployment is served
 * (AT-42: no tenant content without a verified domain registry mapping).
 */
class SitePublicController extends Controller
{
    private const MIME_TYPES = [
        'html' => 'text/html; charset=UTF-8',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ico' => 'image/x-icon',
        'txt' => 'text/plain',
        'md' => 'text/markdown',
    ];

    /**
     * Published sites are static: no scripts, no third-party code, no framing by other origins.
     * Images may be https (stock/AI imagery); forms may only open a mailto: or post to this origin.
     */
    private function securityHeaders(bool $scripts = false, bool $frames = false): array
    {
        // Strict by default. A page only gets script/frame/form allowances when it actually ships an add-on that needs them
        // (the widgets script for carousels + analytics, or an iframe for an embed, video or map).
        $csp = "default-src 'none'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; font-src 'self' data:; form-action 'self' mailto: https:; base-uri 'none'; frame-ancestors 'self'";
        $scripts && $csp .= "; script-src 'self' https://www.googletagmanager.com; connect-src https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com";
        $frames && $csp .= "; frame-src 'self' https://www.youtube-nocookie.com https://www.google.com";

        return [
            'Content-Security-Policy' => $csp,
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        ];
    }

    public function show(Request $request, string $slug, ?string $path = null): Response|StreamedResponse|RedirectResponse
    {
        $path ??= '';

        $run = AnalysisRun::where('published_slug', $slug)->first();

        if (! $run || ! $run->activeDeployment || ! $run->activeDeployment->isLive()) {
            abort(404);
        }

        // Relative asset references (assets/app.css) only resolve correctly
        // against a trailing-slash URL — without this redirect the browser
        // resolves them one level too high and the page renders unstyled.
        if ($path === '' && ! str_ends_with($request->getRequestUri(), '/')) {
            // redirect()->to() trims trailing slashes via UrlGenerator, so
            // it can't produce this URL — away() passes the path through untouched.
            return redirect()->away($request->getRequestUri().'/');
        }

        $relative = trim($path, '/') ?: 'index.html';
        $baseDir = storage_path("app/private/sites/{$slug}");
        $fullPath = realpath("{$baseDir}/{$relative}");

        if (! $fullPath || ! str_starts_with($fullPath, realpath($baseDir)) || ! File::exists($fullPath)) {
            abort(404);
        }

        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $mime = self::MIME_TYPES[$extension] ?? 'application/octet-stream';

        $headers = ['Content-Type' => $mime] + $this->securityHeaders(File::exists("{$baseDir}/assets/redo-widgets.js"), $extension === 'html' && str_contains(File::get($fullPath), '<iframe'));
        if (str_starts_with($relative, 'embeds/') && $extension === 'html') {
            // An Embed widget's own code: it may do what it needs, but always in an opaque-origin sandbox — it can never touch this site's origin.
            $headers['Content-Security-Policy'] = "sandbox allow-scripts allow-popups allow-forms allow-popups-to-escape-sandbox; frame-ancestors 'self'";
        }

        return response(File::get($fullPath), 200, $headers);
    }
}
