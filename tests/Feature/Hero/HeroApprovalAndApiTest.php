<?php

namespace Tests\Feature\Hero;

use App\Jobs\RunHeroGenerationJob;
use App\Models\AnalysisRun;
use App\Models\HeroCopyApproval;
use App\Models\HeroCopyOption;
use App\Models\HeroGeneration;
use App\Models\PositioningEvidence;
use App\Models\PositioningProfile;
use App\Models\User;
use App\Services\Editing\SpecEditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;

class HeroApprovalAndApiTest extends TestCase
{
    use BuildsBlueprintSpecs;
    use RefreshDatabase;

    private function project(): array
    {
        $run = $this->makeRun(null, ['team1' => 'team_portrait']);
        [$res, $spec] = $this->attach($run, $this->legacySpec());
        $this->assertTrue($res['attached'], implode('; ', $res['errors']));
        $p = PositioningProfile::create(['analysis_run_id' => $run->id, 'version' => 1, 'business_category' => 'Family dental practice', 'business_model' => 'service_provider', 'status' => 'ready', 'source_mode' => 'dna', 'created_at' => now(),
            'existing_hero_json' => ['headline' => 'Welcome', 'subheadline' => 'Welcome', 'ctas' => ['Learn more']]]);
        foreach ([['category', 'Family dental practice', 'category.1'], ['offering', 'Checkups', 'offering.1'], ['offering', 'Whitening', 'offering.2']] as [$f, $v, $k]) {
            PositioningEvidence::create(['positioning_profile_id' => $p->id, 'claim_key' => $k, 'field' => $f, 'claim_value' => $v, 'status' => 'verified_source', 'confidence' => 'high', 'created_at' => now()]);
        }

        return [$run, $spec, $p, User::factory()->create()];
    }

    private function option(AnalysisRun $run, PositioningProfile $p, array $over = []): HeroCopyOption
    {
        return HeroCopyOption::create($over + ['analysis_run_id' => $run->id, 'positioning_profile_id' => $p->id, 'batch' => 1, 'strategy_id' => 'direct-positioning', 'eyebrow' => null,
            'headline' => 'Checkups and whitening for the whole family', 'description' => 'Brightleaf is a family dental practice offering checkups and whitening, with appointments that fit around school and work.',
            'primary_cta_json' => ['label' => 'Book a visit', 'destination_key' => 'section:contact'], 'secondary_cta_json' => null, 'trust_signals_json' => [], 'evidence_ids_json' => ['category.1', 'offering.1', 'offering.2'],
            'total_score' => 82, 'recommendation' => 'recommended', 'hard_gate_status' => 'pass', 'validation_json' => ['issues' => []], 'status' => 'candidate', 'source' => 'model', 'created_at' => now()]);
    }

    public function test_approving_an_option_writes_a_new_spec_version_with_hero_content_and_keeps_the_old_one(): void
    {
        [$run, $spec, $p, $user] = $this->project();
        $o = $this->option($run, $p);

        $res = $this->actingAs($user)->postJson(route('api.hero.approve', [$run->public_id, $o->id]))->assertOk();

        $new = $run->fresh()->currentDesignSpec();
        $this->assertSame(2, $new->version);
        $this->assertSame($new->version, $res->json('spec_version'));
        $hc = $new->design_spec_json['v16']['hero_content'];
        $this->assertSame('approved', $hc['approval_status']);
        $this->assertSame($o->id, $hc['copy_option_id']);
        $this->assertSame('pass', $hc['validation']['factual_gate']);
        $this->assertSame('pending', $hc['validation']['desktop_fit'], 'rendered fit is not claimed before it is measured');
        $heroProps = collect($new->design_spec_json['v15']['pages'][0]['sections'])->firstWhere('component_id', 'hero')['props'];
        $this->assertSame('Checkups and whitening for the whole family', $heroProps['headline']);
        $this->assertSame('Book a visit', $heroProps['primary_cta']['label']);
        $this->assertSame('Checkups and whitening for the whole family', $new->design_spec_json['hero']['headline']);
        $this->assertSame('Gentle care for the whole family', collect($spec->fresh()->design_spec_json['v15']['pages'][0]['sections'])->firstWhere('component_id', 'hero')['props']['headline']);
        $this->assertSame('approved', $o->fresh()->status);
        $this->assertSame(1, HeroCopyApproval::where('hero_copy_option_id', $o->id)->where('approval_status', 'approved')->count());
    }

