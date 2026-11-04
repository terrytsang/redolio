<?php

namespace Tests\Feature\Hero;

use App\DTO\MetaApi\MetaStageResponse;
use App\Models\HeroCopyOption;
use App\Models\HeroRenderValidation;
use App\Models\PositioningEvidence;
use App\Models\PositioningProfile;
use App\Models\User;
use App\Services\Editing\SpecEditService;
use App\Services\Hero\HeroCopyRefinementService;
use App\Services\Hero\HeroCopyVersionService;
use App\Services\Hero\HeroLayoutValidationService;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\MetaModelClient;
use App\Services\VisualQa\BrowserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Mockery;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;

class HeroFitTest extends TestCase
{
    use BuildsBlueprintSpecs;
    use RefreshDatabase;

    private function m(int $h1 = 2, int $desc = 3, array $over = []): array
    {
        return $over + ['found' => true, 'font_loaded' => true, 'h1_lines' => $h1, 'description_lines' => $desc, 'overflow_detected' => false, 'clipping_detected' => false, 'cta_visible' => true, 'h1_font_px' => 56.0];
    }

    private function allViewports(array $over = []): array
    {
        return ['desktop' => $this->m(2, 3, $over['desktop'] ?? []), 'tablet' => $this->m(3, 4, $over['tablet'] ?? []), 'mobile' => $this->m(4, 6, $over['mobile'] ?? [])] + [];
    }

    private function project(): array
    {
        $run = $this->makeRun(null, ['team1' => 'team_portrait']);
        [$res, $spec] = $this->attach($run, $this->legacySpec());
        $this->assertTrue($res['attached'], implode('; ', $res['errors']));
        $p = PositioningProfile::create(['analysis_run_id' => $run->id, 'version' => 1, 'business_category' => 'Family dental practice', 'business_model' => 'service_provider', 'status' => 'ready', 'source_mode' => 'dna', 'created_at' => now()]);
        foreach ([['category', 'Family dental practice', 'category.1'], ['offering', 'Checkups', 'offering.1'], ['offering', 'Whitening', 'offering.2']] as [$f, $v, $k]) {
            PositioningEvidence::create(['positioning_profile_id' => $p->id, 'claim_key' => $k, 'field' => $f, 'claim_value' => $v, 'status' => 'verified_source', 'confidence' => 'high', 'created_at' => now()]);
        }
        $o = HeroCopyOption::create(['analysis_run_id' => $run->id, 'positioning_profile_id' => $p->id, 'batch' => 1, 'strategy_id' => 'direct-positioning', 'headline' => 'Checkups and whitening for the whole family today and every day after',
            'description' => 'Brightleaf is a family dental practice offering checkups and whitening, with appointments that fit around school and work.', 'primary_cta_json' => ['label' => 'Book a visit', 'destination_key' => 'section:contact'],
            'evidence_ids_json' => ['category.1', 'offering.1', 'offering.2'], 'total_score' => 80, 'recommendation' => 'recommended', 'hard_gate_status' => 'pass', 'validation_json' => ['issues' => []], 'status' => 'candidate', 'source' => 'model', 'created_at' => now()]);
        $user = User::factory()->create();
        app(HeroCopyVersionService::class)->approve($run, $o, $user);

        return [$run->fresh(), $p, $user];
    }

    private function scriptedLayout(array $results): void
    {
        $svc = Mockery::mock(HeroLayoutValidationService::class)->makePartial();
        $queue = $results;
        $svc->shouldReceive('validate')->andReturnUsing(function ($run, $spec, $attempt = 0, $optionId = null) use (&$queue, $svc, $results) {
            $r = array_shift($queue) ?? end($results);
            [$result, $reasons] = $svc->judge($r, $svc->variantOf($spec));

            return ['result' => $result, 'viewports' => $r, 'reasons' => $reasons, 'font_px' => $r['desktop']['h1_font_px'] ?? 56.0, 'record' => null, 'screenshots' => []];
        });
        $this->app->instance(HeroLayoutValidationService::class, $svc);
    }

    private function refineModel(?array $responses, bool $down = false): void
    {
        $model = Mockery::mock(MetaModelClient::class);
        $queue = $responses ?? [];
        $model->shouldReceive('refineHeroCopy')->andReturnUsing(function () use (&$queue, $down) {
            if ($down) {
                throw new MetaApiException('down', 'provider_down');
            }

            return new MetaStageResponse(data: array_shift($queue) ?? [], modelIdentifier: 't', inputTokens: 1, outputTokens: 1, latencyMs: 1);
        });
        $this->app->instance(MetaModelClient::class, $model);
    }

