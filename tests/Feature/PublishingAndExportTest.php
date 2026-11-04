<?php

namespace Tests\Feature;

use App\Models\AnalysisRun;
use App\Models\Deployment;
use App\Models\Export;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\DemoFixturesSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublishingAndExportTest extends TestCase
{
    use RefreshDatabase;

    private function loadFixtureRun(): AnalysisRun
    {
        $this->seed(DemoFixturesSeeder::class);
        $response = $this->get('/demo/brightleaf-dental');
        $publicId = basename($response->headers->get('Location'));

        return AnalysisRun::where('public_id', $publicId)->firstOrFail();
    }

    private function starterUser(AnalysisRun $run): User
    {
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();
        $run->update(['user_id' => $user->id]);

        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => Plan::where('plan_key', 'starter')->value('id'),
            'status' => 'active',
            'started_at' => now(),
        ]);

        return $user;
    }

    public function test_free_user_is_redirected_to_pricing_when_publishing(): void
    {
        $run = $this->loadFixtureRun();
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();
        $run->update(['user_id' => $user->id]);

        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => Plan::where('plan_key', 'free')->value('id'),
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->actingAs($user)
            ->get("/analyses/{$run->public_id}/publish")
            ->assertRedirect(route('pricing'));

        $this->assertDatabaseCount('deployments', 0);
    }

    public function test_starter_user_can_approve_and_publish(): void
    {
        $run = $this->loadFixtureRun();
        $user = $this->starterUser($run);

        $this->actingAs($user)
            ->post("/analyses/{$run->public_id}/approve-design")
            ->assertRedirect(route('analyses.launch', $run->public_id));

        $this->assertDatabaseCount('approved_designs', 1);

        $this->actingAs($user)
            ->post("/analyses/{$run->public_id}/publish", ['slug' => 'brightleaf-test'])
            ->assertRedirect(route('analyses.publish', $run->public_id));

        $run->refresh();
        $this->assertSame('brightleaf-test', $run->published_slug);
        $this->assertNotNull($run->active_deployment_id);
        $this->assertSame('live', $run->activeDeployment->status);
    }

    public function test_published_site_without_trailing_slash_redirects_to_add_one(): void
    {
        // Laravel's test client normalizes away trailing slashes before
        // building the request (TestCase::prepareUrlForRequest), so the
        // redirect target itself can't be re-requested faithfully here —
        // confirmed working live via curl; this only verifies the redirect fires.
        $run = $this->loadFixtureRun();
        $user = $this->starterUser($run);

        $this->actingAs($user)->post("/analyses/{$run->public_id}/publish", ['slug' => 'brightleaf-public']);

        $redirect = $this->get('/sites/brightleaf-public');
        $redirect->assertRedirect();
        $this->assertStringEndsWith('/sites/brightleaf-public/', $redirect->headers->get('Location'));
    }

    public function test_published_site_assets_and_index_are_servable(): void
    {
        $run = $this->loadFixtureRun();
        $user = $this->starterUser($run);

        $this->actingAs($user)->post("/analyses/{$run->public_id}/publish", ['slug' => 'brightleaf-assets']);

        $this->get('/sites/brightleaf-assets/index.html')
            ->assertStatus(200)
            ->assertSee('assets/app.css', false);

        $this->get('/sites/brightleaf-assets/assets/app.css')
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/css; charset=utf-8');
    }

    public function test_unknown_slug_is_not_served(): void
    {
        $this->get('/sites/does-not-exist/')->assertStatus(404);
    }

    public function test_slug_cannot_be_reserved_word_or_duplicate(): void
    {
        $run = $this->loadFixtureRun();
        $user = $this->starterUser($run);

        $this->actingAs($user)
            ->post("/analyses/{$run->public_id}/publish", ['slug' => 'admin'])
            ->assertSessionHasErrors('slug');

        $this->actingAs($user)->post("/analyses/{$run->public_id}/publish", ['slug' => 'taken-slug']);

        $otherRun = $this->loadFixtureRun();
        $otherRun->update(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/analyses/{$otherRun->public_id}/publish", ['slug' => 'taken-slug'])
            ->assertSessionHasErrors('slug');
    }

    public function test_starter_user_can_export_a_static_zip(): void
    {
        $run = $this->loadFixtureRun();
        $user = $this->starterUser($run);

        $response = $this->actingAs($user)->post("/analyses/{$run->public_id}/exports");

        $this->assertDatabaseCount('exports', 1);
        $export = Export::first();
        $this->assertTrue(Storage::disk('local')->exists($export->storage_path));
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringEndsWith('/ready', $location);

        // After "Export now": a page that starts the download and explains how to upload the files to ordinary web hosting.
        $page = $this->actingAs($user)->get($location)->assertOk();
        $page->assertSee('Your website files', false)->assertSee('How to put it on your website')->assertSee('File Manager')->assertSee('public_html')->assertSee('Extract')
            ->assertSee('installation service')->assertSee('mailto:support@example.test?subject=', false)->assertSee('support@example.test');
        $token = explode('/', trim(parse_url($location, PHP_URL_PATH), '/'))[1];
        $page->assertSee(route('exports.download', $token), false);
        $this->actingAs($user)->get(route('exports.download', $token))->assertOk()->assertDownload('redo-export.zip');

        // The README inside the zip carries the same guide.
        $zip = new \ZipArchive;
        $zip->open(Storage::disk('local')->path($export->storage_path));
        $readme = $zip->getFromName('README.md');
        $zip->close();
        $this->assertStringContainsString('public_html', $readme);
        $this->assertStringContainsString('support@example.test', $readme);

        $this->get(route('exports.ready', 'not-a-real-token'))->assertRedirect(route('landing'));
    }

    public function test_free_user_without_export_pass_cannot_export(): void
    {
        $run = $this->loadFixtureRun();
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();
        $run->update(['user_id' => $user->id]);

        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => Plan::where('plan_key', 'free')->value('id'),
            'status' => 'active',
            'started_at' => now(),
        ]);

        $this->actingAs($user)
            ->post("/analyses/{$run->public_id}/exports")
            ->assertRedirect(route('pricing'));

        $this->assertDatabaseCount('exports', 0);
    }

    public function test_rollback_creates_new_deployment_pointing_to_prior_version(): void
    {
        $run = $this->loadFixtureRun();
        $user = $this->starterUser($run);

        $this->actingAs($user)->post("/analyses/{$run->public_id}/publish", ['slug' => 'rollback-test']);
        $firstDeployment = Deployment::where('analysis_run_id', $run->id)->first();

        $this->actingAs($user)->post("/analyses/{$run->public_id}/publish");
        $run->refresh();
        $this->assertNotSame($firstDeployment->id, $run->active_deployment_id);

        $this->actingAs($user)
            ->post("/analyses/{$run->public_id}/deployments/{$firstDeployment->id}/rollback")
            ->assertRedirect(route('analyses.publish', $run->public_id));

        $run->refresh();
        $this->assertSame('rollback-test', $run->published_slug);
        $this->assertDatabaseCount('deployments', 3);
    }

    public function test_export_pass_downloads_run_out_after_three_and_point_to_a_plan(): void
    {
        $run = $this->loadFixtureRun();
        $this->seed(PlanSeeder::class);
        $user = User::factory()->create();
        $run->update(['user_id' => $user->id]);
        Subscription::create(['user_id' => $user->id, 'plan_id' => \App\Models\Plan::where('plan_key', 'free')->value('id'), 'status' => 'active', 'started_at' => now()]);
        $pass = \App\Models\OneTimePurchase::create(['user_id' => $user->id, 'analysis_run_id' => $run->id, 'purchase_type' => 'export_pass', 'status' => 'paid', 'exports_remaining' => 3, 'saves_remaining' => 2]);

        foreach ([2, 1, 0] as $left) {
            $this->actingAs($user)->post("/analyses/{$run->public_id}/exports")->assertRedirect()->assertSessionMissing('status');
            $this->assertSame($left, $pass->fresh()->exports_remaining);
        }
        $this->assertDatabaseCount('exports', 3);

        $this->actingAs($user)->post("/analyses/{$run->public_id}/exports")->assertRedirect(route('analyses.upgrade', $run->public_id))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'used all the downloads included'));
        $this->assertDatabaseCount('exports', 3);

        // Someone else's project is never exported (or attached to a pass) by another account.
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post("/analyses/{$run->public_id}/exports")->assertNotFound();
    }
}