    public function test_an_option_that_failed_a_hard_gate_can_never_be_approved(): void
    {
        [$run, $spec, $p, $user] = $this->project();
        $bad = $this->option($run, $p, ['hard_gate_status' => 'fail', 'recommendation' => 'rejected', 'status' => 'rejected']);

        $this->actingAs($user)->postJson(route('api.hero.approve', [$run->public_id, $bad->id]))->assertStatus(422);
        $this->assertSame(1, $run->designSpecs()->count());
    }

    public function test_edits_made_while_approving_are_rechecked_by_the_same_gates(): void
    {
        [$run, $spec, $p, $user] = $this->project();
        $o = $this->option($run, $p);

        $this->actingAs($user)->postJson(route('api.hero.approve', [$run->public_id, $o->id]), ['headline' => 'The leading dentist serving 10,000 patients'])
            ->assertStatus(422);
        $this->assertSame(1, $run->designSpecs()->count());

        $ok = $this->actingAs($user)->postJson(route('api.hero.approve', [$run->public_id, $o->id]), ['headline' => 'Family checkups and whitening'])->assertOk();
        $this->assertSame('user_edited', $run->fresh()->currentDesignSpec()->design_spec_json['v16']['hero_content']['approval_status']);
        $this->assertSame(['headline' => 'Family checkups and whitening'], HeroCopyApproval::where('hero_copy_option_id', $o->id)->first()->user_edits_json);
    }

    public function test_hand_editing_the_hero_after_approval_marks_it_user_edited_and_warns_about_unsupported_claims(): void
    {
        [$run, $spec, $p, $user] = $this->project();
        $o = $this->option($run, $p);
        $this->actingAs($user)->postJson(route('api.hero.approve', [$run->public_id, $o->id]))->assertOk();
        $cur = $run->fresh()->currentDesignSpec();
        $hid = collect($cur->design_spec_json['v15']['pages'][0]['sections'])->firstWhere('component_id', 'hero')['instance_id'];

        $r = app(SpecEditService::class)->apply($cur, $cur->version, [['op' => 'set', 'instance_id' => $hid, 'path' => 'headline', 'value' => 'Serving 10,000 happy patients']], $user);

        $hc = $r['spec']->design_spec_json['v16']['hero_content'];
        $this->assertSame('user_edited', $hc['approval_status']);
        $this->assertSame('fail', $hc['validation']['factual_gate']);
        $this->assertContains('confirm', array_column($r['warnings'], 'level'), 'the user is asked to confirm it, not blocked');
        $this->assertSame('Serving 10,000 happy patients', $r['spec']->design_spec_json['hero']['headline']);
    }

    public function test_api_requires_sign_in_and_the_owner(): void
    {
        [$run, $spec, $p, $user] = $this->project();

        $this->getJson(route('api.hero.positioning', $run->public_id))->assertStatus(401);

        $run->update(['user_id' => User::factory()->create()->id]);
        $this->actingAs($user)->getJson(route('api.hero.positioning', $run->public_id))->assertNotFound();
    }

    public function test_an_unclaimed_project_is_claimed_by_its_first_signed_in_editor(): void
    {
        [$run, $spec, $p, $user] = $this->project();
        $this->assertNull($run->user_id);

        $this->actingAs($user)->getJson(route('api.hero.positioning', $run->public_id))->assertOk()->assertJsonPath('business_category', 'Family dental practice');

        $this->assertSame($user->id, $run->fresh()->user_id);
    }