    // ---------------------------------------------------------------- judgement (pure)

    public function test_a_hero_that_fits_every_viewport_passes(): void
    {
        [$result, $reasons] = app(HeroLayoutValidationService::class)->judge($this->allViewports(), 'split');

        $this->assertSame('pass', $result);
        $this->assertSame([], $reasons);
    }

    public function test_too_many_lines_overflow_clipping_and_a_hidden_cta_each_fail_with_a_specific_reason(): void
    {
        $svc = app(HeroLayoutValidationService::class);
        $cases = [
            ['h1_lines', ['mobile' => ['h1_lines' => 9]]], ['description_lines', ['tablet' => ['description_lines' => 12]]],
            ['overflow', ['desktop' => ['overflow_detected' => true]]], ['clipping', ['mobile' => ['clipping_detected' => true]]], ['cta_hidden', ['tablet' => ['cta_visible' => false]]],
        ];
        foreach ($cases as [$code, $over]) {
            [$result, $reasons] = $svc->judge($this->allViewports($over), 'split');
            $this->assertSame('fail', $result, $code);
            $this->assertContains($code, array_column($reasons, 'code'));
        }
    }

    public function test_line_limits_follow_the_hero_variant(): void
    {
        $svc = app(HeroLayoutValidationService::class);
        $tall = $this->allViewports(['desktop' => ['h1_lines' => 4]]);

        $this->assertSame('fail', $svc->judge($tall, 'split')[0], 'split prefers 3 lines on desktop');
        $this->assertSame('pass', $svc->judge($tall, 'editorial-asymmetric')[0], 'editorial allows 4');
    }

    public function test_fonts_not_ready_or_a_missing_measurement_is_never_a_pass(): void
    {
        $svc = app(HeroLayoutValidationService::class);

        $this->assertSame('exception', $svc->judge($this->allViewports(['mobile' => ['font_loaded' => false]]), 'split')[0]);
        $missing = $this->allViewports(); unset($missing['tablet']);
        $this->assertSame('exception', $svc->judge($missing, 'split')[0]);
        $this->assertSame('exception', $svc->judge([], 'split')[0]);
        $this->assertSame(['desktop_fit' => 'unavailable', 'tablet_fit' => 'unavailable', 'mobile_fit' => 'unavailable'], HeroLayoutValidationService::statusByViewport('exception', []));
    }

    public function test_a_provider_that_cannot_measure_reports_unavailable_never_pass(): void
    {
        [$run] = $this->project();
        $browser = Mockery::mock(BrowserService::class);
        $browser->shouldReceive('id')->andReturn('browserless');
        $browser->shouldReceive('isAvailable')->andReturn(true);
        $this->app->instance(BrowserService::class, $browser);

        $r = app(HeroLayoutValidationService::class)->validate($run, $run->currentDesignSpec());

        $this->assertSame('unavailable', $r['result']);
        $this->assertSame('unavailable', HeroRenderValidation::latest('id')->first()->validation_result);
    }

    // ---------------------------------------------------------------- the bounded loop

    public function test_a_hero_that_fits_is_left_exactly_as_it_is(): void
    {
        [$run, $p, $user] = $this->project();
        $this->scriptedLayout([$this->allViewports()]);
        $this->refineModel([]);
        $before = $run->designSpecs()->count();

        $r = app(HeroCopyRefinementService::class)->run($run, $user);

        $this->assertSame('fit', $r['status']);
        $this->assertSame(0, $r['copy_refinements']);
        $this->assertSame($before, $run->designSpecs()->count());
        $this->assertSame('pass', $run->currentDesignSpec()->design_spec_json['v16']['hero_content']['validation']['desktop_fit']);
    }

    public function test_a_too_long_headline_is_shortened_by_a_gated_rewrite_without_touching_type_size(): void
    {
        [$run, $p, $user] = $this->project();
        $long = $this->allViewports(['mobile' => ['h1_lines' => 9]]);
        $this->scriptedLayout([$long, $this->allViewports()]);
        $this->refineModel([['headline' => 'Checkups and whitening for families', 'description' => 'Brightleaf is a family dental practice offering checkups and whitening around school and work.', 'change_summary' => 'Cut the trailing clause.']]);

        $r = app(HeroCopyRefinementService::class)->run($run, $user);

        $this->assertSame('fit', $r['status']);
        $this->assertSame(1, $r['copy_refinements']);
        $now = $run->fresh()->currentDesignSpec();
        $this->assertSame('Checkups and whitening for families', collect($now->design_spec_json['v15']['pages'][0]['sections'])->firstWhere('component_id', 'hero')['props']['headline']);
        $this->assertSame('refinement', HeroCopyOption::where('analysis_run_id', $run->id)->latest('id')->first()->source);
        $this->assertSame(56.0, $r['font_px'], 'type size is untouched');
        $this->assertCount(2, $r['attempts'], 'measured, then re-measured after one rewrite');
    }

