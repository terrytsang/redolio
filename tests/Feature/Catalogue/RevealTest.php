<?php

namespace Tests\Feature\Catalogue;

use App\Models\Plan;
use App\Models\SiteSnapshot;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VisualQaReport;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\DesignDirectionSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;

class RevealTest extends TestCase
{
    use BuildsBlueprintSpecs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DesignDirectionSeeder::class);
        $this->seed(CatalogueSeeder::class);
        $this->seed(PlanSeeder::class);
        Storage::fake('public');
    }

    private function revealFor(bool $withShots, ?string $before = 'https://img.test/before.png', string $mode = 'live'): array
    {
        $run = $this->makeRun();
        $run->update(['source_mode' => $mode]);
        [, $spec] = $this->attach($run, $this->legacySpec());
        $before && SiteSnapshot::create(['analysis_run_id' => $run->id, 'snapshot_hash' => 'h', 'homepage_url' => 'https://x.test', 'page_count' => 1, 'snapshot_json' => [], 'screenshot_path' => $before]);
        if ($withShots) {
            VisualQaReport::create(['design_spec_id' => $spec->id, 'status' => 'complete', 'created_at' => now(), 'report_json' => [
                'screenshot_urls' => ['desktop' => 'https://img.test/d.png', 'tablet' => 'https://img.test/t.png', 'mobile' => 'https://img.test/m.png'],
                'findings' => [['check' => 'a', 'status' => 'passed'], ['check' => 'b', 'status' => 'passed'], ['check' => 'c', 'status' => 'failed']],
                'fixes' => [['action' => 'Removed the empty section', 'target' => 'x']],
            ]]);
        }

        return [$run, $spec];
    }

    public function test_without_a_spec_it_redirects_back_to_the_analysis(): void
    {
        $run = $this->makeRun();
        $this->get("/analyses/{$run->public_id}/reveal")->assertRedirect(route('analyses.show', $run->public_id));
    }

    public function test_without_screenshots_it_prepares_them_automatically_instead_of_showing_nothing(): void
    {
        [$run, $spec] = $this->revealFor(false);

        $this->get("/analyses/{$run->public_id}/reveal")->assertOk()
            ->assertSee('Preparing your before', false)
            ->assertSee("/analyses/{$run->public_id}/preview/{$spec->version}/visual-qa", false)
            ->assertDontSee('id="reveal-device-frame"', false);
    }

    public function test_with_screenshots_it_offers_the_slider_side_by_side_and_all_three_devices(): void
    {
        [$run] = $this->revealFor(true);

        $this->get("/analyses/{$run->public_id}/reveal")->assertOk()
            ->assertSee('Slide to compare')->assertSee('Side by side')->assertSee('After only')
            ->assertSee('data-device="desktop"', false)->assertSee('data-device="tablet"', false)->assertSee('data-device="mobile"', false)
            ->assertSee('role="slider"', false)->assertSee('aria-valuenow', false)
            ->assertSee('https://img.test/before.png', false)->assertSee('https://img.test/d.png', false)
            ->assertSee('The original page ends here')
            ->assertSee('Local Services Trust Builder')
            ->assertSee('1 issue(s) fixed automatically.')->assertSee('left out — nothing invented');
    }

    public function test_without_an_original_capture_it_says_so_and_still_shows_the_redesign(): void
    {
        [$run] = $this->revealFor(true, before: null);

        $this->get("/analyses/{$run->public_id}/reveal")->assertOk()
            ->assertSee('could not be captured')->assertDontSee('role="slider"', false)->assertDontSee('Slide to compare')
            ->assertSee('https://img.test/d.png', false);
    }

    public function test_demo_fixtures_label_the_before_as_illustrative(): void
    {
        [$live] = $this->revealFor(true);
        $this->get("/analyses/{$live->public_id}/reveal")->assertDontSee('Demo mode');

        [$demo] = $this->revealFor(true, mode: 'fixture');
        $this->get("/analyses/{$demo->public_id}/reveal")->assertSee('Demo mode')->assertSee('illustrative rendering');
    }

    public function test_guests_and_free_users_see_the_paid_plans_and_paid_users_see_the_publish_and_download_action(): void
    {
        [$run] = $this->revealFor(true);
        $guest = $this->get("/analyses/{$run->public_id}/reveal");
        $guest->assertSee('Starter — $29/mo')->assertSee('Agency — $99/mo')->assertSee('Publish + download HTML');

        $user = User::factory()->create();
        Subscription::create(['user_id' => $user->id, 'plan_id' => Plan::where('plan_key', 'starter')->value('id'), 'status' => 'active', 'started_at' => now()]);
        $this->actingAs($user)->get("/analyses/{$run->public_id}/reveal")
            ->assertSee('Publish or download HTML')->assertDontSee('Starter — $29/mo');
    }
}
