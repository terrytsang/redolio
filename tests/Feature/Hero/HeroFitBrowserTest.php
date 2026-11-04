<?php

namespace Tests\Feature\Hero;

use App\Models\User;
use App\Services\Editing\SpecEditService;
use App\Services\Hero\HeroLayoutValidationService;
use App\Services\VisualQa\BrowserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;

/** Real Chromium, real layout: proves the measurement sees wrapping the character-count heuristics only guess at. */
class HeroFitBrowserTest extends TestCase
{
    use BuildsBlueprintSpecs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $probe = Process::timeout(20)->path(base_path())->run(['node', '-e', "require('playwright').chromium.executablePath()"]);
        if (! $probe->successful() || ! app(BrowserService::class)->isAvailable() || app(BrowserService::class)->id() !== 'local') {
            $this->markTestSkipped('Local Playwright/Chromium is not available.');
        }
        Storage::fake('public');
    }

    public function test_a_long_headline_really_wraps_and_a_short_one_really_fits_with_unchanged_type(): void
    {
        $run = $this->makeRun();
        [$res, $spec] = $this->attach($run, $this->legacySpec());
        $this->assertTrue($res['attached']);
        $hid = collect($spec->design_spec_json['v15']['pages'][0]['sections'])->firstWhere('component_id', 'hero')['instance_id'];
        $user = User::factory()->create();
        $edit = fn ($s, $text) => app(SpecEditService::class)->apply($s, $s->version, [['op' => 'set', 'instance_id' => $hid, 'path' => 'headline', 'value' => $text]], $user)['spec'];
        $svc = app(HeroLayoutValidationService::class);

        $short = $edit($spec, 'Checkups and whitening for families');
        $a = $svc->validate($run, $short);
        if ($a['result'] === 'exception') {
            $this->markTestSkipped('Fonts were not available in this environment: '.json_encode($a['reasons']));
        }
        $long = $edit($short->fresh(), 'Preventive checkups, cosmetic whitening, same day emergency care, orthodontic consultations and gentle dentistry for every age');
        $b = $svc->validate($run, $long->fresh());

        $this->assertTrue($a['viewports']['desktop']['font_loaded']);
        $this->assertGreaterThan($a['viewports']['mobile']['h1_lines'], $b['viewports']['mobile']['h1_lines'], 'the long headline wraps to more lines on a phone');
        $this->assertGreaterThanOrEqual(5, $b['viewports']['mobile']['h1_lines']);
        $this->assertSame('fail', $b['result']);
        $this->assertContains('h1_lines', array_column($b['reasons'], 'code'));
        $this->assertEqualsWithDelta($a['font_px'], $b['font_px'], 0.5, 'type size is identical for both: nothing shrinks to hide long copy');
        $this->assertNotEmpty($b['screenshots']);
        $this->assertDatabaseHas('hero_render_validations', ['analysis_run_id' => $run->id, 'validation_result' => 'fail']);
        $this->assertDatabaseHas('hero_render_validations', ['analysis_run_id' => $run->id, 'font_loaded' => 1]);
        $this->assertTrue($a['viewports']['desktop']['cta_visible']);
    }
}