    public function test_a_rewrite_that_adds_an_unsupported_claim_is_rejected_and_the_original_stays(): void
    {
        [$run, $p, $user] = $this->project();
        $this->scriptedLayout([$this->allViewports(['mobile' => ['h1_lines' => 9]])]);
        $this->refineModel(array_fill(0, 2, ['headline' => 'The best dentist for 10,000 families', 'description' => 'Trusted by 10,000 families.', 'change_summary' => 'x']));
        $versions = $run->designSpecs()->count();

        $r = app(HeroCopyRefinementService::class)->run($run, $user);

        $this->assertSame('needs_review', $r['status']);
        $this->assertSame($versions, $run->designSpecs()->count() - $r['layout_adjustments'], 'no unsafe copy reached the page');
        $this->assertStringContainsString('Checkups and whitening for the whole family', collect($run->fresh()->currentDesignSpec()->design_spec_json['v15']['pages'][0]['sections'])->firstWhere('component_id', 'hero')['props']['headline']);
    }

    public function test_the_loop_is_bounded_two_copy_rewrites_two_layout_changes_then_the_user_decides(): void
    {
        [$run, $p, $user] = $this->project();
        $this->scriptedLayout(array_fill(0, 12, $this->allViewports(['mobile' => ['h1_lines' => 9]])));
        $calls = 0;
        $model = Mockery::mock(MetaModelClient::class);
        $model->shouldReceive('refineHeroCopy')->andReturnUsing(function () use (&$calls) {
            $calls++;

            return new MetaStageResponse(data: ['headline' => 'Family checkups and whitening '.str_repeat('x', 0), 'description' => 'Brightleaf is a family dental practice offering checkups and whitening around school and work.', 'change_summary' => 's'], modelIdentifier: 't', inputTokens: 1, outputTokens: 1, latencyMs: 1);
        });
        $this->app->instance(MetaModelClient::class, $model);

        $r = app(HeroCopyRefinementService::class)->run($run, $user);

        $this->assertLessThanOrEqual(2, $calls, 'at most two copy rewrites');
        $this->assertLessThanOrEqual(2, $r['layout_adjustments']);
        $this->assertSame('needs_review', $r['status'], 'the user is handed the decision, with the reasons');
        $this->assertNotEmpty($r['reasons']);
    }

    public function test_model_down_means_no_rewrite_and_no_silent_change(): void
    {
        [$run, $p, $user] = $this->project();
        $this->scriptedLayout([$this->allViewports(['desktop' => ['h1_lines' => 8]])]);
        $this->refineModel(null, down: true);
        $headline = fn ($r) => collect($r->currentDesignSpec()->design_spec_json['v15']['pages'][0]['sections'])->firstWhere('component_id', 'hero')['props']['headline'];
        $was = $headline($run);

        $r = app(HeroCopyRefinementService::class)->run($run, $user);

        $this->assertSame($was, $headline($run->fresh()));
        $this->assertSame('needs_review', $r['status']);
    }

    public function test_the_owner_can_accept_a_layout_exception_and_it_is_recorded(): void
    {
        [$run, $p, $user] = $this->project();

        $new = app(HeroCopyRefinementService::class)->acceptException($run, $user);

        $this->assertSame('layout_exception', $new->design_spec_json['v16']['hero_content']['approval_status']);
        $this->assertDatabaseHas('hero_copy_approvals', ['analysis_run_id' => $run->id, 'approval_status' => 'layout_exception']);
    }

    public function test_api_validate_render_is_queued_and_needs_hero_copy_first(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        [$run, $p, $user] = $this->project();
        $plain = $this->makeRun(); $this->attach($plain, $this->legacySpec());
        $plain->update(['user_id' => $user->id]);

        $this->actingAs($user)->postJson(route('api.hero.validate-render', $plain->public_id))->assertStatus(409);
        $this->actingAs($user)->postJson(route('api.hero.validate-render', $run->public_id))->assertStatus(202)->assertJsonPath('kind', 'render');
        $this->actingAs($user)->getJson(route('api.hero.render-status', $run->public_id))->assertOk()->assertJsonStructure(['validation', 'approval_status', 'last', 'job']);
        $this->actingAs($user)->postJson(route('api.hero.accept-exception', $run->public_id))->assertOk()->assertJsonPath('approval_status', 'layout_exception');
    }
}
