<?php

namespace Tests\Feature\Hero;

use App\DTO\MetaApi\MetaStageResponse;
use App\Models\AnalysisRun;
use App\Models\HeroCopyOption;
use App\Models\PositioningProfile;
use App\Services\Hero\HeroCopy;
use App\Services\Hero\HeroCopyEvaluationService;
use App\Services\Hero\HeroCopyGenerationService;
use App\Services\Hero\HeroCopyValidationService;
use App\Services\Hero\HeroMessagingDiagnosisService;
use App\Services\Hero\PositioningIntelligenceService;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\MetaModelClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Feature\Hero\Concerns\BuildsPositioningSite;
use Tests\TestCase;

class HeroCopyGenerationTest extends TestCase
{
    use BuildsPositioningSite;
    use RefreshDatabase;

    private function profile(): array
    {
        $run = $this->makeSupplierRun();
        $this->modelReturning($this->emptyModelProfile());
        $p = app(PositioningIntelligenceService::class)->build($run);

        return [$run, $p];
    }

    private function scores(int $n = 85): array
    {
        return array_fill_keys(array_keys(config('hero_layout.weights')), ['score' => $n, 'rationale' => 'ok']);
    }

    private function option(string $strategy, string $headline, string $description, array $extra = []): array
    {
        return $extra + ['option_id' => $strategy, 'strategy_id' => $strategy, 'headline' => $headline, 'description' => $description,
            'primary_cta' => ['label' => 'Request a Quote', 'destination_key' => 'section:contact'], 'evidence_ids' => [], 'positioning_rationale' => 'r', 'criterion_scores' => $this->scores()];
    }

    private function modelOptions(array $options): void
    {
        $m = Mockery::mock(MetaModelClient::class);
        $m->shouldReceive('generateHeroCopyOptions')->andReturn(new MetaStageResponse(data: ['positioning_summary' => 's', 'copy_options' => $options, 'unsupported_claim_warnings' => [], 'recommendation' => 'x'], modelIdentifier: 'test-model', inputTokens: 1, outputTokens: 1, latencyMs: 1));
        $this->app->instance(MetaModelClient::class, $m);
    }

    private function modelDown(): void
    {
        $m = Mockery::mock(MetaModelClient::class);
        $m->shouldReceive('generateHeroCopyOptions')->andThrow(new MetaApiException('down', 'provider_down'));
        $this->app->instance(MetaModelClient::class, $m);
    }

    private function keysFor(PositioningProfile $p, string $field): array
    {
        return $p->evidence()->get()->filter->isFactual()->where('field', $field)->pluck('claim_key')->take(2)->all();
    }

    private function gen(AnalysisRun $run)
    {
        return app(HeroCopyGenerationService::class)->generate($run);
    }

    public function test_three_distinct_options_pass_the_gates_and_are_ranked_and_stored(): void
    {
        [$run, $p] = $this->profile();
        $off = $this->keysFor($p, 'offering');
        $this->modelOptions([
            $this->option('direct-positioning', 'Raw materials for industrial manufacturers', 'Northgate Materials supplies aluminium plates, stainless steel and engineering plastic to manufacturers across the region.', ['evidence_ids' => $off]),
            $this->option('product-clarity', 'Aluminium plate and sheet supply', 'Stocked aluminium in plate, extrusion, sheet and coil forms, with stainless steel, copper and plastics alongside for industrial buyers.', ['evidence_ids' => $off]),
            $this->option('customer-outcome', 'One supplier for every material you source', 'Buy metals and engineering plastics from a single supplier instead of coordinating several, with quotes on request.', ['evidence_ids' => $off]),
        ]);

        $rows = $this->gen($run);

        $this->assertCount(3, $rows);
        $this->assertCount(3, $rows->pluck('strategy_id')->unique());
        $this->assertSame(3, HeroCopyOption::where('analysis_run_id', $run->id)->where('status', 'candidate')->count());
        $this->assertSame(1, (int) $rows->first()->batch);
        $this->assertGreaterThanOrEqual($rows->last()->total_score, $rows->first()->total_score);
        $this->assertSame('pass', $rows->first()->hard_gate_status);
        $this->assertEqualsWithDelta(array_sum(array_column($rows->first()->score_breakdown_json, 'weighted')), $rows->first()->total_score + 5 * collect($rows->first()->validation_json['issues'])->where('gate', 'editorial')->whereIn('severity', ['warn', 'fail'])->count(), 0.6);
    }

