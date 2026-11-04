<?php

namespace Tests\Feature\Hero;

use App\Models\PositioningProfile;
use App\Services\Hero\PositioningIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Hero\Concerns\BuildsPositioningSite;
use Tests\TestCase;

class PositioningProfileTest extends TestCase
{
    use BuildsPositioningSite;
    use RefreshDatabase;

    private function build(?array $modelProfile = null, bool $modelFails = false, ?array $pages = null): PositioningProfile
    {
        $run = $this->makeSupplierRun($pages);
        $this->modelReturning($modelProfile ?? $this->emptyModelProfile(), $modelFails);

        return app(PositioningIntelligenceService::class)->build($run);
    }

    private function claims(PositioningProfile $p, string $field)
    {
        return $p->evidence()->where('field', $field)->get();
    }

    public function test_a_profile_is_built_before_any_copy_with_traceable_claims(): void
    {
        $p = $this->build();

        $this->assertSame('dna+model', $p->source_mode);
        $this->assertSame('Industrial raw materials supply', $p->business_category);
        $offering = $this->claims($p, 'offering')->firstWhere('claim_value', 'Aluminium plates');
        $this->assertSame('verified_source', $offering->status);
        $this->assertNotEmpty($offering->evidence_keys_json);
        $this->assertStringContainsString('aluminium plates', mb_strtolower($offering->evidence_summary));
        $this->assertSame('https://supplier.test/', $offering->source_url);
    }

    public function test_facts_are_read_directly_from_the_page_text_and_the_plants_year_is_not_the_company_founding(): void
    {
        $p = $this->build();

        $years = $this->claims($p, 'years');
        $this->assertSame(['Operating since 2005'], $years->pluck('claim_value')->all(), 'a manufacturing plant "established 2009" is a different fact');
        $this->assertSame('verified_source', $years->first()->status);
        $this->assertSame('9 branches', $this->claims($p, 'locations')->first()->claim_value);
        $this->assertSame('ISO 9001:2015', $this->claims($p, 'certification')->firstWhere('claim_value', 'ISO 9001:2015')->claim_value);
        $this->assertSame('China, Malaysia, Thailand', $this->claims($p, 'geography')->firstWhere('claim_value', 'China, Malaysia, Thailand')->claim_value);
    }

    public function test_the_business_model_is_only_what_the_text_supports(): void
    {
        $p = $this->build();
        $models = $this->claims($p, 'business_model')->pluck('claim_value')->all();
        $this->assertContains('manufacturer', $models);
        $this->assertContains('supplier', $models);
        $this->assertSame('supplier_and_manufacturer', $p->business_model);

        // TEST-07: a site that sells materials but never says it manufactures them is not labelled a manufacturer.
        $only = $this->build(pages: [['url' => 'https://s.test/', 'title' => 'Shop', 'headings' => ['Metals'], 'text' => 'We supply aluminium sheet and copper tube to workshops across the region with next-day delivery on stocked items.', 'ctas' => []]]);
        $this->assertNotContains('manufacturer', $this->claims($only, 'business_model')->pluck('claim_value')->all());
        $this->assertSame('supplier', $only->business_model);
    }

    public function test_the_models_claim_status_is_recomputed_never_trusted(): void
    {
        // TEST-06: an unsupported statistic cites real evidence but is not in it.
        $p = $this->build($this->emptyModelProfile([
            'proof_points' => [$this->claim('Serving 10,000 customers worldwide', ['p0-s0'], 'high')],
            'differentiators' => [$this->claim('The leading and most trusted supplier in Asia', ['p0-s0'], 'high'), $this->claim('Operates a manufacturing plant in Suzhou', ['p0-s3'], 'high')],
            'customer_benefits' => [$this->claim('Helps manufacturers keep production moving', [], 'high')],
        ]));

        $this->assertSame('inferred', $this->claims($p, 'proof')->firstWhere('claim_value', 'Serving 10,000 customers worldwide')->status);
        $this->assertSame('inferred', $this->claims($p, 'differentiator')->firstWhere('claim_value', 'The leading and most trusted supplier in Asia')->status);
        $this->assertSame('verified_source', $this->claims($p, 'differentiator')->firstWhere('claim_value', 'Operates a manufacturing plant in Suzhou')->status, 'a genuinely supported claim is verified');
        $this->assertSame('inferred', $this->claims($p, 'benefit')->firstWhere('claim_value', 'Helps manufacturers keep production moving')->status, 'no evidence cited, none found');
        $this->assertNotContains('10,000', $p->factualClaims()->pluck('claim_value')->map(fn ($v) => preg_replace('/\D/', '', $v))->all());
    }

