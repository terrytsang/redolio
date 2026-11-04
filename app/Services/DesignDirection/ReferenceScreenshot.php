<?php

namespace App\Services\DesignDirection;

use App\Services\Crawler\UrlSubmissionValidator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * A real homepage screenshot of a reference website, shown next to a Design Direction so admins and users can see what inspired
 * it. Taken in an isolated remote browser (Browserbase, then Browserless as the backup); never required — a direction without a
 * picture is still a complete direction. The address is checked like any submitted URL before a browser is pointed at it.
 */
class ReferenceScreenshot
{
    public function __construct(private readonly UrlSubmissionValidator $validator) {}

    /** @return string|null the public URL of the saved screenshot */
    public function capture(string $url, string $key): ?string
    {
        try {
            $safe = $this->validator->validateAndNormalize($url);
        } catch (Throwable) {
            return null;
        }

        $providers = array_values(array_unique(array_filter([(string) config('redo.browser.provider'), (string) config('redo.browser.fallback')])));
        foreach ($providers as $provider) {
            try {
                $bytes = match ($provider) {
                    'browserbase' => $this->viaBrowserbase($safe),
                    'browserless' => $this->viaBrowserless($safe),
                    'local' => $this->viaLocal($safe),
                    default => null,
                };
            } catch (Throwable $e) {
                Log::info('Reference screenshot failed', ['provider' => $provider, 'url' => $safe, 'error' => mb_substr($e->getMessage(), 0, 200)]);
                $bytes = null;
            }
            if ($bytes !== null && @getimagesizefromstring($bytes) !== false) {
                $path = 'direction-previews/'.preg_replace('/[^a-z0-9_-]/i', '', $key).'-'.substr(md5($safe.microtime()), 0, 8).'.jpg';
                Storage::disk('public')->put($path, $bytes);

                return Storage::disk('public')->url($path);
            }
        }

        return null;
    }

    private function viaBrowserbase(string $url): ?string
    {
        $apiKey = config('redo.browser.browserbase_api_key');
        if (! $apiKey || ! is_dir(base_path('node_modules/playwright-core'))) {
            return null;
        }
        $out = tempnam(sys_get_temp_dir(), 'refshot').'.jpg';
        try {
            $r = Process::timeout(90)->path(base_path())
                ->env(array_filter(['REDO_BROWSER_MODE' => 'browserbase', 'BROWSERBASE_API_KEY' => $apiKey, 'BROWSERBASE_PROJECT_ID' => config('redo.browser.browserbase_project_id')]))
                ->run([(string) config('redo.browser.node_binary', 'node'), base_path('scripts/visual-qa/capture-url.cjs'), $url, $out]);

            return $r->successful() && is_file($out) ? File::get($out) : null;
        } finally {
            @unlink($out);
        }
    }

    private function viaBrowserless(string $url): ?string
    {
        $token = config('redo.browser.browserless_token');
        if (! $token) {
            return null;
        }
        $r = Http::timeout(60)->withQueryParameters(['token' => $token])->post(rtrim((string) config('redo.browser.browserless_url'), '/').'/screenshot', [
            'url' => $url, 'options' => ['type' => 'jpeg', 'quality' => 82],
            'viewport' => ['width' => 1440, 'height' => 900],
            'gotoOptions' => ['waitUntil' => 'networkidle2', 'timeout' => 30000], 'waitForTimeout' => 1200,
        ]);

        return $r->successful() && str_starts_with((string) $r->header('Content-Type'), 'image/') ? $r->body() : null;
    }

    /** Development only: a Chromium installed on this machine. */
    private function viaLocal(string $url): ?string
    {
        $out = tempnam(sys_get_temp_dir(), 'refshot').'.jpg';
        try {
            $r = Process::timeout(60)->path(base_path())->run([(string) config('redo.browser.node_binary', 'node'), base_path('scripts/visual-qa/capture-url.cjs'), $url, $out]);

            return $r->successful() && is_file($out) ? File::get($out) : null;
        } finally {
            @unlink($out);
        }
    }
}
