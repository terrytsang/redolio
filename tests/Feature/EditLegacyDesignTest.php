<?php

namespace Tests\Feature;

use App\Jobs\GenerateDesignSpecJob;
use App\Models\AnalysisRun;
use App\Models\DesignSelection;
use App\Models\DiagnosisReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;

/** A design made by the older generator has no editable layout; opening it in the editor upgrades it instead of refusing. */
class EditLegacyDesignTest extends TestCase
{
    use BuildsBlueprintSpecs;
    use RefreshDatabase;

    private function legacyRun(User $owner): AnalysisRun
    {
        $run = $this->makeRun(null, ['hero1' => 'hero_image']);
        $run->update(['user_id' => $owner->id, 'status' => 'complete']);
        $this->specFor($run, $this->legacySpec()); // no blueprint, no v15: what the older "refine" flow produced
        DiagnosisReport::create(['analysis_run_id' => $run->id, 'version' => 1, 'summary' => 's', 'issues_json' => ['issues' => []], 'model_identifier' => 't', 'prompt_version' => 'v1']);

        return $run->fresh();
    }

    public function test_the_reveal_and_the_editor_work_for_an_older_design_and_offer_to_make_it_editable(): void
    {
        $owner = User::factory()->create();
        $run = $this->legacyRun($owner);
        $this->assertArrayNotHasKey('v15', $run->currentDesignSpec()->design_spec_json);

        // The button is there (it used to be hidden), and it rebuilds rather than refusing.
        $this->actingAs($owner)->get(route('analyses.reveal', $run->public_id))->assertOk()
            ->assertSee('Edit the design')->assertSee(route('analyses.make-editable', $run->public_id), false);
        $this->actingAs($owner)->get(route('analyses.preview', $run->public_id))->assertOk()->assertSee(route('analyses.make-editable', $run->public_id), false);

        // "Edit free" / opening the editor no longer ends in "can't be edited in place".
        $page = $this->actingAs($owner)->get(route('analyses.edit', $run->public_id))->assertOk();
        $page->assertSee('Getting your')->assertSee(route('analyses.make-editable', $run->public_id), false)->assertDontSee('can’t be edited in place');
        $this->assertStringNotContainsString('pick a design direction first', (string) session('status'));
    }

    public function test_making_it_editable_rebuilds_with_the_layout_engine_and_lands_in_the_editor(): void
    {
        Queue::fake();
        $this->seed(\Database\Seeders\DesignDirectionSeeder::class);
        $this->seed(\Database\Seeders\CatalogueSeeder::class);
        $owner = User::factory()->create();
        $run = $this->legacyRun($owner);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $r = $this->actingAs($owner)->post(route('analyses.make-editable', $run->public_id));
        $sel = DesignSelection::where('analysis_run_id', $run->id)->latest('id')->first();
        $this->assertNotNull($sel, 'a new design selection is created');
        $this->assertNotEmpty($sel->blueprint_id, 'with a layout (blueprint), which is what makes it editable');
        $this->assertSame('generating', $sel->status);
        Queue::assertPushed(GenerateDesignSpecJob::class);
        $r->assertRedirect(route('analyses.design-directions.generating', [$run->public_id, $sel->id, 'edit' => 1]));

        // Clicking again while it builds just waits for the same build.
        $this->actingAs($owner)->post(route('analyses.make-editable', $run->public_id))->assertRedirect(route('analyses.design-directions.generating', [$run->public_id, $sel->id, 'edit' => 1]));
        $this->assertSame(1, DesignSelection::where('analysis_run_id', $run->id)->count());
    }

    public function test_when_the_rebuild_is_done_the_person_goes_straight_into_the_editor(): void
    {
        $owner = User::factory()->create();
        $run = $this->makeRun(null, ['hero1' => 'hero_image']);
        $run->update(['user_id' => $owner->id, 'status' => 'complete']);
        [, $spec] = $this->attach($run, $this->legacySpec());
        $sel = DesignSelection::where('analysis_run_id', $run->id)->first();
        $sel->update(['status' => 'complete', 'design_spec_id' => $spec->id]);

        $this->actingAs($owner)->get(route('analyses.design-directions.generating', [$run->public_id, $sel->id, 'edit' => 1]))->assertRedirect(route('analyses.edit', $run->public_id));
        $this->actingAs($owner)->get(route('analyses.design-directions.generating', [$run->public_id, $sel->id]))->assertRedirect(route('analyses.reveal', $run->public_id));
        $this->actingAs($owner)->post(route('analyses.make-editable', $run->public_id))->assertRedirect(route('analyses.edit', $run->public_id), 'an already editable design opens the editor');
    }

    public function test_only_the_owner_can_make_a_design_editable(): void
    {
        $owner = User::factory()->create();
        $run = $this->legacyRun($owner);
        $this->actingAs(User::factory()->create())->post(route('analyses.make-editable', $run->public_id))->assertNotFound();
        $this->assertSame(0, DesignSelection::where('analysis_run_id', $run->id)->count());
    }

    public function test_without_ready_layouts_it_says_so_instead_of_quietly_building_another_uneditable_design(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public'); // previews were captured on this install, but none of them passed
        \Illuminate\Support\Facades\Storage::disk('public')->put('blueprint-previews/local-services-01/trusted-expertise/manifest.json', json_encode(['passed' => false]));
        Queue::fake();
        $this->seed(\Database\Seeders\DesignDirectionSeeder::class);
        $this->seed(\Database\Seeders\CatalogueSeeder::class);
        $owner = User::factory()->create();
        $run = $this->legacyRun($owner);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $this->actingAs($owner)->post(route('analyses.make-editable', $run->public_id))->assertRedirect(route('analyses.reveal', $run->public_id))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'layouts aren’t available'));
        $this->assertSame(0, DesignSelection::where('analysis_run_id', $run->id)->count());
        Queue::assertNothingPushed();
    }
}
