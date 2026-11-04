<?php

namespace App\Console\Commands;

use App\Services\Blueprint\BlueprintCatalog;
use App\Services\Blueprint\BlueprintRenderer;
use App\Services\Publishing\ProductionArtifactGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Captures desktop/tablet/mobile previews of blueprints rendered by the REAL renderer and
 * writes a QA manifest. A blueprint only counts as "ready" once its manifest records passed=true.
 * Pages are rendered and bundled (fonts included) to disk and screenshotted from file://, so no
 * web server needs to be running and nothing is recorded as site traffic.
 */
class CaptureBlueprintPreviews extends Command
{
    protected $signature = 'blueprints:capture {blueprint? : Blueprint id (default: all)} {--direction= : Direction id (default: all compatible)} {--imported : Capture the imported / captured Design Directions (database rows) with the layouts they can use}';

    protected $description = 'Capture blueprint preview screenshots and run automated layout QA';

    public function handle(BlueprintCatalog $catalog, BlueprintRenderer $renderer, ProductionArtifactGenerator $packager): int
    {
        $blueprints = $this->argument('blueprint') ? array_filter([$catalog->find($this->argument('blueprint'))]) : $catalog->all();
        if (! $blueprints) {
            $this->error('No such blueprint.');

            return self::FAILURE;
        }

        $failed = false;
        $importedIds = $this->option('imported')
            ? \App\Models\DesignDirection::where('is_selectable', true)->pluck('direction_key')->filter(fn ($k) => app(\App\Services\Catalogue\DesignCatalogue::class)->importedDirection($k) !== null)->values()->all()
            : [];
        foreach ($blueprints as $bp) {
            $directions = $this->option('direction') ? [$this->option('direction')]
                : ($this->option('imported') ? array_values(array_filter($importedIds, fn ($d) => $catalog->compatible($bp, $d))) : $bp['compatible_direction_ids']);
            foreach ($directions as $directionId) {
                $failed = ! $this->captureOne($catalog, $renderer, $packager, $bp, $directionId) || $failed;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function captureOne(BlueprintCatalog $catalog, BlueprintRenderer $renderer, ProductionArtifactGenerator $packager, array $bp, string $directionId): bool
    {
        if (! $catalog->compatible($bp, $directionId)) {
            $this->error("{$bp['id']} × {$directionId}: not a compatible pair.");

            return false;
        }

        $dir = "blueprint-previews/{$bp['id']}/{$directionId}";
        $outDir = Storage::disk('public')->path($dir);
        $work = storage_path('app/private/visual-qa-tmp/blueprint-'.$bp['id'].'-'.$directionId);
        File::deleteDirectory($work);
        File::ensureDirectoryExists("{$work}/assets");

        try {
            $html = $renderer->render($bp, $catalog->sample($bp['id'], $directionId), $directionId, ['sample_banner' => false]);
            File::put("{$work}/index.html", $packager->bundleFonts($html, "{$work}/assets/fonts"));

            // Expected order is the blueprint's, as resolved for this direction's layout choices.
            $expected = array_column($bp['section_sequence'], 'instance_id');
            $proc = Process::timeout(120)->path(base_path())->run(['node', base_path('scripts/visual-qa/capture-blueprint.cjs'), 'file://'.$work.'/index.html', $outDir, json_encode($expected)]);
        } finally {
            File::deleteDirectory($work);
        }

        if (! $proc->successful()) {
            $this->error("{$bp['id']} × {$directionId}: capture failed — ".trim($proc->errorOutput()));

            return false;
        }

        $viewports = array_values(array_filter(array_map(fn ($l) => json_decode($l, true), explode("\n", trim($proc->output())))));
        $issues = [];
        foreach ($viewports as $v) {
            $name = $v['viewport'];
            ($v['load_error'] ?? null) && $issues[] = "{$name}: load error";
            ($v['has_horizontal_overflow'] ?? false) && $issues[] = "{$name}: horizontal overflow";
            ($v['broken_image_count'] ?? 0) > 0 && $issues[] = "{$name}: broken images";
            ! empty($v['console_errors']) && $issues[] = "{$name}: console errors";
            ! empty($v['failed_requests']) && $issues[] = "{$name}: failed requests";
            ! empty($v['empty_sections']) && $issues[] = "{$name}: empty sections";
            ($v['duplicate_sections'] ?? false) && $issues[] = "{$name}: duplicate sections";
            ! ($v['sequence_matches'] ?? false) && $issues[] = "{$name}: section sequence differs from blueprint";
            ($v['h1_count'] ?? 0) !== 1 && $issues[] = "{$name}: expected exactly one h1";
        }

        $passed = count($viewports) === 3 && $issues === [];
        Storage::disk('public')->put("{$dir}/manifest.json", json_encode([
            'blueprint_id' => $bp['id'], 'blueprint_version' => $bp['version'], 'direction_id' => $directionId,
            'captured_at' => now()->toIso8601String(), 'passed' => $passed, 'issues' => $issues,
            'viewports' => array_map(fn ($v) => array_diff_key($v, ['console_errors' => 1]) + ['console_error_count' => count($v['console_errors'] ?? [])], $viewports),
        ], JSON_PRETTY_PRINT));

        $this->line(sprintf('%s × %s: %s %s', $bp['id'], $directionId, $passed ? '<info>PASS</info>' : '<error>FAIL</error>', $issues ? '('.implode('; ', $issues).')' : ''));

        return $passed;
    }
}
