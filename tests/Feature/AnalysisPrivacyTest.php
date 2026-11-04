<?php

namespace Tests\Feature;

use App\Models\AnalysisRun;
use App\Models\Deployment;
use App\Models\DesignSelection;
use App\Models\User;
use App\Models\VisualQaReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;

/** An analysis belongs to the person who made it: nobody else (signed in or not) can open it, except an admin. */
class AnalysisPrivacyTest extends TestCase
{
    use BuildsBlueprintSpecs;
    use RefreshDatabase;

    private function ownedRun(User $owner): AnalysisRun
    {
        $run = $this->makeRun(null, ['hero1' => 'hero_image']);
        $run->update(['user_id' => $owner->id, 'status' => 'complete']);
        $this->attach($run, $this->legacySpec());

        return $run->fresh();
    }

    /** @return array<string, string> every page and data URL of an analysis, by name */
    private function urls(AnalysisRun $run): array
    {
        $id = $run->public_id;

        return [
            'overview' => "/analyses/{$id}", 'reveal' => "/analyses/{$id}/reveal", 'progress' => "/analyses/{$id}/progress", 'website-dna' => "/analyses/{$id}/website-dna",
            'diagnosis' => "/analyses/{$id}/diagnosis", 'evidence' => "/analyses/{$id}/evidence/EV-001", 'directions' => "/analyses/{$id}/design-directions",
            'layouts' => "/analyses/{$id}/design-directions/layout", 'preview' => "/analyses/{$id}/preview", 'preview-v1' => "/analyses/{$id}/preview/1",
            'next-redo' => "/analyses/{$id}/next-redo", 'launch' => "/analyses/{$id}/launch", 'edit' => "/analyses/{$id}/edit", 'edit-frame' => "/analyses/{$id}/edit/frame",
            'upgrade' => "/analyses/{$id}/upgrade", 'publish' => "/analyses/{$id}/publish", 'hero-options' => "/api/projects/{$id}/hero/options", 'positioning' => "/api/projects/{$id}/positioning",
        ];
    }

    public function test_only_the_owner_and_admins_can_open_an_owned_analysis(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $run = $this->ownedRun($owner);
        $other = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ($this->urls($run) as $name => $url) {
            // The public (not signed in): sent to sign in, never shown anything.
            $guest = $this->get($url);
            $this->assertContains($guest->getStatusCode(), [302, 401], "guest must not open {$name}");
            if ($guest->getStatusCode() === 302) {
                $this->assertStringContainsString('/login', $guest->headers->get('Location'), "guest is sent to sign in: {$name}");
            }
            $this->assertStringNotContainsString('Gentle care', (string) $guest->getContent());

            // Another signed-in user: "not found", so it does not even reveal that the page exists.
            $this->actingAs($other)->get($url)->assertNotFound();
            auth()->logout();
        }

        // The owner and an admin get in.
        $this->actingAs($owner)->get("/analyses/{$run->public_id}/reveal")->assertOk();
        $this->actingAs($admin)->get("/analyses/{$run->public_id}/reveal")->assertOk();
        $this->actingAs($owner)->get("/analyses/{$run->public_id}/website-dna")->assertOk();
    }

    public function test_writes_and_deletes_are_closed_to_everyone_else_too(): void
    {
        $owner = User::factory()->create();
        $run = $this->ownedRun($owner);
        $other = User::factory()->create();
        $id = $run->public_id;

        $this->actingAs($other)->delete("/analyses/{$id}")->assertNotFound();
        $this->actingAs($other)->postJson("/analyses/{$id}/redo")->assertNotFound();
        $this->actingAs($other)->postJson("/analyses/{$id}/retry")->assertNotFound();
        $this->actingAs($other)->postJson("/analyses/{$id}/design-directions", ['direction_key' => 'x'])->assertNotFound();
        $this->actingAs($other)->postJson("/analyses/{$id}/edit", ['base_version' => 1, 'ops' => [['op' => 'set']]])->assertNotFound();
        $this->actingAs($other)->postJson("/analyses/{$id}/exports")->assertNotFound();
        $this->actingAs($other)->postJson("/analyses/{$id}/leads", ['email' => 'x@example.com'])->assertNotFound();
        $this->actingAs($other)->patchJson("/analyses/{$id}/website-dna", ['business_name' => 'Hijacked'])->assertNotFound();
        $this->assertNotNull(AnalysisRun::find($run->id), 'the analysis is still there');
        $this->assertSame($owner->id, $run->fresh()->user_id);

        // A guest cannot write either.
        auth()->logout();
        $this->deleteJson("/analyses/{$id}")->assertStatus(401);
        $this->postJson("/analyses/{$id}/redo")->assertStatus(401);
    }

    public function test_a_visitors_own_scan_before_signing_up_stays_reachable_by_its_private_link(): void
    {
        $run = $this->makeRun(null, ['hero1' => 'hero_image']);
        $run->update(['user_id' => null, 'status' => 'complete']);

        $this->get("/analyses/{$run->public_id}/progress")->assertOk();
        $this->actingAs(User::factory()->create())->get("/analyses/{$run->public_id}/progress")->assertOk();
    }

    public function test_an_id_from_another_persons_analysis_cannot_be_read_through_your_own(): void
    {
        $mine = $this->ownedRun($me = User::factory()->create());
        $theirs = $this->ownedRun(User::factory()->create());
        $report = VisualQaReport::create(['design_spec_id' => $theirs->currentDesignSpec()->id, 'status' => 'complete', 'report_json' => ['findings' => [['status' => 'failed', 'check' => 'secret']]], 'created_at' => now()]);
        $selection = DesignSelection::create(['analysis_run_id' => $theirs->id, 'direction_key' => 'trusted-expertise', 'direction_version' => 1, 'blueprint_id' => 'local-services-01', 'blueprint_version' => 1, 'category_id' => 'services', 'selected_at' => now()]);
        $deployment = Deployment::create(['analysis_run_id' => $theirs->id, 'design_spec_id' => $theirs->currentDesignSpec()->id, 'slug' => 'their-site', 'deployment_number' => 1, 'status' => 'live']);

        $this->actingAs($me)->getJson("/analyses/{$mine->public_id}/visual-qa/{$report->id}")->assertNotFound();
        $this->actingAs($me)->post("/analyses/{$mine->public_id}/visual-qa/{$report->id}/refine")->assertNotFound();
        $this->actingAs($me)->get("/analyses/{$mine->public_id}/design-directions/generating/{$selection->id}")->assertNotFound();
        $this->actingAs($me)->post("/analyses/{$mine->public_id}/deployments/{$deployment->id}/rollback")->assertNotFound();
    }

    public function test_the_dashboard_only_lists_your_own_analyses(): void
    {
        $owner = User::factory()->create();
        $mine = $this->ownedRun($owner);
        $theirs = $this->ownedRun(User::factory()->create());

        $page = $this->actingAs($owner)->get('/dashboard')->assertOk();
        $page->assertSee($mine->public_id, false)->assertDontSee($theirs->public_id, false);
    }
}
