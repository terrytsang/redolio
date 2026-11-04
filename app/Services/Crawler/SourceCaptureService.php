<?php

namespace App\Services\Crawler;

use App\DTO\Crawler\SourceCapture;
use App\Services\Crawler\Security\SsrfGuard;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Reads a customer's website with a real browser: content for sites that need JavaScript or sit behind
 * a bot-protection challenge, plus a genuine desktop screenshot for the "before" panel.
 *
 * Security: the URL is validated by SsrfGuard here, and the browser (scripts/visual-qa/capture-source.cjs)
 * can only reach the network through scripts/visual-qa/egress-proxy.cjs, which refuses every non-public
 * address at connection time — see docs/v1.5-runbook.md. Enabled by default only in the local environment;
 * production should use an isolated worker or a remote browser provider and set REDO_SOURCE_CAPTURE=true
 * only where that isolation exists.
 */
class SourceCaptureService
{
    private ?bool $nodeOk = null;

    public function __construct(private readonly SsrfGuard $guard) {}

    public function isAvailable(): bool
    {
        if (! config('redo.source_capture.enabled')) {
            return false;
        }

        return $this->nodeOk ??= $this->nodeAvailable();
    }

    /** @throws \App\Exceptions\SsrfViolationException when the URL itself is not a permitted public destination */
    public function capture(string $url): ?SourceCapture
    {
        $this->guard->assertSafe($url);

        $id = (string) Str::uuid();
        $work = storage_path("app/private/source-capture/{$id}");
        File::ensureDirectoryExists($work);

        try {
            $config = json_encode([
                'url' => $url, 'outDir' => $work,
                'maxPages' => (int) config('redo.source_capture.max_pages', 4),
                'maxMs' => (int) config('redo.source_capture.max_seconds', 75) * 1000,
            ]);
            $run = Process::timeout((int) config('redo.source_capture.max_seconds', 75) + 45)->path(base_path())
                ->run(['node', base_path('scripts/visual-qa/capture-source.cjs'), $config]);

            $lines = array_values(array_filter(explode("\n", trim($run->output()))));
            $data = $lines ? json_decode(end($lines), true) : null;
            if (! $run->successful() || ! is_array($data)) {
                Log::warning('Source capture failed', ['url' => $url, 'error' => substr($run->errorOutput(), 0, 300)]);

                return null;
            }

            $screenshotUrl = null;
            if (! empty($data['screenshot']) && File::exists("{$work}/{$data['screenshot']}")) {
                $path = "site-captures/{$id}/desktop.png";
                Storage::disk('public')->put($path, File::get("{$work}/{$data['screenshot']}"));
                $screenshotUrl = Storage::disk('public')->url($path);
            }

            return new SourceCapture(
                pages: array_values(array_filter((array) ($data['pages'] ?? []), fn ($p) => ! empty($p['html']))),
                screenshotUrl: $screenshotUrl,
                challengeCleared: (bool) ($data['challenge_cleared'] ?? false),
                errors: (array) ($data['errors'] ?? []),
                blocked: (array) ($data['blocked'] ?? []),
            );
        } catch (\App\Exceptions\SsrfViolationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Source capture error', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        } finally {
            File::deleteDirectory($work);
        }
    }

    private function nodeAvailable(): bool
    {
        try {
            return Process::timeout(5)->run(['node', '-v'])->successful() && is_dir(base_path('node_modules/playwright'));
        } catch (\Throwable) {
            return false;
        }
    }
}
