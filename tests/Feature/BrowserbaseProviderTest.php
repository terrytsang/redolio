<?php

namespace Tests\Feature;

use App\Services\VisualQa\BrowserbaseBrowser;
use App\Services\VisualQa\BrowserService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class BrowserbaseProviderTest extends TestCase
{
    public function test_the_provider_is_chosen_by_config_and_needs_a_key(): void
    {
        config(['redo.browser.provider' => 'browserbase', 'redo.browser.browserbase_api_key' => '']);
        $b = app(BrowserService::class);
        $this->assertInstanceOf(BrowserbaseBrowser::class, $b);
        $this->assertSame('browserbase', $b->id());
        $this->assertFalse($b->isAvailable());
        $this->assertFalse($b->capturesExternalUrls());
    }

    public function test_capture_runs_the_script_with_the_key_in_the_environment_and_inlines_local_images(): void
    {
        $dir = sys_get_temp_dir().'/bbtest'.uniqid();
        File::ensureDirectoryExists("$dir/site/assets");
        File::put("$dir/site/assets/p.png", 'PNGDATA');
        File::ensureDirectoryExists("$dir/site/assets/fonts");
        File::put("$dir/site/assets/fonts/a.woff2", 'FONTDATA');
        File::put("$dir/site/index.html", '<html><head><style>@font-face{font-family:X;src:url("assets/fonts/a.woff2") format("woff2")}</style></head><body><img src="assets/p.png"></body></html>');
        Process::fake(function ($p) {
            $cmd = is_array($p->command) ? implode(' ', $p->command) : $p->command;
            if (str_contains($cmd, '-v')) {
                return Process::result('v22.0.0');
            }
            // The page handed to the remote browser has its picture embedded, and the key is not on the command line.
            $this->assertStringNotContainsString('bb_secret', $cmd);
            $page = File::get($p->command[2]);
            $this->assertStringContainsString('data:image/png;base64,'.base64_encode('PNGDATA'), $page);
            // Fonts referenced from CSS are embedded too — without them a remote render falls back to a default font and the layout shifts.
            $this->assertStringContainsString('url("data:font/woff2;base64,'.base64_encode('FONTDATA').'")', $page);
            $this->assertStringNotContainsString('assets/fonts/a.woff2', $page);

            return Process::result(json_encode(['viewport' => 'desktop', 'width' => 1440, 'height' => 1000, 'screenshot_path' => null, 'load_error' => null])."\n".json_encode(['viewport' => 'mobile', 'width' => 390, 'height' => 844, 'screenshot_path' => null, 'load_error' => null]));
        });
        $b = new class('bb_secret', null, 'node') extends BrowserbaseBrowser
        {
            public function isAvailable(): bool
            {
                return true;
            }
        };

        $out = $b->capture("$dir/site/index.html", "$dir/out");

        $this->assertSame(['desktop', 'mobile'], array_column($out, 'viewport'));
        Process::assertRan(fn ($p) => ($p->environment['REDO_BROWSER_MODE'] ?? null) === 'browserbase' && ($p->environment['BROWSERBASE_API_KEY'] ?? null) === 'bb_secret');
        $this->assertFileDoesNotExist("$dir/out/inlined.html", 'the temporary inlined page is removed');
        File::deleteDirectory($dir);
    }

    public function test_a_failing_script_is_reported_as_a_capture_failure(): void
    {
        $dir = sys_get_temp_dir().'/bbtest'.uniqid();
        File::ensureDirectoryExists($dir);
        File::put("$dir/index.html", '<html></html>');
        Process::fake(['*' => Process::result('', 'Browserbase could not start a browser session (HTTP 401).', 1)]);
        $b = new class('k', null, 'node') extends BrowserbaseBrowser
        {
            public function isAvailable(): bool
            {
                return true;
            }
        };

        $this->expectExceptionMessage('Browserbase capture failed: Browserbase could not start a browser session (HTTP 401).');
        try {
            $b->capture("$dir/index.html", "$dir/out");
        } finally {
            File::deleteDirectory($dir);
        }
    }
}
