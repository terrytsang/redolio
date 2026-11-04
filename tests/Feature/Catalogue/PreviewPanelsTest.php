<?php

namespace Tests\Feature\Catalogue;

use App\Models\SiteSnapshot;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\DesignDirectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;

/** The BEFORE / AFTER panels of the preview page: real desktop views, no third-party placeholders. */
class PreviewPanelsTest extends TestCase
{
    use BuildsBlueprintSpecs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DesignDirectionSeeder::class);
        $this->seed(CatalogueSeeder::class);
        Storage::fake('public');
    }

    private function page(?string $screenshot): string
    {
        $run = $this->makeRun();
        $this->attach($run, $this->legacySpec());
        SiteSnapshot::create(['analysis_run_id' => $run->id, 'snapshot_hash' => 'h', 'homepage_url' => 'https://brightleaf.example/', 'page_count' => 1, 'snapshot_json' => [], 'screenshot_path' => $screenshot]);

        return $this->get("/analyses/{$run->public_id}/preview")->assertOk()->getContent();
    }

    public function test_before_shows_our_own_screenshot_of_the_site(): void
    {
        $html = $this->page('http://app.test/storage/site-captures/abc/desktop.png');

        $this->assertStringContainsString('src="http://app.test/storage/site-captures/abc/desktop.png"', $html);
        $this->assertStringNotContainsString("isn't available", $html);
    }

    public function test_before_never_shows_the_third_party_generating_preview_placeholder(): void
    {
        foreach (['https://s0.wp.com/mshots/v1/https%3A%2F%2Fanoz.co?w=1200', null] as $stored) {
            $html = $this->page($stored);
            $this->assertStringNotContainsString('mshots', $html);
            $this->assertStringContainsString("A screenshot of your current website isn't available.", $html);
            $this->assertStringContainsString('Open your current website', $html);
            $this->assertStringContainsString('href="https://brightleaf.example', $html);
        }
    }

    public function test_after_is_a_desktop_width_page_scaled_to_fit_its_panel_not_a_mobile_layout(): void
    {
        $html = $this->page(null);

        $this->assertStringContainsString('class="bp-frame-host"', $html);
        $this->assertStringContainsString('data-desktop-width="1440"', $html);
        $this->assertStringContainsString('width:1440px', $html);
        $this->assertStringContainsString('transform-origin:0 0', $html);
        $this->assertStringContainsString('window.redoFitFrames', $html, 'the page scales the frame to the panel width');
        $this->assertStringContainsString('data-frame-viewport', $html);
        $this->assertStringNotContainsString('<div class="p-4"><div class="bp-frame-host"', preg_replace('/\s+/', ' ', $html), 'no padding squeezes the desktop frame');
    }
}
