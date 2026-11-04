<?php

namespace App\Services\VisualQa;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Remote provider: Browserbase (https://browserbase.com). For hosts that cannot run Chromium (shared hosting such as Opalstack).
 * scripts/visual-qa/capture.cjs opens a Browserbase cloud browser over CDP (playwright-core is enough — no browser is installed
 * here), loads the page's HTML with its local images inlined, and runs the same screenshots and checks as the local provider.
 * The API key reaches the script through its environment only, never the command line.
 */
class BrowserbaseBrowser implements BrowserService
{
    use InlinesLocalAssets;

    private const TIMEOUT_SECONDS = 150;

    public function __construct(
        private readonly ?string $apiKey,
        private readonly ?string $projectId = null,
        private readonly string $node = 'node',
    ) {}

    public function id(): string
    {
        return 'browserbase';
    }

    public function capturesExternalUrls(): bool
    {
        return false; // this provider only renders pages REDO built
    }

    public function isAvailable(): bool
    {
        if ($this->apiKey === null || $this->apiKey === '' || ! is_dir(base_path('node_modules/playwright-core'))) {
            return false;
        }
        try {
            return Process::timeout(5)->run([$this->node, '-v'])->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function capture(string $htmlPath, string $outputDir, ?array $expectations = null): array
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('Browserbase is not configured (BROWSERBASE_API_KEY, Node.js and playwright-core are needed).');
        }
        File::ensureDirectoryExists($outputDir);

        $inlined = "{$outputDir}/inlined.html";
        File::put($inlined, $this->inlineLocalAssets($htmlPath));
        $command = [$this->node, base_path('scripts/visual-qa/capture.cjs'), $inlined, $outputDir];
        if ($expectations !== null) {
            File::put($path = "{$outputDir}/expectations.json", json_encode($expectations));
            $command[] = $path;
        }

        try {
            $result = Process::timeout(self::TIMEOUT_SECONDS)->path(base_path())
                ->env(array_filter(['REDO_BROWSER_MODE' => 'browserbase', 'BROWSERBASE_API_KEY' => $this->apiKey, 'BROWSERBASE_PROJECT_ID' => $this->projectId]))
                ->run($command);
        } finally {
            File::delete($inlined);
        }

        if (! $result->successful()) {
            throw new RuntimeException('Browserbase capture failed: '.mb_substr(trim($result->errorOutput()) ?: 'unknown error', 0, 300));
        }

        $viewports = [];
        foreach (explode("\n", trim($result->output())) as $line) {
            if ($line !== '' && is_array($decoded = json_decode($line, true))) {
                $viewports[] = $decoded;
            }
        }

        return $viewports;
    }
}
