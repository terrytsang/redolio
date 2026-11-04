<?php

namespace Tests\Feature\Catalogue;

use App\Jobs\GenerateDesignSpecJob;
use App\Models\AnalysisRun;
use App\Models\DesignDirection;
use App\Models\DesignSelection;
use App\Models\WebsiteAsset;
use App\Models\WebsiteDna;
use App\Services\Catalogue\DesignCatalogue;
use App\Services\Recommendation\BlueprintRecommender;
use App\Services\Recommendation\DirectionScorer;
use App\Services\Recommendation\ProjectSignals;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\DesignDirectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecommendationAndSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DesignDirectionSeeder::class);
        $this->seed(CatalogueSeeder::class);
        Storage::fake('public');
        // Blueprints only count as selectable once preview QA has passed.
        foreach (['saas-product-01' => ['product-pulse', 'datacanvas', 'launch-signal', 'executive-clarity'], 'local-services-01' => ['trusted-expertise', 'executive-clarity', 'modern-heritage', 'studio-editorial']] as $bp => $dirs) {
            foreach ($dirs as $d) {
                Storage::disk('public')->put("blueprint-previews/{$bp}/{$d}/manifest.json", json_encode(['passed' => true]));
            }
        }
    }

    private function makeRun(array $dna = [], int $realImages = 0): AnalysisRun
    {
        $run = AnalysisRun::create([
            'public_id' => 'run_'.uniqid(), 'submitted_url' => 'https://example.test', 'normalized_url' => 'https://example.test',
            'idempotency_key' => uniqid(), 'status' => 'completed', 'current_stage' => 'done', 'source_mode' => 'live',
        ]);
        $dna && WebsiteDna::create(['analysis_run_id' => $run->id, 'version' => 1, 'dna_json' => $dna, 'model_identifier' => 'test', 'prompt_version' => 'v1']);
        for ($i = 0; $i < $realImages; $i++) {
            WebsiteAsset::create(['analysis_run_id' => $run->id, 'asset_key' => "a{$i}", 'source_page_url' => 'https://example.test', 'original_url' => "https://example.test/{$i}.jpg", 'stored_path' => "assets/a{$i}.jpg", 'asset_type' => 'portfolio_image', 'created_at' => now()]);
        }

        return $run;
    }

    private function saasDna(): array
    {
        return [
            'industry' => 'B2B SaaS analytics platform', 'business_summary' => 'Software platform with automation and dashboards for developer teams.',
            'target_audience' => [['segment' => 'developer teams', 'needs' => ['analytics']]], 'offerings' => [['name' => 'Analytics', 'description' => 'dashboards'], ['name' => 'API']],
            'primary_conversion_goal' => 'lead', 'trust_signals' => [['type' => 'client_logo']], 'tone_of_voice' => ['precise'],
        ];
    }

    public function test_scoring_weights_sum_to_one_and_follow_the_prd(): void
    {
        $this->assertEqualsWithDelta(1.0, array_sum(DirectionScorer::WEIGHTS), 0.0001);
        $this->assertSame(['business_fit' => 0.25, 'audience_fit' => 0.2, 'brand_continuity' => 0.2, 'conversion_goal_fit' => 0.15, 'asset_fit' => 0.1, 'content_fit' => 0.1], DirectionScorer::WEIGHTS);
    }

    public function test_saas_project_scores_a_saas_direction_above_a_shop_direction_with_evidence(): void
    {
        $signals = ProjectSignals::fromRun($this->makeRun($this->saasDna()));
        $cat = app(DesignCatalogue::class);
        $scorer = app(DirectionScorer::class);

        $pulse = $scorer->score($cat->direction('product-pulse'), $signals);
        $maison = $scorer->score($cat->direction('maison-market'), $signals);

        $this->assertGreaterThan($maison['total'], $pulse['total']);
        $this->assertNotEmpty($pulse['reasons']);
        $this->assertNotEmpty($maison['cautions'], 'image-led direction without photos should carry a caution');
        $this->assertSame('saas', $signals->inferCategory($cat));
    }

    public function test_image_led_direction_improves_with_real_photos(): void
    {
        $cat = app(DesignCatalogue::class);
        $scorer = app(DirectionScorer::class);
        $none = $scorer->score($cat->direction('auteur'), ProjectSignals::fromRun($this->makeRun($this->saasDna(), 0)));
        $many = $scorer->score($cat->direction('auteur'), ProjectSignals::fromRun($this->makeRun($this->saasDna(), 6)));

        $this->assertGreaterThan($none['factors']['asset_fit'], $many['factors']['asset_fit']);
    }

    public function test_missing_dna_gives_neutral_scores_not_errors(): void
    {
        $s = ProjectSignals::fromRun($this->makeRun());
        $r = app(DirectionScorer::class)->score(app(DesignCatalogue::class)->direction('executive-clarity'), $s);
        $this->assertEqualsWithDelta(0.5, $r['factors']['business_fit'], 0.0001);
    }

    public function test_blueprint_recommendation_ranks_by_fit_and_reports_missing_content_without_inventing_it(): void
    {
        $signals = ProjectSignals::fromRun($this->makeRun($this->saasDna(), 0));
        $recs = app(BlueprintRecommender::class)->recommend('executive-clarity', $signals);

        $this->assertSame('saas-product-01', $recs[0]['blueprint']['id'], 'SaaS site should be offered the SaaS blueprint first');
        $this->assertLessThanOrEqual(3, count($recs));
        $missing = implode(' | ', $recs[0]['missing_content']);
        $this->assertStringContainsString('Pricing is never invented', $missing);
        $this->assertStringContainsString('gallery', strtolower($missing));
    }

    public function test_only_ready_blueprints_are_eligible(): void
    {
        $this->assertSame(['saas-product-01'], array_column(app(BlueprintRecommender::class)->eligible('product-pulse'), 'id'));

        Storage::fake('public'); // wipes manifests; then previews exist but none has passed QA
        Storage::disk('public')->put('blueprint-previews/saas-product-01/product-pulse/manifest.json', json_encode(['passed' => false]));
        $this->assertSame([], app(BlueprintRecommender::class)->eligible('product-pulse'));
    }

    public function test_selection_persists_exact_versions_and_what_was_offered(): void
    {
        Queue::fake();
        $run = $this->makeRun($this->saasDna());

        $this->post("/analyses/{$run->public_id}/design-directions", [
            'direction_key' => 'product-pulse', 'blueprint_id' => 'saas-product-01', 'category_id' => 'saas', 'preserve_logo' => 1,
        ])->assertRedirect();

        $sel = DesignSelection::firstOrFail();
        $this->assertSame('product-pulse', $sel->direction_key);
        $this->assertSame(1, $sel->direction_version);
        $this->assertSame('saas-product-01', $sel->blueprint_id);
        $this->assertSame(1, $sel->blueprint_version);
        $this->assertSame('saas', $sel->category_id);
        $this->assertSame(app(DesignCatalogue::class)->version(), $sel->catalogue_version);
        $this->assertSame(['saas-product-01'], $sel->selection_context_json['eligible_blueprint_ids']);
        $this->assertTrue($sel->preferences_json['preserve_logo']);
        Queue::assertPushed(GenerateDesignSpecJob::class);
    }

    public function test_user_can_override_the_recommended_direction_and_it_is_recorded(): void
    {
        Queue::fake();
        $run = $this->makeRun($this->saasDna());
        $run->designDirectionRecommendations()->create(['direction_key' => 'product-pulse', 'rank' => 1, 'is_recommended' => true, 'rationale' => 'x', 'created_at' => now()]);

        $this->post("/analyses/{$run->public_id}/design-directions", ['direction_key' => 'maison-market'])->assertRedirect();

        $sel = DesignSelection::firstOrFail();
        $this->assertTrue($sel->selection_context_json['direction_overrode_recommendation']);
        $this->assertNull($sel->blueprint_id, 'directions without a ready blueprint can still be selected');
    }

    public function test_selection_rejects_unknown_incompatible_or_unready_options(): void
    {
        Queue::fake();
        $run = $this->makeRun($this->saasDna());
        $url = "/analyses/{$run->public_id}/design-directions";

        $this->post($url, ['direction_key' => 'nope'])->assertSessionHasErrors('direction_key');
        $this->post($url, ['direction_key' => 'product-pulse', 'blueprint_id' => 'local-services-01'])->assertSessionHasErrors('blueprint_id');
        $this->post($url, ['direction_key' => 'product-pulse', 'blueprint_id' => 'surf-school-editorial-01'])->assertSessionHasErrors('blueprint_id');
        $this->post($url, ['direction_key' => 'product-pulse', 'category_id' => 'nope'])->assertSessionHasErrors('category_id');
        $this->post($url, ['direction_key' => 'modern_corporate'])->assertSessionHasErrors('direction_key'); // retired legacy
        $this->assertSame(0, DesignSelection::count());
    }

    public function test_direction_page_filters_by_category_without_restricting_choice(): void
    {
        $run = $this->makeRun($this->saasDna());
        $this->get("/analyses/{$run->public_id}/design-directions")->assertOk()->assertSee('Website type');
        $page = $this->get("/analyses/{$run->public_id}/design-directions?category=landing");
        $page->assertOk()->assertSee('Launch Signal')->assertDontSee('Maison Market');

        // …but a direction outside the filter is still accepted on selection.
        Queue::fake();
        $this->post("/analyses/{$run->public_id}/design-directions", ['direction_key' => 'maison-market', 'category_id' => 'landing'])->assertRedirect();
        $this->assertSame('maison-market', DesignSelection::firstOrFail()->direction_key);
    }

    public function test_layout_step_lists_recommended_blueprints_and_handles_directions_without_any(): void
    {
        $run = $this->makeRun($this->saasDna());

        $this->get("/analyses/{$run->public_id}/design-directions/layout?direction_key=product-pulse")
            ->assertOk()->assertSee('SaaS Product Showcase')->assertSee('not your website')->assertSee('Pricing is never invented');

        $this->get("/analyses/{$run->public_id}/design-directions/layout?direction_key=maison-market")
            ->assertOk()->assertSee('coming soon');

        $this->get("/analyses/{$run->public_id}/design-directions/layout?direction_key=bogus")->assertRedirect("/analyses/{$run->public_id}/design-directions");
    }

    public function test_project_api_recommendations_and_selection(): void
    {
        Queue::fake();
        $run = $this->makeRun($this->saasDna());

        $r = $this->postJson("/api/projects/{$run->public_id}/design-recommendations", ['direction_id' => 'executive-clarity'])->assertOk();
        $r->assertJsonPath('suggested_category_id', 'saas')->assertJsonPath('blueprints.0.blueprint_id', 'saas-product-01');
        $this->assertCount(12, $r->json('directions'));
        $this->assertArrayNotHasKey('score', $r->json('directions.0'), 'scores are not exposed');
        $this->assertNotEmpty($r->json('directions.0.reasons') ?? $r->json('directions.1.reasons'));

        $this->postJson("/api/projects/{$run->public_id}/design-selection", ['direction_id' => 'executive-clarity', 'blueprint_id' => 'saas-product-01', 'category_id' => 'saas'])
            ->assertCreated()->assertJsonPath('data.blueprint_version', 1)->assertJsonPath('data.status', 'selected');
        Queue::assertNotPushed(GenerateDesignSpecJob::class); // generation is a separate, explicit step

        $this->postJson("/api/projects/{$run->public_id}/design-selection", ['direction_id' => 'executive-clarity', 'blueprint_id' => 'nope'])->assertUnprocessable();
    }

    public function test_recommendations_api_needs_website_dna(): void
    {
        $run = $this->makeRun();
        $this->postJson("/api/projects/{$run->public_id}/design-recommendations")->assertStatus(409);
    }
}
