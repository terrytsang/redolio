<?php

namespace Tests\Feature\Catalogue;

use App\Jobs\RunVisualQaJob;
use App\Models\VisualQaReport;
use App\Services\VisualQa\BlueprintRefiner;
use App\Services\VisualQa\BrowserlessBrowser;
use App\Services\VisualQa\BrowserService;
use App\Services\VisualQa\PlaywrightScreenshotService;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\DesignDirectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;

class VisualQaTest extends TestCase
{
    use BuildsBlueprintSpecs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DesignDirectionSeeder::class);
        $this->seed(CatalogueSeeder::class);
        Storage::fake('public');
        config(['queue.default' => 'sync', 'redo.qa_max_refinements' => 2]);
    }

    /** A provider that returns scripted per-viewport results, and records what it was asked to inspect. */
    private function fakeBrowser(callable $perViewport, array &$calls = []): BrowserService
    {
        return new class($perViewport, $calls) implements BrowserService
        {
            public function __construct(private $fn, private array &$calls) {}

            public function id(): string { return 'fake'; }

            public function isAvailable(): bool { return true; }

            public function capturesExternalUrls(): bool { return false; }

            public function capture(string $htmlPath, string $outputDir, ?array $expectations = null): array
            {
                $this->calls[] = ['html' => File::get($htmlPath), 'expectations' => $expectations];
                $out = [];
                foreach ([['desktop', 1440], ['tablet', 768], ['mobile', 390]] as [$name, $width]) {
                    $out[] = array_replace_recursive($this->baseResult($name, $width, $expectations), ($this->fn)($name, $expectations, count($this->calls)));
                }

                return $out;
            }

            private function baseResult(string $name, int $width, ?array $exp): array
            {
                return [
                    'viewport' => $name, 'width' => $width, 'height' => 900, 'screenshot_path' => null, 'load_error' => null, 'console_errors' => [], 'page_errors' => [],
                    'failed_requests' => [], 'has_horizontal_overflow' => false, 'image_count' => 0, 'broken_image_count' => 0, 'broken_image_srcs' => [],
                    'reduced_motion_ok' => $name === 'desktop' ? true : null,
                    'blueprint' => $exp ? [
                        'section_ids' => $exp['sections'], 'expected_section_ids' => $exp['sections'], 'sequence_matches' => true, 'duplicate_sections' => false, 'empty_sections' => [],
                        'broken_links' => [], 'nav_mode' => $width < 900 ? 'disclosure' : 'inline', 'nav_ok' => true, 'primary_cta_visible' => true,
                        'forms' => [['fields' => 3, 'unlabeled' => 0, 'has_target' => true, 'has_submit' => true]], 'h1_count' => 1, 'unverified_claims' => [],
                    ] : null,
                ];
            }
        };
    }

    private function qa(array $run_spec, BrowserService $browser): VisualQaReport
    {
        $this->app->instance(BrowserService::class, $browser);
        $report = VisualQaReport::create(['design_spec_id' => $run_spec['spec']->id, 'status' => 'pending', 'created_at' => now()]);
        RunVisualQaJob::dispatchSync($report->id);

        return $report->fresh();
    }

    private function fixture(array $assets = []): array
    {
        $run = $this->makeRun(null, $assets);
        [, $spec] = $this->attach($run, $this->legacySpec());

        return compact('run', 'spec');
    }

    public function test_a_healthy_blueprint_site_passes_all_checks_at_three_viewports(): void
    {
        $calls = [];
        $fx = $this->fixture();
        $report = $this->qa($fx, $this->fakeBrowser(fn () => [], $calls));
        $findings = collect($report->report_json['findings']);

        $this->assertSame('complete', $report->status);
        $this->assertSame(0, $findings->where('status', 'failed')->count());
        $this->assertEqualsCanonicalizing(['desktop', 'tablet', 'mobile'], $findings->pluck('viewport')->unique()->values()->all());
        $this->assertSame('fake', $report->report_json['provider']);
        $this->assertSame('local-services-01', $report->report_json['blueprint']['blueprint_id']);
        $this->assertTrue($findings->contains('check', 'Mobile navigation opens and lists links'));
        $this->assertTrue($findings->contains('check', 'Desktop navigation is visible'));
        $this->assertTrue($findings->contains('check', 'Reduced-motion preference is respected'));
        $this->assertTrue($findings->contains('check', 'Forms are labelled and have a submission target'));

        $exp = $calls[0]['expectations'];
        $this->assertSame(array_column($fx['spec']->design_spec_json['v15']['pages'][0]['sections'], 'instance_id'), $exp['sections']);
        $this->assertStringContainsString('gentle care for the whole family', $exp['allowed_text']);
        $this->assertStringContainsString('<style>', $calls[0]['html'], 'QA runs against the real self-contained export');
    }

    public function test_each_blueprint_check_fails_for_the_right_reason_and_severity(): void
    {
        $fx = $this->fixture();
        $report = $this->qa($fx, $this->fakeBrowser(fn (string $vp) => $vp !== 'desktop' ? [] : ['blueprint' => [
            'sequence_matches' => false, 'section_ids' => ['x'], 'empty_sections' => ['services-image-grid'], 'broken_links' => ['#nowhere'], 'nav_ok' => false,
            'primary_cta_visible' => false, 'h1_count' => 2, 'unverified_claims' => ['$99', '120 clients'],
            'forms' => [['fields' => 3, 'unlabeled' => 1, 'has_target' => true, 'has_submit' => true]],
        ], 'reduced_motion_ok' => false, 'failed_requests' => ['https://cdn.example/x.js']]));

        $failed = collect($report->report_json['findings'])->where('status', 'failed')->keyBy('check');
        foreach ([
            'Rendered sections match the selected blueprint' => 'high', 'No empty or missing sections' => 'high', 'Internal links resolve' => 'high',
            'Desktop navigation is visible' => 'high', 'Primary call to action is visible' => 'high', 'Exactly one main heading' => 'medium',
            'Forms are labelled and have a submission target' => 'medium', 'No unverified figures or claims' => 'high',
            'Reduced-motion preference is respected' => 'low', 'No failed network requests' => 'medium',
        ] as $check => $severity) {
            $this->assertTrue($failed->has($check), "expected failed check: {$check}");
            $this->assertSame($severity, $failed[$check]['severity'], $check);
        }
        $this->assertStringContainsString('$99', $failed['No unverified figures or claims']['evidence']);
    }

    public function test_a_qa_that_cannot_run_its_checks_never_reads_as_a_pass(): void
    {
        $fx = $this->fixture();
        $report = $this->qa($fx, $this->fakeBrowser(fn () => ['blueprint' => null]));
        // blueprint => null merges as "unchanged" in array_replace_recursive, so force an explicit error result:
        $report = $this->qa($fx, new class implements BrowserService
        {
            public function id(): string { return 'broken'; }

            public function isAvailable(): bool { return true; }

            public function capturesExternalUrls(): bool { return false; }

            public function capture(string $htmlPath, string $outputDir, ?array $expectations = null): array
            {
                return [['viewport' => 'desktop', 'width' => 1440, 'height' => 1000, 'screenshot_path' => null, 'load_error' => null, 'console_errors' => [], 'page_errors' => [], 'failed_requests' => [], 'has_horizontal_overflow' => false, 'image_count' => 0, 'broken_image_count' => 0, 'broken_image_srcs' => [], 'reduced_motion_ok' => null, 'blueprint' => ['error' => 'evaluate blew up']]];
            }
        });

        $failed = collect($report->report_json['findings'])->where('status', 'failed');
        $this->assertSame('Blueprint checks ran', $failed->first()['check']);
        $this->assertSame('critical', $failed->first()['severity']);
        $this->assertStringContainsString('evaluate blew up', $failed->first()['evidence']);
    }

    public function test_the_loop_fixes_an_empty_section_creates_a_new_version_and_keeps_the_old_one(): void
    {
        $fx = $this->fixture();
        $firstCall = true;
        $report = $this->qa($fx, $this->fakeBrowser(function (string $vp, ?array $exp) use (&$firstCall) {
            return $vp === 'desktop' && $exp && in_array('services-image-grid', $exp['sections'], true)
                ? ['blueprint' => ['empty_sections' => ['services-image-grid']]] : [];
        }));

        $this->assertSame(2, $report->report_json['refined_to_version']);
        $this->assertSame('Removed the empty section', $report->report_json['fixes'][0]['action']);

        $run = $fx['run']->fresh();
        $this->assertSame([1, 2], $run->designSpecs()->orderBy('version')->pluck('version')->all(), 'previous version retained');
        $v1 = $run->designSpecs()->where('version', 1)->first()->design_spec_json['v15']['pages'][0]['sections'];
        $v2 = $run->designSpecs()->where('version', 2)->first()->design_spec_json['v15'];
        $this->assertContains('services-image-grid', array_column($v1, 'instance_id'));
        $this->assertNotContains('services-image-grid', array_column($v2['pages'][0]['sections'], 'instance_id'));
        $this->assertSame(['schema_valid' => true, 'registry_valid' => true, 'content_integrity_valid' => true, 'asset_integrity_valid' => true], $v2['validation']);

        // The refined version was QA'd again, with lineage and a clean result.
        $second = VisualQaReport::where('design_spec_id', $run->designSpecs()->where('version', 2)->value('id'))->firstOrFail();
        $this->assertSame(2, $second->report_json['attempt']);
        $this->assertSame($report->id, $second->report_json['previous_report_id']);
        $this->assertSame('complete', $second->status);
        $this->assertSame(0, collect($second->report_json['findings'])->where('status', 'failed')->count());
        $this->assertCount(2, $run->designSpecs()->get());
        $this->assertSame([2], \App\Models\PreviewVersion::where('analysis_run_id', $run->id)->pluck('version_number')->all(), 'the refined version shows up in the version list');
    }

    public function test_the_loop_is_bounded_by_the_configured_number_of_refinements(): void
    {
        $fx = $this->fixture();
        $calls = [];
        // Always report the third remaining section as empty, so every pass has something fixable.
        $first = $this->qa($fx, $this->fakeBrowser(fn (string $vp, ?array $exp) => $vp === 'desktop' && $exp
            ? ['blueprint' => ['empty_sections' => [$exp['sections'][2]]]] : [], $calls));

        $reports = VisualQaReport::orderBy('id')->get();
        $this->assertCount(3, $reports, 'original + exactly two refinement passes');
        $this->assertSame([1, 2, 3], $reports->map(fn ($r) => $r->report_json['attempt'] ?? 1)->all());
        $this->assertCount(3, $calls);
        $this->assertArrayNotHasKey('refined_to_version', $reports->last()->report_json, 'no pass beyond the limit');
        $this->assertSame(3, $fx['run']->designSpecs()->count());
    }

    public function test_the_loop_stops_when_nothing_can_be_fixed_safely(): void
    {
        $fx = $this->fixture();
        $report = $this->qa($fx, $this->fakeBrowser(fn (string $vp) => $vp === 'desktop' ? ['blueprint' => ['unverified_claims' => ['$5,000'], 'h1_count' => 2], 'has_horizontal_overflow' => true] : []));

        $this->assertArrayNotHasKey('refined_to_version', $report->report_json);
        $this->assertSame(1, VisualQaReport::count());
        $this->assertSame(1, $fx['run']->designSpecs()->count());
        $this->assertGreaterThan(0, collect($report->report_json['findings'])->where('status', 'failed')->count(), 'unfixable issues stay reported');
    }

    public function test_legacy_specs_are_qad_without_blueprint_checks_or_refinement(): void
    {
        $run = $this->makeRun();
        $spec = $this->specFor($run, $this->legacySpec());
        $report = $this->qa(['spec' => $spec], $this->fakeBrowser(fn () => []));

        $this->assertNull($report->report_json['blueprint']);
        $this->assertFalse(collect($report->report_json['findings'])->contains('check', 'No empty or missing sections'));
    }

    public function test_refiner_unbinds_failed_images_repairs_dead_links_and_refuses_unsafe_results(): void
    {
        $run = $this->makeRun(null, ['hero1' => 'hero_image']);
        [, $spec] = $this->attach($run, $this->legacySpec(['hero' => ['image_asset_key' => 'hero1']]));
        $refiner = app(BlueprintRefiner::class);
        $stored = basename($run->websiteAssets()->first()->stored_path);

        $out = $refiner->refine($spec, [['check' => 'Images load', 'status' => 'failed', 'meta' => ['broken_srcs' => ["assets/ab12cd34-{$stored}"]]]]);
        $hero = collect($out['spec']->design_spec_json['v15']['pages'][0]['sections'])->firstWhere('instance_id', 'hero-service-split');
        $this->assertArrayNotHasKey('image', $hero['props']);
        $this->assertSame([], $hero['asset_bindings']);
        $this->assertStringContainsString('Unbound', $out['fixes'][0]['action']);
        $this->assertSame(2, $out['spec']->version);

        // Removing the section nav links point at would leave dead links — they are repaired, not shipped.
        $out = $refiner->refine($spec, [['check' => 'No empty or missing sections', 'status' => 'failed', 'meta' => ['instance_ids' => ['services-image-grid']]]]);
        $v15 = $out['spec']->design_spec_json['v15'];
        $nav = collect($v15['pages'][0]['sections'])->firstWhere('instance_id', 'navigation-simple');
        $this->assertNotContains('#services', array_column($nav['props']['links'], 'href'));
        $this->assertSame([], app(\App\Services\Blueprint\DesignSpecV15::class)->validate($v15, $run)['errors']);
        $this->assertContains('Repaired in-page links whose target is not on the page', array_column($out['fixes'], 'action'));

        // Nothing fixable, or ids that match nothing: no new version.
        $this->assertNull($refiner->refine($spec, [['check' => 'No unverified figures or claims', 'status' => 'failed', 'meta' => ['claims' => ['$1']]]]));
        $this->assertNull($refiner->refine($spec, [['check' => 'No empty or missing sections', 'status' => 'failed', 'meta' => ['instance_ids' => ['no-such-section']]]]));
        $this->assertNull($refiner->refine($spec, [['check' => 'Images load', 'status' => 'passed', 'meta' => []]]));
        $this->assertSame(1, $run->designSpecs()->where('version', 1)->count());
    }

    public function test_provider_selection_follows_config_and_only_remote_providers_may_load_external_urls(): void
    {
        $this->assertInstanceOf(PlaywrightScreenshotService::class, app(BrowserService::class));
        $this->assertFalse(app(BrowserService::class)->capturesExternalUrls());

        config(['redo.browser.provider' => 'browserless', 'redo.browser.browserless_token' => 'tok']);
        $remote = app(BrowserService::class);
        $this->assertInstanceOf(BrowserlessBrowser::class, $remote);
        $this->assertTrue($remote->isAvailable());
        $this->assertTrue($remote->capturesExternalUrls());

        config(['redo.browser.browserless_token' => null]);
        $this->assertFalse(app(BrowserService::class)->isAvailable());
    }

    public function test_unavailable_provider_fails_the_report_with_an_actionable_message(): void
    {
        config(['redo.browser.provider' => 'browserless', 'redo.browser.browserless_token' => null]);
        $fx = $this->fixture();
        $report = VisualQaReport::create(['design_spec_id' => $fx['spec']->id, 'status' => 'pending', 'created_at' => now()]);
        RunVisualQaJob::dispatchSync($report->id);

        $report = $report->fresh();
        $this->assertSame('failed', $report->status);
        $this->assertStringContainsString('not configured', $report->error_message);
    }

    public function test_browserless_adapter_follows_the_documented_request_contract(): void
    {
        $dir = storage_path('framework/testing/bl-'.uniqid());
        File::ensureDirectoryExists("{$dir}/assets");
        File::put("{$dir}/assets/pic.png", 'PNGDATA');
        File::put("{$dir}/index.html", '<html><body><img src="assets/pic.png"></body></html>');

        Http::fake(['*/function*' => fn ($req) => Http::response(['data' => [
            'viewport' => $req['context']['viewport']['name'], 'screenshot_base64' => base64_encode('IMG-'.$req['context']['viewport']['name']),
            'has_horizontal_overflow' => false, 'image_count' => 1, 'broken_image_count' => 0, 'blueprint' => null,
        ], 'type' => 'application/json'])]);

        try {
            $browser = new BrowserlessBrowser('https://browserless.test/', 'secret-token', 30);
            $results = $browser->capture("{$dir}/index.html", "{$dir}/out", ['sections' => ['a'], 'allowed_text' => 'x']);

            $this->assertSame(['desktop', 'tablet', 'mobile'], array_column($results, 'viewport'));
            $this->assertSame([1440, 768, 390], array_column($results, 'width'));
            $this->assertSame('IMG-tablet', File::get("{$dir}/out/tablet.png"));
            $this->assertArrayNotHasKey('screenshot_base64', $results[0]);

            Http::assertSentCount(3);
            Http::assertSent(function ($req) {
                $body = $req->data();

                return str_starts_with($req->url(), 'https://browserless.test/function?') && str_contains($req->url(), 'token=secret-token')
                    && $req->method() === 'POST'
                    && str_contains($body['code'], 'export default async function') && str_contains($body['code'], 'function inspectBlueprint')
                    && ! str_contains($body['code'], 'module.exports')
                    && str_contains($body['context']['html'], 'data:image/png;base64,'.base64_encode('PNGDATA'))
                    && $body['context']['expectations']['sections'] === ['a'];
            });
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_browserless_adapter_surfaces_provider_failures_and_misconfiguration(): void
    {
        $dir = storage_path('framework/testing/bl-'.uniqid());
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/index.html", '<html></html>');

        try {
            Http::fake(['*' => Http::response('nope', 500)]);
            $this->expectExceptionMessage('HTTP 500');
            (new BrowserlessBrowser('https://browserless.test', 'tok'))->capture("{$dir}/index.html", "{$dir}/o");
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_browserless_adapter_refuses_to_run_unconfigured(): void
    {
        $this->expectExceptionMessage('not configured');
        (new BrowserlessBrowser('https://browserless.test', null))->capture('/x', '/y');
    }

    /** Real browser, real artifact: a broken image is detected at every viewport, refined away, and re-checked clean. */
    public function test_real_browser_detects_and_refines_a_broken_image_across_three_viewports(): void
    {
        $local = app(PlaywrightScreenshotService::class);
        if (! $local->isAvailable() || ! is_dir(base_path('node_modules/playwright'))) {
            $this->markTestSkipped('Node/Playwright is not available.');
        }
        if (! Process::path(base_path())->run(['node', '-e', "require('playwright').chromium.executablePath()"])->successful()) {
            $this->markTestSkipped('Playwright is not installed.');
        }

        // The asset row exists but its file does not, so the exported <img> cannot load.
        $run = $this->makeRun(null, ['hero1' => 'hero_image']);
        [, $spec] = $this->attach($run, $this->legacySpec(['hero' => ['image_asset_key' => 'hero1']]));
        $this->assertSame('hero1', $spec->design_spec_json['v15']['pages'][0]['sections'][1]['props']['image']['asset_id']);

        $this->app->instance(BrowserService::class, $local);
        $report = VisualQaReport::create(['design_spec_id' => $spec->id, 'status' => 'pending', 'created_at' => now()]);
        RunVisualQaJob::dispatchSync($report->id);

        $first = $report->fresh();
        $images = collect($first->report_json['findings'])->where('check', 'Images load');
        $this->assertSame(['desktop', 'tablet', 'mobile'], $images->pluck('viewport')->values()->all());
        $this->assertSame(3, $images->where('status', 'failed')->count(), 'broken image caught at all three viewports');
        $this->assertCount(3, $first->report_json['screenshot_urls']);
        foreach (['desktop', 'tablet', 'mobile'] as $viewport) {
            $this->assertTrue(Storage::disk('public')->exists("visual-qa/{$run->public_id}/v1-{$viewport}.png"), "{$viewport} screenshot stored per version");
        }
        $this->assertSame(2, $first->report_json['refined_to_version']);

        $second = VisualQaReport::where('id', '>', $first->id)->firstOrFail();
        $this->assertSame('complete', $second->status);
        $this->assertSame(0, collect($second->report_json['findings'])->where('status', 'failed')->count(), json_encode(collect($second->report_json['findings'])->where('status', 'failed')->values()));
        $this->assertSame(0, collect($second->report_json['findings'])->where('check', 'Images load')->where('status', 'failed')->count());
        $this->assertTrue(collect($second->report_json['findings'])->contains('check', 'Mobile navigation opens and lists links'));
    }
}