    public function test_a_number_must_appear_in_the_evidence_or_the_claim_is_only_inferred(): void
    {
        $p = $this->build($this->emptyModelProfile(['proof_points' => [$this->claim('12 branches in China, Malaysia and Thailand', ['p0-s2'])]]));
        $this->assertSame('inferred', $this->claims($p, 'proof')->firstWhere('claim_value', '12 branches in China, Malaysia and Thailand')->status, 'the site says 9, not 12');
    }

    public function test_conflicting_founding_years_across_pages_are_flagged_and_excluded_until_resolved(): void
    {
        // TEST-03
        $p = $this->build(pages: [
            ['url' => 'https://s.test/', 'title' => 'Home', 'headings' => ['Metals'], 'text' => 'Since 2005 we have supplied aluminium and copper to industrial manufacturers in the region reliably.', 'ctas' => []],
            ['url' => 'https://s.test/about', 'title' => 'About', 'headings' => ['About us'], 'text' => 'The company was founded in 2009 and supplies aluminium and copper to industrial manufacturers in the region.', 'ctas' => []],
        ]);

        $years = $this->claims($p, 'years');
        $this->assertCount(2, $years);
        $this->assertSame(['contradicted'], $years->pluck('status')->unique()->all());
        $c = collect($p->contradictions_json)->firstWhere('topic', 'Year established');
        $this->assertEqualsCanonicalizing(['2005', '2009'], $c['values']);
        $this->assertSame('open', $c['status']);
        $this->assertSame('needs_confirmation', $p->status);
        $this->assertEmpty($p->factualClaims()->where('field', 'years')->get(), 'a disputed claim can never be used as a fact');

        // The user picks the right one: it is confirmed, its rival becomes unknown, the contradiction closes.
        $svc = app(PositioningIntelligenceService::class);
        $keep = $years->firstWhere('claim_value', 'Operating since 2009');
        $p = $svc->applyCorrection($p, 'resolve', $keep->claim_key, topic: 'Year established');
        $this->assertSame('user_confirmed', $p->evidence()->where('claim_key', $keep->claim_key)->value('status'));
        $this->assertSame(['unknown'], $p->evidence()->where('field', 'years')->where('claim_key', '!=', $keep->claim_key)->pluck('status')->all());
        $this->assertSame('resolved', collect($p->contradictions_json)->firstWhere('topic', 'Year established')['status']);
        $this->assertNotSame('needs_confirmation_contradiction', $p->status);
    }

    public function test_different_facts_on_the_same_page_are_not_contradictions(): void
    {
        $p = $this->build(); // "9 branches" and "12 countries" etc. on the homepage
        $this->assertSame([], collect($p->contradictions_json)->where('origin', 'deterministic')->all());
    }

    public function test_the_profile_still_builds_when_the_model_is_down_and_says_so(): void
    {
        $p = $this->build(modelFails: true);

        $this->assertSame('dna', $p->source_mode);
        $this->assertNull($p->model_identifier);
        $this->assertSame('Industrial raw materials supply', $p->business_category);
        $this->assertGreaterThan(0, $p->evidence()->count());
        $this->assertDatabaseHas('positioning_profiles', ['id' => $p->id]);
    }

    public function test_missing_elements_are_recorded_as_unknown_never_invented(): void
    {
        $run = $this->makeSupplierRun(dnaOverride: ['business_name' => 'X', 'industry' => 'Industrial raw materials supply', 'business_summary' => '', 'target_audience' => [], 'offerings' => [['name' => 'Aluminium plates', 'description' => '', 'evidence_ids' => ['p0-s0']]], 'value_proposition' => ['text' => null, 'evidence_ids' => [], 'confidence' => 'low'], 'tone_of_voice' => [], 'primary_conversion_goal' => 'unknown', 'cta_inventory' => [], 'trust_signals' => []]);
        $this->modelReturning($this->emptyModelProfile());
        $p = app(PositioningIntelligenceService::class)->build($run);

        $unknown = collect($p->unknowns_json)->pluck('field')->all();
        $this->assertContains('audience', $unknown);
        $this->assertContains('benefit', $unknown);
        $this->assertSame(0, $p->evidence()->where('field', 'audience')->count());
        $this->assertSame(0, $p->evidence()->where('field', 'benefit')->count());
    }

