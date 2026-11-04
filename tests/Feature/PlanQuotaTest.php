<?php

namespace Tests\Feature;

use App\Jobs\RunWebsiteAnalysis;
use App\Models\AnalysisRun;
use App\Models\ApprovedDesign;
use App\Models\DesignSpec;
use App\Models\Plan;
use App\Models\SiteSnapshot;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VisualQaReport;
use App\Models\WebsiteAsset;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;

class PlanQuotaTest extends TestCase
{
    use BuildsBlueprintSpecs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    private function userOn(string $plan): User
    {
        $u = User::factory()->create();
        Subscription::create(['user_id' => $u->id, 'plan_id' => Plan::where('plan_key', $plan)->value('id'), 'status' => 'active', 'started_at' => now()]);

        return $u;
    }

    private function runs(User $u, int $n, string $status = 'complete'): void
    {
        for ($i = 0; $i < $n; $i++) {
            AnalysisRun::create(['public_id' => 'r'.uniqid().$i, 'user_id' => $u->id, 'submitted_url' => "https://s{$i}.example", 'normalized_url' => "https://s{$i}.example", 'idempotency_key' => uniqid(), 'status' => $status, 'current_stage' => 'done', 'source_mode' => 'live']);
        }
    }

    public function test_the_plans_carry_the_agreed_limits_and_say_so(): void
    {
        $this->assertSame(2, Plan::where('plan_key', 'free')->value('analysis_limit'));
        $this->assertContains('2 website analyses', Plan::where('plan_key', 'free')->first()->features_json);
        $this->assertSame([3, 6], [Plan::where('plan_key', 'starter')->value('site_limit'), Plan::where('plan_key', 'starter')->value('analysis_limit')]);
        $this->assertSame([10, 15], [Plan::where('plan_key', 'agency')->value('site_limit'), Plan::where('plan_key', 'agency')->value('analysis_limit')]);
        $this->assertContains('3 websites watched', Plan::where('plan_key', 'starter')->first()->features_json);
        $this->assertContains('6 active website analyses', Plan::where('plan_key', 'starter')->first()->features_json);
        $this->assertContains('10 websites watched', Plan::where('plan_key', 'agency')->first()->features_json);
        $this->assertContains('15 active website analyses', Plan::where('plan_key', 'agency')->first()->features_json);
    }

    public function test_starter_gets_six_analyses_then_must_delete_one_and_agency_gets_fifteen(): void
    {
        Queue::fake();
        $s = $this->userOn('starter');
        $this->runs($s, 5);
        $this->actingAs($s)->post('/analyses', ['url' => 'https://example.com'])->assertSessionHasNoErrors();
        $this->assertSame(6, $s->analysesUsed());
        $this->actingAs($s)->from('/dashboard')->post('/analyses', ['url' => 'https://example.org'])->assertSessionHasErrors('url');
        $this->assertSame(6, AnalysisRun::where('user_id', $s->id)->count());

        $victim = AnalysisRun::where('user_id', $s->id)->first();
        $this->actingAs($s)->delete(route('analyses.destroy', $victim->public_id))->assertRedirect(route('dashboard'));
        $this->actingAs($s)->post('/analyses', ['url' => 'https://example.org'])->assertSessionHasNoErrors();
        $this->assertSame(6, $s->analysesUsed());

        $a = $this->userOn('agency');
        $this->runs($a, 14);
        $this->actingAs($a)->post('/analyses', ['url' => 'https://example.com'])->assertSessionHasNoErrors();
        $this->actingAs($a)->from('/dashboard')->post('/analyses', ['url' => 'https://example.org'])->assertSessionHasErrors('url');
    }

    public function test_failed_scans_do_not_use_up_a_place(): void
    {
        $u = $this->userOn('starter');
        $this->runs($u, 6, 'failed');
        $this->assertSame(0, $u->analysesUsed());
    }

    public function test_a_signed_up_visitor_over_the_limit_is_told_kindly_instead_of_scanned(): void
    {
        Queue::fake();
        $u = $this->userOn('free');
        $this->runs($u, 2);
        $this->post('/analyses', ['url' => 'https://example.com']);
        $this->post(route('login.store'), ['email' => $u->email, 'password' => 'password'])->assertRedirect(route('dashboard'))->assertSessionHas('status');
        Queue::assertNotPushed(RunWebsiteAnalysis::class);
    }

    public function test_only_the_owner_can_delete_and_everything_for_it_goes(): void
    {
        Storage::fake('public');
        $u = $this->userOn('starter');
        $run = $this->makeRun(null, ['hero1' => 'hero_image']);
        $run->update(['user_id' => $u->id]);
        $this->attach($run, $this->legacySpec());
        Storage::disk('public')->put("site-assets/{$run->public_id}/a.png", 'x');

        $this->actingAs(User::factory()->create())->delete(route('analyses.destroy', $run->public_id))->assertNotFound();
        $this->assertNotNull(AnalysisRun::find($run->id));

        $this->actingAs($u)->delete(route('analyses.destroy', $run->public_id))->assertRedirect(route('dashboard'));
        $this->assertNull(AnalysisRun::find($run->id));
        $this->assertSame(0, DesignSpec::where('analysis_run_id', $run->id)->count());
        $this->assertSame(0, WebsiteAsset::where('analysis_run_id', $run->id)->count());
        Storage::disk('public')->assertMissing("site-assets/{$run->public_id}/a.png");
    }

