<?php

namespace App\Services\VisualQa;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs the primary remote browser and, when it fails (free quota used up, plan limit, outage), carries on with a backup provider
 * so screenshots keep working. After a primary failure the primary is skipped for a while, so people aren't kept waiting on a
 * provider that is out of quota; it is tried again automatically afterwards.
 */
class FallbackBrowser implements BrowserService
{
    private const COOLDOWN_MINUTES = 15;

    private BrowserService $last;

    public function __construct(private readonly BrowserService $primary, private readonly BrowserService $backup)
    {
        $this->last = $primary;
    }

    /** The provider that actually did the last capture (stored with each report). */
    public function id(): string
    {
        return $this->last->id();
    }

    public function isAvailable(): bool
    {
        return $this->primary->isAvailable() || $this->backup->isAvailable();
    }

    public function capturesExternalUrls(): bool
    {
        return $this->last->capturesExternalUrls();
    }

    public function capture(string $htmlPath, string $outputDir, ?array $expectations = null): array
    {
        $key = 'browser-primary-down:'.$this->primary->id();

        if ($this->primary->isAvailable() && ! Cache::has($key)) {
            try {
                $this->last = $this->primary;

                return $this->primary->capture($htmlPath, $outputDir, $expectations);
            } catch (Throwable $e) {
                Cache::put($key, true, now()->addMinutes(self::COOLDOWN_MINUTES));
                Log::warning('Primary browser failed; switching to the backup', ['primary' => $this->primary->id(), 'backup' => $this->backup->id(), 'error' => mb_substr($e->getMessage(), 0, 300)]);
                if (! $this->backup->isAvailable()) {
                    throw $e;
                }
            }
        }

        $this->last = $this->backup;

        return $this->backup->capture($htmlPath, $outputDir, $expectations);
    }
}