    public function test_unsupported_statistics_are_hard_rejected_whatever_the_model_scored_them(): void
    {
        [$run, $p] = $this->profile(); // TEST-06: "serving 10,000 customers" has no evidence
        $this->modelOptions([$this->option('direct-positioning', 'Raw materials serving 10,000 customers', 'Northgate Materials supplies aluminium plates and stainless steel to manufacturers.', ['evidence_ids' => $this->keysFor($p, 'offering'), 'criterion_scores' => $this->scores(100)])]);

        $rows = $this->gen($run);
        $bad = $rows->firstWhere('source', 'model');

        $this->assertSame('rejected', $bad->recommendation);
        $this->assertSame('fail', $bad->hard_gate_status);
        $this->assertSame('rejected', $bad->status);
        $this->assertContains('unsupported_number', array_column($bad->validation_json['issues'], 'code'));
        $this->assertSame('conservative', $rows->firstWhere('hard_gate_status', 'pass')->source); // an unblocked safe fallback exists
    }

    public function test_leading_and_best_are_rejected_without_evidence_and_an_option_without_evidence_cannot_pass(): void
    {
        [$run, $p] = $this->profile(); // TEST-02
        $this->modelOptions([
            $this->option('direct-positioning', 'The leading raw materials supplier', 'Northgate Materials supplies aluminium plates and stainless steel to manufacturers.', ['evidence_ids' => $this->keysFor($p, 'offering')]),
            $this->option('product-clarity', 'Quality aluminium plates for industry', 'Aluminium plates and stainless steel supplied to industrial manufacturers on request.', ['evidence_ids' => []]),
        ]);

        $rows = $this->gen($run)->where('source', 'model');

        $leading = $rows->firstWhere('headline', 'The leading raw materials supplier');
        $this->assertSame('fail', $leading->hard_gate_status);
        $noEvidence = $rows->firstWhere('headline', 'Quality aluminium plates for industry');
        $this->assertSame('fail', $noEvidence->hard_gate_status);
        $this->assertContains('no_evidence', array_column($noEvidence->validation_json['issues'], 'code'));
    }

    public function test_the_business_is_never_called_a_manufacturer_without_evidence(): void
    {
        [$run, $p] = $this->profile(); // TEST-07 — start from a site with no manufacturing evidence
        $pages = [['url' => 'https://shop.test/', 'title' => 'Shop', 'headings' => ['Shop'], 'text' => 'We supply aluminium sheet to workshops. Contact us for pricing and availability today.', 'ctas' => ['Contact us']]];
        $run = $this->makeSupplierRun($pages, ['business_name' => 'Shop', 'industry' => 'Aluminium sheet supply', 'business_summary' => 'x', 'target_audience' => [], 'offerings' => [['name' => 'Aluminium sheet', 'description' => '', 'evidence_ids' => ['p0-s0']]],
            'value_proposition' => ['text' => 'Aluminium sheet', 'evidence_ids' => ['p0-s0'], 'confidence' => 'medium'], 'tone_of_voice' => ['plain'], 'primary_conversion_goal' => 'inquiry', 'cta_inventory' => [], 'trust_signals' => []]);
        $this->modelReturning($this->emptyModelProfile());
        $p = app(PositioningIntelligenceService::class)->build($run);
        $this->modelOptions([$this->option('direct-positioning', 'Aluminium sheet manufacturer for workshops', 'We manufacture aluminium sheet for workshops and supply it direct from our own plant.', ['evidence_ids' => $this->keysFor($p, 'offering')])]);

        $bad = $this->gen($run)->firstWhere('source', 'model');

        $this->assertSame('fail', $bad->hard_gate_status);
        $this->assertContains('unsupported_business_model', array_column($bad->validation_json['issues'], 'code'));
    }

    public function test_a_product_stuffed_headline_is_flagged_and_relocated_not_recommended(): void
    {
        [$run, $p] = $this->profile(); // TEST-01
        $this->modelOptions([$this->option('direct-positioning', 'One-Stop Raw Materials — Suzhou 6061-T651 Plates, Aluminium, Steel, Copper & Engineering Plastic', 'Northgate Materials supplies aluminium plates, stainless steel and engineering plastic to manufacturers.', ['evidence_ids' => $this->keysFor($p, 'offering')])]);

        $rows = $this->gen($run);
        $stuffed = $rows->firstWhere('source', 'model');

        $this->assertNotSame('recommended', $stuffed->recommendation);
        $this->assertContains('feature_dump', array_column($stuffed->validation_json['issues'], 'code'));

        $d = app(HeroMessagingDiagnosisService::class)->diagnose(HeroCopy::fromOption($stuffed), $p, 'split');
        $checks = array_column($d['findings'], 'check');
        $this->assertContains('feature-dump', $checks);
        $this->assertContains('message-overload', $checks);
        $this->assertContains('Product details', array_column($d['relocate'], 'belongs_in'));
    }

    public function test_near_duplicate_options_collapse_into_one(): void
    {
        [$run, $p] = $this->profile();
        $off = $this->keysFor($p, 'offering');
        $this->modelOptions([
            $this->option('direct-positioning', 'Raw materials for industrial manufacturers', 'Northgate Materials supplies aluminium plates and stainless steel to manufacturers across the region.', ['evidence_ids' => $off]),
            $this->option('customer-outcome', 'Raw materials for industrial manufacturing', 'Aluminium plates and stainless steel supplied by Northgate Materials to manufacturers around the region.', ['evidence_ids' => $off]),
        ]);

        $model = $this->gen($run)->where('source', 'model');

        $this->assertCount(1, $model);
    }