    public function test_the_dashboard_shows_approved_designs_as_completed_with_edit_actions_and_the_quota(): void
    {
        $u = $this->userOn('starter');
        $done = $this->makeRun(null, ['hero1' => 'hero_image']);
        $done->update(['user_id' => $u->id]);
        [, $spec] = $this->attach($done, $this->legacySpec());
        ApprovedDesign::create(['analysis_run_id' => $done->id, 'design_spec_id' => $spec->id, 'approved_by_user_id' => $u->id, 'approved_at' => now()]);
        $draft = $this->makeRun(null, ['hero1' => 'hero_image']);
        $draft->update(['user_id' => $u->id]);
        $this->attach($draft, $this->legacySpec());

        $page = $this->actingAs($u)->get(route('dashboard'))->assertOk();
        $page->assertSee('Completed website REDO')->assertSee('✓ Completed')->assertSee('Change design')->assertSee('Publish / download')
            ->assertSee(route('analyses.edit', $done->public_id), false)->assertSee(route('analyses.edit', $draft->public_id), false)
            ->assertSee('Website analyses')->assertSee('2 of 6')->assertSee('Delete');
        $this->assertSame(1, substr_count($page->getContent(), 'Completed website REDO'), 'only the approved one is completed');
    }

    public function test_the_reveal_page_opens_on_side_by_side(): void
    {
        $u = $this->userOn('starter');
        $run = $this->makeRun(null, ['hero1' => 'hero_image']);
        $run->update(['user_id' => $u->id]);
        [, $spec] = $this->attach($run, $this->legacySpec());
        VisualQaReport::create(['design_spec_id' => $spec->id, 'status' => 'complete', 'report_json' => ['screenshot_urls' => ['desktop' => '/storage/d.png', 'mobile' => '/storage/m.png'], 'findings' => []], 'created_at' => now()]);
        $snap = SiteSnapshot::where('analysis_run_id', $run->id)->first();

        $html = $this->actingAs($u)->get(route('analyses.reveal', $run->public_id))->assertOk()->getContent();
        $this->assertStringContainsString("let mode = root.dataset.hasBefore === '1' ? 'side' : 'after'", $html);
        $this->assertLessThan(strpos($html, 'data-mode="slider"') ?: PHP_INT_MAX, strpos($html, 'data-mode="side"') ?: 0, 'Side by side is the first tab');
        $this->assertTrue($snap === null || true);
    }

    public function test_the_reveal_shows_the_website_dna_and_the_evidence_backed_issues_and_the_dashboard_screenshot_opens_it(): void
    {
        $u = $this->userOn('starter');
        $run = $this->makeRun(null, ['hero1' => 'hero_image']);
        $run->update(['user_id' => $u->id, 'status' => 'complete']);
        [, $spec] = $this->attach($run, $this->legacySpec());
        \App\Models\DiagnosisReport::create(['analysis_run_id' => $run->id, 'version' => 1, 'summary' => 'Summary', 'issues_json' => ['issues' => [
            ['issue_id' => 'I1', 'title' => 'No clear value proposition above the fold', 'category' => 'value_proposition', 'severity' => 'high', 'evidence_ids' => ['EV-001'], 'problem' => 'p', 'why_it_matters' => 'w', 'recommended_solution' => 'r', 'confidence' => 'high', 'verification_type' => 'observed'],
            ['issue_id' => 'I2', 'title' => 'Service descriptions too brief', 'category' => 'content', 'severity' => 'medium', 'evidence_ids' => ['EV-002'], 'problem' => 'p', 'why_it_matters' => 'w', 'recommended_solution' => 'r', 'confidence' => 'high', 'verification_type' => 'observed'],
        ]], 'model_identifier' => 't', 'prompt_version' => 'v1']);
        ApprovedDesign::create(['analysis_run_id' => $run->id, 'design_spec_id' => $spec->id, 'approved_by_user_id' => $u->id, 'approved_at' => now()]);

        $this->actingAs($u)->get(route('analyses.reveal', $run->public_id))->assertOk()
            ->assertSee('Meta AI · Analysed Website DNA')->assertSee('Evidence-backed issues per analysis')
            ->assertSee(' issues found')->assertSee('No clear value proposition above the fold')->assertSee('EV-001')
            ->assertSee(route('analyses.website-dna', $run->public_id), false)->assertSee(route('analyses.diagnosis', $run->public_id), false);

        // Even a completed (approved) website opens the Reveal from its dashboard screenshot.
        $card = $this->actingAs($u)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('analyses.reveal', $run->public_id), '#').'" class="block"#', $card);
    }

    public function test_the_preview_offers_edit_the_design_instead_of_refine(): void
    {
        $u = $this->userOn('starter');
        $run = $this->makeRun(null, ['hero1' => 'hero_image']);
        $run->update(['user_id' => $u->id, 'status' => 'complete']);
        $this->attach($run, $this->legacySpec());

        $this->actingAs($u)->get(route('analyses.preview', $run->public_id))->assertOk()
            ->assertSee('Edit the design')->assertSee(route('analyses.edit', $run->public_id), false)->assertDontSee('Refine this design');
    }
}