    public function test_generation_is_queued_returns_a_job_id_and_is_idempotent(): void
    {
        Queue::fake();
        [$run, $spec, $p, $user] = $this->project();

        $a = $this->actingAs($user)->postJson(route('api.hero.generate'.'', $run->public_id), [], ['Idempotency-Key' => 'k1'])->assertStatus(202);
        $b = $this->actingAs($user)->postJson(route('api.hero.generate', $run->public_id), [], ['Idempotency-Key' => 'k1'])->assertStatus(202);

        $this->assertSame($a->json('job_id'), $b->json('job_id'));
        Queue::assertPushed(RunHeroGenerationJob::class, 1);
        $this->actingAs($user)->getJson(route('api.hero.job', [$run->public_id, $a->json('job_id')]))->assertOk()->assertJsonPath('status', 'pending');
    }

    public function test_options_and_versions_and_corrections_endpoints(): void
    {
        [$run, $spec, $p, $user] = $this->project();
        $o = $this->option($run, $p);
        $this->actingAs($user);

        $this->getJson(route('api.hero.options', $run->public_id))->assertOk()->assertJsonPath('options.0.option_id', $o->id)->assertJsonPath('options.0.score.total', 82);
        $this->patchJson(route('api.hero.positioning.correct', $run->public_id), ['action' => 'add', 'field' => 'geography', 'value' => 'Austin, Texas'])->assertOk();
        $this->assertTrue($p->evidence()->where('claim_value', 'Austin, Texas')->where('status', 'user_confirmed')->exists());
        $this->postJson(route('api.hero.diagnose', $run->public_id))->assertOk()->assertJsonStructure(['findings', 'relocate', 'summary']);

        $this->postJson(route('api.hero.approve', [$run->public_id, $o->id]))->assertOk();
        $v = $this->getJson(route('api.hero.versions', $run->public_id))->assertOk();
        $this->assertCount(2, $v->json('versions'));
        $this->assertSame('approved', $v->json('hero_history.0.status'));

        $o2 = $this->option($run, $p, ['headline' => 'Other', 'batch' => 2]);
        $this->postJson(route('api.hero.reject', [$run->public_id, $o2->id]))->assertOk()->assertJsonPath('option.status', 'rejected');
    }

    public function test_batches_count_numerically_past_nine_and_the_latest_batch_is_the_highest_number(): void
    {
        [$run, $spec, $p, $user] = $this->project();
        foreach ([9, 10] as $b) {
            $this->option($run, $p, ['batch' => $b, 'headline' => "Batch {$b} headline for the family"]);
        }

        $this->assertSame(10, HeroCopyOption::latestBatch($run->id));
        $r = $this->actingAs($user)->getJson(route('api.hero.options', $run->public_id))->assertOk();
        $this->assertSame([10], collect($r->json('options'))->pluck('batch')->unique()->values()->all());
    }

    public function test_a_markup_only_headline_is_refused_cleanly_not_a_server_error(): void
    {
        [$run, $spec, $p, $user] = $this->project();
        $o = $this->option($run, $p);

        $this->actingAs($user)->postJson(route('api.hero.approve', [$run->public_id, $o->id]), ['headline' => '<b></b>'])->assertStatus(422);
    }

    public function test_a_job_that_never_finished_is_failed_and_a_new_one_can_start(): void
    {
        Queue::fake();
        [$run, $spec, $p, $user] = $this->project();
        $dead = HeroGeneration::create(['analysis_run_id' => $run->id, 'kind' => 'options', 'status' => 'running', 'created_at' => now()->subMinutes(30)]);

        $r = $this->actingAs($user)->postJson(route('api.hero.generate', $run->public_id))->assertStatus(202);

        $this->assertNotSame($dead->id, $r->json('job_id'));
        $this->assertSame('failed', $dead->fresh()->status);
    }
}
