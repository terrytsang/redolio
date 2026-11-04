<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSetupWizard
{
    /**
     * Self-hosted (MIT) installs have no .env-seeded admin. Until one
     * exists, every request is funneled through the in-browser setup
     * wizard — except the wizard itself and the analyse/preview/demo
     * routes it embeds, so a visitor can run the full REDO journey
     * (crawl → DNA → diagnosis → preview) before creating the admin
     * account that unlocks saving/rebuilding.
     */
    private const ALLOWED_PREFIXES = [
        'setup',
        'analyses',
        'demo',
        'up',
        'build',
        'storage',
        'sites',
        'exports',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (config('redo.edition') !== 'selfhosted') {
            return $next($request);
        }

        try {
            $adminExists = User::where('role', 'admin')->exists();
        } catch (\Throwable) {
            return $next($request);
        }

        if ($adminExists) {
            return $next($request);
        }

        $path = ltrim($request->path(), '/');

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, "{$prefix}/")) {
                return $next($request);
            }
        }

        return redirect()->route('setup.index');
    }
}
