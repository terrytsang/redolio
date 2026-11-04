<?php

namespace Tests\Feature\Hero;

use App\Models\HeroCopyOption;
use App\Models\PositioningEvidence;
use App\Models\PositioningProfile;
use App\Services\Hero\AutoHeroService;
use App\Services\Hero\HeroCopyGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;

class AutoHeroTest extends TestCase
{
    use BuildsBlueprintSpecs;
    use RefreshDatabase;

    private function project(): array
    {
        $run = $this->makeRun(null, ['team1' => 'team_portrait']);
        [$res] = $this->attach($run, $this->legacySpec());
        $this->assertTrue($res['attached'], implode('; ', $res['errors']));
        $p = PositioningProfile::create(['analysis_run_id' => $run->id, 'version' => 1, 'business_category' => 'Family dental practice', 'business_model' => 'service_provider', 'status' => 'ready', 'source_mode' => 'dna', 'created_at' => now()]);
        foreach ([['category', 'Family dental practice', 'category.1'], ['offering', 'Checkups', 'offering.1'], ['offering', 'Whitening', 'offering.2']] as [$f, $v, $k]) {
            PositioningEvidence::create(['positioning_profile_id' => $p->id, 'claim_key' => $k, 'field' => $f, 'claim_value' => $v, 'status' => 'verified_source', 'confidence' => 'high', 'created_at' => now()]);
        }

        return [$run, $p];
    }

    private function option($run, $p, array $over = []): HeroCopyOption
    {
        return HeroCopyOption::create($over + ['analysis_run_id' => $run->id, 'positioning_profile_id' => $p->id, 'batch' => 1, 'strategy_id' => 'direct-positioning', 'headline' => 'Checkups and whitening for the whole family',
            'description' => 'Brightleaf is a family dental practice offering checkups and whitening, with appointments that fit around school and work.', 'primary_cta_json' => ['label' => 'Book a visit', 'destination_key' => 'section:contact'],
            'evidence_ids_json' => ['category.1', 'offering.1'], 'total_score' => 82, 'recommendation' => 'recommended', 'hard_gate_status' => 'pass', 'validation_json' => ['issues' => []], 'status' => 'candidate', 'source' => 'model', 'created_at' => now()]);
    }

    private function generating(array $options): void
    {
        $this->mock(HeroCopyGenerationService::class, fn ($m) => $m->shouldReceive('generate')->once()->andReturn(collect($options)));
    }

    public function test_the_recommended_option_is_put_on_the_first_redo(): void
    {
        config(['hero_layout.auto_apply' => true]);
        [$run, $p] = $this->project();
        $rec = $this->option($run, $p);
        $this->generating([$this->option($run, $p, ['recommendation' => 'needs_review', 'total_score' => 70, 'headline' => 'Dental care that fits your week']), $rec]);

        $r = app(AutoHeroService::class)->apply($run);

        $this->assertSame('applied', $r['status']);
        $new = $run->fresh()->currentDesignSpec();
        $this->assertSame(2, $new->version);
        $this->assertSame($rec->id, $new->design_spec_json['v16']['hero_content']['copy_option_id']);
        $this->assertSame('Checkups and whitening for the whole family', $new->design_spec_json['hero']['headline']);
        $this->assertSame('approved', $rec->fresh()->status);
    }

    public function test_nothing_is_applied_unless_an_option_is_both_recommended_and_fully_supported(): void
    {
        config(['hero_layout.auto_apply' => true]);
        [$run, $p] = $this->project();
        $this->generating([$this->option($run, $p, ['recommendation' => 'needs_review']), $this->option($run, $p, ['hard_gate_status' => 'fail', 'recommendation' => 'recommended', 'status' => 'rejected'])]);

        $this->assertSame('no_recommended', app(AutoHeroService::class)->apply($run)['status']);
        $this->assertSame(1, $run->designSpecs()->count(), 'the generated page is left exactly as it was');
    }

    public function test_a_failure_never_costs_the_redesign_and_the_switch_and_prior_choice_are_respected(): void
    {
        config(['hero_layout.auto_apply' => true]);
        [$run, $p] = $this->project();
        $this->mock(HeroCopyGenerationService::class, fn ($m) => $m->shouldReceive('generate')->once()->andThrow(new \RuntimeException('model down')));
        $this->assertSame('failed', app(AutoHeroService::class)->apply($run)['status']);
        $this->assertSame(1, $run->designSpecs()->count());

        config(['hero_layout.auto_apply' => false]);
        $this->assertSame('disabled', app(AutoHeroService::class)->apply($run)['status']);
    }

    public function test_a_page_whose_hero_wording_was_already_chosen_is_not_overwritten(): void
    {
        config(['hero_layout.auto_apply' => true]);
        [$run, $p] = $this->project();
        $this->generating([$this->option($run, $p)]);
        $this->assertSame('applied', app(AutoHeroService::class)->apply($run)['status']);

        $this->assertSame('skipped', app(AutoHeroService::class)->apply($run->fresh())['status']);
        $this->assertSame(2, $run->designSpecs()->count());
    }
}
