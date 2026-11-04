<?php

namespace Tests\Feature;

use App\Services\VisualQa\BrowserService;
use App\Services\VisualQa\FallbackBrowser;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class FallbackBrowserTest extends TestCase
{
    private function fake(string $id, bool $available, ?\Closure $capture = null): BrowserService
    {
        return new class($id, $available, $capture) implements BrowserService
        {
            public int $calls = 0;

            public function __construct(private string $id, private bool $available, private ?\Closure $capture) {}

            public function id(): string
            {
                return $this->id;
            }

            public function isAvailable(): bool
            {
                return $this->available;
            }

            public function capturesExternalUrls(): bool
            {
                return false;
            }

            public function capture(string $htmlPath, string $outputDir, ?array $expectations = null): array
            {
                $this->calls++;

                return $this->capture ? ($this->capture)() : [['viewport' => 'desktop', 'by' => $this->id]];
            }
        };
    }

    public function test_the_backup_takes_over_when_the_primary_fails_and_the_primary_is_skipped_for_a_while(): void
    {
        Cache::flush();
        $primary = $this->fake('browserbase', true, fn () => throw new RuntimeException('Browserbase could not start a browser session (HTTP 402).'));
        $backup = $this->fake('browserless', true);
        $b = new FallbackBrowser($primary, $backup);

        $this->assertSame('browserless', $b->capture('x', 'y')[0]['by']);
        $this->assertSame('browserless', $b->id(), 'reports record the provider that really captured');
        $b->capture('x', 'y');
        $this->assertSame(1, $primary->calls, 'after a failure the primary is skipped for a while');
        $this->assertSame(2, $backup->calls);
    }

    public function test_a_working_primary_is_used_and_the_backup_untouched(): void
    {
        Cache::flush();
        $primary = $this->fake('browserbase', true);
        $backup = $this->fake('browserless', true);
        $b = new FallbackBrowser($primary, $backup);

        $this->assertSame('browserbase', $b->capture('x', 'y')[0]['by']);
        $this->assertSame('browserbase', $b->id());
        $this->assertSame(0, $backup->calls);
    }

    public function test_an_unconfigured_primary_goes_straight_to_the_backup_and_both_failing_surfaces_the_error(): void
    {
        Cache::flush();
        $this->assertSame('browserless', (new FallbackBrowser($this->fake('browserbase', false), $this->fake('browserless', true)))->capture('x', 'y')[0]['by']);

        Cache::flush();
        $bad = fn () => throw new RuntimeException('down');
        $b = new FallbackBrowser($this->fake('browserbase', true, $bad), $this->fake('browserless', true, fn () => throw new RuntimeException('also down')));
        $this->expectExceptionMessage('also down');
        $b->capture('x', 'y');
    }

    public function test_the_container_wraps_the_provider_when_a_fallback_is_configured(): void
    {
        config(['redo.browser.provider' => 'browserbase', 'redo.browser.fallback' => 'browserless']);
        $this->assertInstanceOf(FallbackBrowser::class, app(BrowserService::class));

        config(['redo.browser.fallback' => '']);
        $this->assertNotInstanceOf(FallbackBrowser::class, app(BrowserService::class));
    }
}