    public function test_model_failure_falls_back_to_a_conservative_option_that_is_not_recommended(): void
    {
        [$run, $p] = $this->profile();
        $this->modelDown();

        $rows = $this->gen($run);

        $this->assertCount(1, $rows);
        $o = $rows->first();
        $this->assertSame('conservative', $o->source);
        $this->assertSame('pass', $o->hard_gate_status);
        $this->assertNotSame('recommended', $o->recommendation);
        $this->assertNotEmpty($o->evidence_ids_json);
        $this->assertSame('section:contact', $o->primary_cta_json['destination_key']);
        $this->assertSame('pass', app(HeroCopyValidationService::class)->validate(HeroCopy::fromOption($o), $p, [], false)['factual_gate']);
    }

    public function test_cta_destinations_outside_the_allowlist_are_rejected(): void
    {
        [$run, $p] = $this->profile();
        $this->modelOptions([$this->option('direct-positioning', 'Raw materials for industrial manufacturers', 'Northgate Materials supplies aluminium plates and stainless steel to manufacturers.', ['evidence_ids' => $this->keysFor($p, 'offering'), 'primary_cta' => ['label' => 'Request a Quote', 'destination_key' => 'https://evil.example/pay']])]);

        $bad = $this->gen($run)->firstWhere('source', 'model');

        $this->assertSame('fail', $bad->hard_gate_status);
        $this->assertArrayNotHasKey('destination', $bad->primary_cta_json);
    }

    public function test_regenerating_supersedes_earlier_candidates_and_increments_the_batch(): void
    {
        [$run, $p] = $this->profile();
        $off = $this->keysFor($p, 'offering');
        $this->modelOptions([$this->option('direct-positioning', 'Raw materials for industrial manufacturers', 'Northgate Materials supplies aluminium plates and stainless steel to manufacturers.', ['evidence_ids' => $off])]);
        $this->gen($run);
        $this->gen($run);

        $this->assertSame(1, HeroCopyOption::where('analysis_run_id', $run->id)->where('status', 'candidate')->where('source', 'model')->count());
        $this->assertSame(2, (int) HeroCopyOption::where('analysis_run_id', $run->id)->max('batch'));
        $this->assertGreaterThan(0, HeroCopyOption::where('analysis_run_id', $run->id)->where('status', 'superseded')->count());
    }

    public function test_score_is_computed_in_code_from_the_seven_weights_and_hard_gate_beats_score(): void
    {
        $e = app(HeroCopyEvaluationService::class);
        $r = $e->score($this->scores(80));
        $this->assertSame(80.0, $r['total']);
        $this->assertSame(100, array_sum(array_column($r['breakdown'], 'weight')));

        $this->assertSame('recommended', $e->recommendation(82.0, ['factual_gate' => 'pass', 'editorial_gate' => 'pass', 'issues' => []]));
        $this->assertSame('needs_review', $e->recommendation(70.0, ['factual_gate' => 'pass', 'editorial_gate' => 'pass', 'issues' => []]));
        $this->assertSame('rewrite', $e->recommendation(50.0, ['factual_gate' => 'pass', 'editorial_gate' => 'pass', 'issues' => []]));
        $this->assertSame('rejected', $e->recommendation(99.0, ['factual_gate' => 'fail', 'editorial_gate' => 'pass', 'issues' => []]));
        $this->assertSame('needs_review', $e->recommendation(90.0, ['factual_gate' => 'pass', 'editorial_gate' => 'warn', 'issues' => [['severity' => 'warn']]]));
        // out-of-range model ratings are clamped, never trusted
        $this->assertSame(100.0, $e->score($this->scores(900))['total']);
    }

    public function test_the_existing_hero_is_diagnosed_with_traceable_findings(): void
    {
        [$run, $p] = $this->profile();
        $p->update(['existing_hero_json' => ['headline' => 'Welcome to Northgate', 'subheadline' => 'Welcome to Northgate', 'ctas' => ['Learn more']]]);
        $svc = app(HeroMessagingDiagnosisService::class);

        $d = $svc->diagnose($svc->siteHero($p->fresh()), $p->fresh());
        $checks = array_column($d['findings'], 'check');

        $this->assertContains('generic-claim', $checks);
        $this->assertContains('duplicate-copy', $checks);
        $this->assertContains('weak-cta', $checks);
        foreach ($d['findings'] as $f) {
            $this->assertNotEmpty($f['recommended_fix']);
            $this->assertMatchesRegularExpression('/^D\d+$/', $f['diagnosis_id']);
        }
    }
}
