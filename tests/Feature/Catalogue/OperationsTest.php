<?php

namespace Tests\Feature\Catalogue;

use App\Jobs\RunVisualQaJob;
use App\Models\Export;
use App\Models\VisualQaReport;
use App\Services\VisualQa\BrowserService;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\DesignDirectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;

/** Phase 6: failure handling, cost limits, retention and the repeatable demo. */
class OperationsTest extends TestCase
{
    use BuildsBlueprintSpecs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DesignDirectionSeeder::class);
        $this->seed(CatalogueSeeder::class);
        Storage::fake('public');
        Storage::fake('local');
    }

    private function site(): array
    {
        $run = $this->makeRun();
        [, $spec] = $this->attach($run, $this->legacySpec());

        return [$run, $spec];
    }

    private function brokenBrowser(string $message): BrowserService
    {
        return new class($message) implements BrowserService
        {
            public function __construct(private string $m) {}

            public function id(): string { return 'broken'; }

            public function isAvailable(): bool { return true; }

            public function capturesExternalUrls(): bool { return false; }

            public function capture(string $htmlPath, string $outputDir, ?array $expectations = null): array
            {
                throw new \RuntimeException($this->m);
            }
        };
    }

    public function test_a_browser_outage_fails_the_report_cleanly_without_leaking_detail_or_temp_files(): void
    {
        [$run, $spec] = $this->site();
        $this->app->instance(BrowserService::class, $this->brokenBrowser('Browserless HTTP 503 at https://secret.internal/token=abc'));
        $report = VisualQaReport::create(['design_spec_id' => $spec->id, 'status' => 'pending', 'created_at' => now()]);

        RunVisualQaJob::dispatchSync($report->id);

        $report = $report->fresh();
        $this->assertSame('failed', $report->status);
        $this->assertStringNotContainsString('secret.internal', (string) $report->error_message);
        $this->assertStringNotContainsString('token=abc', (string) $report->error_message);
        $this->assertSame(1, VisualQaReport::count(), 'a failed run does not spawn refinement passes');
        $this->assertSame([], glob(storage_path('app/private/visual-qa-tmp/*')) ?: [], 'temp build directories are removed even on failure');
    }

    public function test_a_killed_worker_marks_the_report_failed_via_the_failed_hook(): void
    {
        [, $spec] = $this->site();
        $report = VisualQaReport::create(['design_spec_id' => $spec->id, 'status' => 'running', 'created_at' => now()]);

        (new RunVisualQaJob($report->id))->failed(new \RuntimeException('timed out'));
        $this->assertSame('failed', $report->fresh()->status);

        // It never overwrites a finished report.
        $done = VisualQaReport::create(['design_spec_id' => $spec->id, 'status' => 'complete', 'created_at' => now(), 'report_json' => ['findings' => []]]);
        (new RunVisualQaJob($done->id))->failed(null);
        $this->assertSame('complete', $done->fresh()->status);
    }

    public function test_status_polling_gives_up_on_a_dead_worker_instead_of_spinning_forever(): void
    {
        [$run, $spec] = $this->site();
        $stale = VisualQaReport::create(['design_spec_id' => $spec->id, 'status' => 'running', 'created_at' => now()->subMinutes(30)]);
        $fresh = VisualQaReport::create(['design_spec_id' => $spec->id, 'status' => 'running', 'created_at' => now()->subMinute()]);

        $this->getJson("/analyses/{$run->public_id}/visual-qa/{$stale->id}")->assertOk()->assertJsonPath('status', 'failed')->assertJsonPath('error_message', 'Visual QA timed out. Please run it again.');
        $this->getJson("/analyses/{$run->public_id}/visual-qa/{$fresh->id}")->assertOk()->assertJsonPath('status', 'running');
    }

    public function test_starting_qa_twice_returns_the_run_already_in_flight(): void
    {
        Queue::fake();
        [$run, $spec] = $this->site();

        $first = $this->postJson("/analyses/{$run->public_id}/preview/{$spec->version}/visual-qa")->assertOk()->json('report_id');
        $second = $this->postJson("/analyses/{$run->public_id}/preview/{$spec->version}/visual-qa")->assertOk()->json('report_id');

        $this->assertSame($first, $second);
        $this->assertSame(1, VisualQaReport::count());
        Queue::assertPushed(RunVisualQaJob::class, 1);
    }

    public function test_browser_time_is_capped_per_project_per_day(): void
    {
        Queue::fake();
        config(['redo.qa_max_reports_per_run_per_day' => 2]);
        [$run, $spec] = $this->site();
        foreach ([1, 2] as $_) {
            VisualQaReport::create(['design_spec_id' => $spec->id, 'status' => 'complete', 'created_at' => now(), 'report_json' => []]);
        }

        $this->postJson("/analyses/{$run->public_id}/preview/{$spec->version}/visual-qa")->assertStatus(429)->assertJsonPath('message', fn ($m) => str_contains($m, 'daily visual QA limit'));
        $this->assertSame(2, VisualQaReport::count());
        Queue::assertNothingPushed();
    }

    public function test_retention_removes_only_what_is_past_its_window(): void
    {
        config(['redo.retention' => ['qa_screenshot_days' => 30, 'temp_hours' => 24, 'export_days_after_expiry' => 7]]);
        $public = Storage::disk('public');
        $public->put('visual-qa/run-a/v1-desktop.png', 'old');
        $public->put('visual-qa/run-a/v2-desktop.png', 'new');
        $public->put('blueprint-previews/saas-product-01/product-pulse/desktop.png', 'catalogue');
        $public->put('direction-previews/auteur/hero.png', 'catalogue');
        touch($public->path('visual-qa/run-a/v1-desktop.png'), now()->subDays(45)->getTimestamp());
        touch($public->path('blueprint-previews/saas-product-01/product-pulse/desktop.png'), now()->subDays(400)->getTimestamp());

        $oldTmp = storage_path('app/private/visual-qa-tmp/ops-old');
        $newTmp = storage_path('app/private/visual-qa-tmp/ops-new');
        File::ensureDirectoryExists($oldTmp);
        File::ensureDirectoryExists($newTmp);
        touch($oldTmp, now()->subDays(3)->getTimestamp());

        $run = $this->makeRun();
        Storage::disk('local')->put('exports/expired.zip', 'zip');
        Storage::disk('local')->put('exports/current.zip', 'zip');
        $expired = Export::create(['analysis_run_id' => $run->id, 'design_spec_id' => $this->specFor($run, $this->legacySpec())->id, 'format' => 'static_html', 'storage_path' => 'exports/expired.zip', 'download_token_hash' => 'a', 'expires_at' => now()->subDays(10)]);
        $current = Export::create(['analysis_run_id' => $run->id, 'design_spec_id' => $expired->design_spec_id, 'format' => 'static_html', 'storage_path' => 'exports/current.zip', 'download_token_hash' => 'b', 'expires_at' => now()->subDays(2)]);

        try {
            $this->artisan('redo:prune', ['--dry-run' => true])->expectsOutputToContain('would remove: 1 QA screenshot(s), 1 temp item(s), 1 expired export(s)')->assertSuccessful();
            $this->assertTrue($public->exists('visual-qa/run-a/v1-desktop.png'), 'dry run deletes nothing');
            $this->assertDirectoryExists($oldTmp);

            $this->artisan('redo:prune')->expectsOutputToContain('Removed: 1 QA screenshot(s), 1 temp item(s), 1 expired export(s)')->assertSuccessful();

            $this->assertFalse($public->exists('visual-qa/run-a/v1-desktop.png'));
            $this->assertTrue($public->exists('visual-qa/run-a/v2-desktop.png'));
            $this->assertTrue($public->exists('blueprint-previews/saas-product-01/product-pulse/desktop.png'), 'catalogue previews are never pruned');
            $this->assertTrue($public->exists('direction-previews/auteur/hero.png'), 'direction screenshots are never pruned');
            $this->assertDirectoryDoesNotExist($oldTmp);
            $this->assertDirectoryExists($newTmp);
            $this->assertFalse(Storage::disk('local')->exists('exports/expired.zip'));
            $this->assertNull(Export::find($expired->id));
            $this->assertNotNull(Export::find($current->id), 'recently expired exports keep their grace period');
        } finally {
            File::deleteDirectory($oldTmp);
            File::deleteDirectory($newTmp);
        }
    }

    public function test_retention_is_scheduled_daily(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map(fn ($e) => $e->command)->implode("\n");
        $this->assertStringContainsString('redo:prune', $events);
    }

    public function test_the_demo_command_runs_clean_end_to_end_and_is_repeatable(): void
    {
        // A scripted browser stands in for Chromium: the command's own logic is what is under test.
        $this->app->instance(BrowserService::class, new class implements BrowserService
        {
            public function id(): string { return 'scripted'; }

            public function isAvailable(): bool { return true; }

            public function capturesExternalUrls(): bool { return false; }

            public function capture(string $htmlPath, string $outputDir, ?array $expectations = null): array
            {
                $out = [];
                foreach ([['desktop', 1440], ['tablet', 768], ['mobile', 390]] as [$n, $w]) {
                    $ids = $expectations['sections'] ?? [];
                    $out[] = ['viewport' => $n, 'width' => $w, 'height' => 900, 'screenshot_path' => null, 'load_error' => null, 'console_errors' => [], 'page_errors' => [], 'failed_requests' => [], 'has_horizontal_overflow' => false, 'image_count' => 0, 'broken_image_count' => 0, 'broken_image_srcs' => [], 'reduced_motion_ok' => $n === 'desktop' ? true : null,
                        'blueprint' => $expectations ? ['section_ids' => $ids, 'expected_section_ids' => $ids, 'sequence_matches' => true, 'duplicate_sections' => false, 'empty_sections' => [], 'broken_links' => [], 'nav_mode' => $w < 900 ? 'disclosure' : 'inline', 'nav_ok' => true, 'primary_cta_visible' => true, 'forms' => [], 'h1_count' => 1, 'unverified_claims' => []] : null];
                }

                return $out;
            }
        });
        // Directions must have a ready layout; in this test the (faked) browser makes capture a no-op, so mark one ready.
        foreach (['trusted-expertise', 'executive-clarity', 'modern-heritage', 'studio-editorial'] as $d) {
            foreach (['local-services-01', 'executive-corporate-01'] as $bp) {
                Storage::disk('public')->put("blueprint-previews/{$bp}/{$d}/manifest.json", json_encode(['passed' => true]));
            }
        }

        foreach ([1, 2] as $_) {
            $this->artisan('redo:demo', ['fixture' => 'brightleaf-dental', '--direction' => 'trusted-expertise'])
                ->expectsOutputToContain('Trusted Expertise')
                ->expectsOutputToContain('DesignSpec 1.5')
                ->expectsOutputToContain('29/29 checks passed')
                ->expectsOutputToContain('Before & after')
                ->assertSuccessful();
        }
        $this->assertSame(2, \App\Models\AnalysisRun::count(), 'each run is a fresh project');
        $this->assertSame(2, \App\Models\DesignSelection::count());

        $this->artisan('redo:demo', ['fixture' => 'no-such-fixture'])->expectsOutputToContain('Unknown fixture')->assertFailed();
        $this->artisan('redo:demo', ['fixture' => 'brightleaf-dental', '--direction' => 'nope'])->expectsOutputToContain('Unknown direction')->assertFailed();
    }
}