    public function test_the_users_current_homepage_messaging_is_captured(): void
    {
        $hero = $this->build()->existing_hero_json;
        $this->assertSame('Welcome to Northgate Materials', $hero['headline']);
        $this->assertSame('Raw materials for industry', $hero['subheadline']);
        $this->assertContains('Our Products', $hero['ctas']);
        $this->assertSame('https://supplier.test/', $hero['source_url']);
    }

    public function test_user_corrections_confirm_edit_reject_and_add(): void
    {
        $svc = app(PositioningIntelligenceService::class);
        $p = $this->build($this->emptyModelProfile(['customer_benefits' => [$this->claim('Keeps production moving', [])]]));
        $inferred = $p->evidence()->where('field', 'benefit')->where('status', 'inferred')->first();

        $p = $svc->applyCorrection($p, 'confirm', $inferred->claim_key);
        $confirmed = $p->evidence()->where('claim_key', $inferred->claim_key)->first();
        $this->assertSame('user_confirmed', $confirmed->status);
        $this->assertNotNull($confirmed->user_confirmed_at);
        $this->assertTrue($confirmed->isFactual());

        $p = $svc->applyCorrection($p, 'edit', $inferred->claim_key, 'Keeps factory lines supplied');
        $this->assertSame('Keeps factory lines supplied', $p->evidence()->where('claim_key', $inferred->claim_key)->value('claim_value'));

        $offering = $p->evidence()->where('field', 'offering')->first();
        $p = $svc->applyCorrection($p, 'reject', $offering->claim_key);
        $this->assertSame('unknown', $p->evidence()->where('claim_key', $offering->claim_key)->value('status'));
        $this->assertNotContains($offering->claim_key, $p->factualClaims()->pluck('claim_key')->all());

        $p = $svc->applyCorrection($p, 'add', value: 'Industrial buyers in South-East Asia', field: 'audience');
        $this->assertSame('user_confirmed', $p->evidence()->where('claim_value', 'Industrial buyers in South-East Asia')->value('status'));

        $this->expectException(\RuntimeException::class);
        $svc->applyCorrection($p, 'confirm', 'no.such.claim');
    }

    public function test_versions_increment_and_history_is_kept(): void
    {
        $run = $this->makeSupplierRun();
        $this->modelReturning($this->emptyModelProfile());
        $svc = app(PositioningIntelligenceService::class);

        $a = $svc->build($run);
        $b = $svc->build($run);
        $this->assertSame([1, 2], [$a->version, $b->version]);
        $this->assertSame($b->id, $run->currentPositioningProfile()->id);
        $this->assertSame(2, $run->positioningProfiles()->count());
    }

    public function test_building_without_website_dna_is_refused(): void
    {
        $run = \App\Models\AnalysisRun::create(['public_id' => 'r_'.uniqid(), 'submitted_url' => 'https://x.test', 'normalized_url' => 'https://x.test/', 'idempotency_key' => uniqid(), 'status' => 'pending', 'current_stage' => 'validating_url', 'source_mode' => 'live']);
        $this->expectException(\RuntimeException::class);
        app(PositioningIntelligenceService::class)->build($run);
    }

    public function test_long_model_wording_never_overflows_the_summary_columns(): void
    {
        // Seen live: a model returned a whole sentence as the "business model". MySQL enforces varchar lengths; sqlite does not, so assert the bound directly.
        $long = 'Consulting engagements typically running 8 to 12 weeks with a dedicated senior consultant and a structured delivery process '.str_repeat('and more ', 20);
        $p = $this->build($this->emptyModelProfile(['business_category' => $this->claim($long.str_repeat('x', 300)), 'business_model' => [$this->claim($long)]]));

        $this->assertLessThanOrEqual(64, mb_strlen((string) $p->business_model));
        $this->assertLessThanOrEqual(255, mb_strlen((string) $p->business_category));
    }
}
