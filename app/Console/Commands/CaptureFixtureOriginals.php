<?php

namespace App\Console\Commands;

use App\Models\DemoFixture;
use App\Services\Fixtures\FixtureOriginalRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/** Renders and screenshots the illustrative "original site" for each demo fixture (needs Node + Playwright). */
class CaptureFixtureOriginals extends Command
{
    protected $signature = 'fixtures:capture-originals';

    protected $description = 'Capture illustrative original-site screenshots for the demo fixtures';

    public function handle(FixtureOriginalRenderer $renderer): int
    {
        foreach (DemoFixture::all() as $fixture) {
            $dir = storage_path('app/private/fixture-originals');
            File::ensureDirectoryExists($dir);
            $html = "{$dir}/{$fixture->fixture_key}.html";
            File::put($html, $renderer->render($fixture->fixture_payload_json));

            $out = Storage::disk('public')->path("fixture-originals/{$fixture->fixture_key}.png");
            $result = Process::timeout(60)->path(base_path())->run(['node', base_path('scripts/visual-qa/capture-page.cjs'), $html, $out, '1440']);
            File::delete($html);

            if (! $result->successful()) {
                $this->error("{$fixture->fixture_key}: ".trim($result->errorOutput()));

                return self::FAILURE;
            }
            $this->line("{$fixture->fixture_key}: <info>captured</info>");
        }

        return self::SUCCESS;
    }
}
