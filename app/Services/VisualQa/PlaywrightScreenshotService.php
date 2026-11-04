<?php

namespace App\Services\VisualQa;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Local provider: thin PHP wrapper around scripts/visual-qa/capture.cjs. Node/
 * Playwright is an optional, separately-verified capability (PRD risk: shared
 * hosting cannot run browser binaries) — use the Browserless provider there.
 * It only ever opens file:// artifacts REDO built, never a customer URL.
 */
class PlaywrightScreenshotService implements BrowserService
{
    private const TIMEOUT_SECONDS = 120;

    public function id(): string
    {
        return 'local';
    }

    public function capturesExternalUrls(): bool
    {
        return false;
    }

    public function isAvailable(): bool
    {
        try {
            return Process::timeout(5)->run(['node', '-v'])->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function capture(string $htmlPath, string $outputDir, ?array $expectations = null): array
    {
        File::ensureDirectoryExists($outputDir);
        $command = ['node', base_path('scripts/visual-qa/capture.cjs'), $htmlPath, $outputDir];

        if ($expectations !== null) {
            $path = "{$outputDir}/expectations.json";
            File::put($path, json_encode($expectations));
            $command[] = $path;
        }

        $result = Process::timeout(self::TIMEOUT_SECONDS)->path(base_path())->run($command);

        if (! $result->successful()) {
            throw new RuntimeException('Visual QA screenshot capture failed: '.(trim($result->errorOutput()) ?: 'unknown error'));
        }

        $viewports = [];
        foreach (explode("\n", trim($result->output())) as $line) {
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $viewports[] = $decoded;
            }
        }

        return $viewports;
    }
}
