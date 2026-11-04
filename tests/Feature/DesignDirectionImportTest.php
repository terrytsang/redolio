<?php

namespace Tests\Feature;

use App\DTO\MetaApi\MetaStageResponse;
use App\Jobs\GenerateDesignDirectionJob;
use App\Jobs\ImportDesignMdDirectionJob;
use App\Models\DesignDirection;
use App\Models\DesignDirectionGeneration;
use App\Models\User;
use App\Services\DesignDirection\GradientDeckSource;
use App\Services\DesignDirection\ReferenceScreenshot;
use App\Services\MetaApi\MetaModelClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DesignDirectionImportTest extends TestCase
{
    use RefreshDatabase;

    private const MD = "# Kobbe — Style Reference\n> harbor sketch, quiet ledger.\n\n## Colors\n| Name | Value | Role |\n|---|---|---|\n| Charcoal Ink | `#2b2b2b` | Headlines |\n| Chalk Paper | `#faf9f8` | Canvas |\n";

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function fakeGradientDeck(): void
    {
        Http::fake([
            'ui.gradientdeck.com/styles/kobbe/design-extended.md' => Http::response(self::MD, 200, ['Content-Type' => 'text/markdown']),
            'ui.gradientdeck.com/library.json' => Http::response([['slug' => 'kobbe', 'name' => 'Kobbe', 'nickname' => 'Harbor sketch, quiet ledger', 'host' => 'kobbe.io', 'industry' => 'saas', 'theme' => 'light', 'colors' => [['name' => 'Charcoal Ink', 'hex' => '#2b2b2b']]]]),
        ]);
    }

    public function test_only_gradientdeck_style_addresses_are_accepted(): void
    {
        $s = app(GradientDeckSource::class);
        $this->assertSame('kobbe', $s->slugFrom('https://ui.gradientdeck.com/styles/kobbe'));
        $this->assertSame('kobbe', $s->slugFrom('https://ui.gradientdeck.com/styles/kobbe/design.md'));
        $this->assertSame('apollo-io', $s->slugFrom('https://ui.gradientdeck.com/styles/apollo-io/design-extended.md'));
        foreach (['https://evil.example/styles/kobbe', 'http://ui.gradientdeck.com/styles/kobbe', 'https://ui.gradientdeck.com.evil.example/styles/kobbe', 'https://ui.gradientdeck.com/library.json', 'https://ui.gradientdeck.com/styles/../../x'] as $bad) {
            try {
                $s->slugFrom($bad);
                $this->fail("accepted: $bad");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_admin_form_queues_an_import_and_rejects_other_hosts(): void
    {
        Bus::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.design-directions.import-design-md'), ['design_md_url' => 'https://example.com/x'])->assertSessionHasErrors('design_md_url');
        Bus::assertNotDispatched(ImportDesignMdDirectionJob::class);

        $this->actingAs($admin)->post(route('admin.design-directions.import-design-md'), ['design_md_url' => 'https://ui.gradientdeck.com/styles/kobbe'])->assertRedirect();
        Bus::assertDispatched(ImportDesignMdDirectionJob::class);
        $this->assertSame('https://ui.gradientdeck.com/styles/kobbe', DesignDirectionGeneration::first()->source_url);
        $this->actingAs(User::factory()->create())->post(route('admin.design-directions.import-design-md'), ['design_md_url' => 'kobbe'])->assertForbidden();
    }

    public function test_the_import_converts_a_design_md_into_a_hidden_direction_with_the_inspiring_sites_screenshot(): void
    {
        $this->fakeGradientDeck();
        $gen = DesignDirectionGeneration::create(['requested_name' => '', 'direction_key' => 'kobbe_style', 'source_url' => 'https://ui.gradientdeck.com/styles/kobbe', 'status' => 'pending', 'requested_by' => $this->admin()->id]);

        $model = Mockery::mock(MetaModelClient::class);
        $model->shouldReceive('requestHash')->andReturn('h');
        $model->shouldReceive('generateDesignDirectionFromDesignMd')->once()->withArgs(fn ($md, $ctx) => str_contains($md, 'Charcoal Ink') && $ctx['name'] === 'Kobbe' && $ctx['palette'][0]['hex'] === '#2b2b2b')
            ->andReturn(new MetaStageResponse(['template_key' => 'minimal', 'family' => 'Warm editorial analytics', 'description' => 'Quiet, warm and editorial.', 'best_for' => ['SaaS'], 'brand_traits' => ['calm'],
                // an unreadable mapping (text almost the same as the background) must be corrected
                'color_system' => ['background' => '#FAF9F8', 'surface' => '#F5F3F0', 'text' => '#F0EFEE', 'text_muted' => '#75736E', 'accent' => '#2B2B2B', 'accent_contrast' => '#2B2B2B'],
                'typography_system' => ['display' => 'font-sans', 'body' => 'font-sans'], 'layout_compositions' => ['offset editorial headings'], 'hero_patterns' => ['left annotation'], 'section_patterns' => ['ledger rows'],
                'image_treatment' => 'illustrated', 'spacing_system' => 'spacious', 'component_theme' => 'compact dark controls', 'responsive_rules' => 'stack', 'accessibility_rules' => 'AA'], 'm', 1, 1, 1));
        $this->app->instance(MetaModelClient::class, $model);
        $shots = Mockery::mock(ReferenceScreenshot::class);
        $shots->shouldReceive('capture')->once()->with('https://kobbe.io/', Mockery::type('string'))->andReturn('/storage/direction-previews/kobbe.jpg');
        $this->app->instance(ReferenceScreenshot::class, $shots);

        (new ImportDesignMdDirectionJob($gen->id))->handle(app(GradientDeckSource::class), $model, app(\App\Services\MetaApi\Validation\DesignDirectionCaptureValidator::class), app(\App\Services\Orchestration\ModelCallLogger::class), $shots);

        $gen->refresh();
        $this->assertSame('complete', $gen->status);
        $d = DesignDirection::find($gen->design_direction_id);
        $this->assertFalse((bool) $d->is_selectable, 'a new direction waits for the admin to add it to the gallery');
        $this->assertSame('Kobbe style', $d->name);
        $r = $d->recipe_json;
        $this->assertSame('minimal', $r['template_key']);
        $this->assertSame('/storage/direction-previews/kobbe.jpg', $r['preview_thumbnail']);
        $this->assertSame('https://kobbe.io/', $r['source_url']);
        $this->assertSame('https://ui.gradientdeck.com/styles/kobbe/design.md', $r['inspired_by']['design_md']);
        $this->assertSame('#FAF9F8', $r['color_system']['background']);
        $this->assertSame('#111111', $r['color_system']['text'], 'unreadable body text is replaced by near-black on a light page');
        $this->assertSame('#FFFFFF', $r['color_system']['accent_contrast']);
    }

    public function test_an_unknown_style_fails_the_import_cleanly(): void
    {
        Http::fake(['ui.gradientdeck.com/*' => Http::response('nope', 404)]);
        $gen = DesignDirectionGeneration::create(['requested_name' => '', 'direction_key' => 'x', 'source_url' => 'https://ui.gradientdeck.com/styles/nope', 'status' => 'pending', 'requested_by' => $this->admin()->id]);
        $model = Mockery::mock(MetaModelClient::class);
        (new ImportDesignMdDirectionJob($gen->id))->handle(app(GradientDeckSource::class), $model, app(\App\Services\MetaApi\Validation\DesignDirectionCaptureValidator::class), app(\App\Services\Orchestration\ModelCallLogger::class), Mockery::mock(ReferenceScreenshot::class));
        $this->assertSame('failed', $gen->fresh()->status);
        $this->assertStringContainsString('could not be found', $gen->fresh()->error_message);
    }

    public function test_a_killed_or_lost_capture_is_marked_failed_and_the_popup_stops_spinning(): void
    {
        $admin = $this->admin();
        $gen = DesignDirectionGeneration::create(['requested_name' => '', 'direction_key' => 'shopify', 'source_url' => 'https://shopify.com', 'status' => 'running', 'requested_by' => $admin->id]);

        (new GenerateDesignDirectionJob($gen->id))->failed(new RuntimeException('timed out'));
        $this->assertSame('failed', $gen->fresh()->status);

        // Even with no failure callback at all (worker killed), a capture stuck for 20+ minutes is failed when the page is opened.
        $stuck = DesignDirectionGeneration::create(['requested_name' => '', 'direction_key' => 'old', 'source_url' => 'https://shopify.com', 'status' => 'running', 'requested_by' => $admin->id]);
        DesignDirectionGeneration::where('id', $stuck->id)->update(['updated_at' => now()->subMinutes(25)]);
        $this->actingAs($admin)->get(route('admin.design-directions.generating', $stuck))->assertRedirect(route('admin.design-directions.index'))->assertSessionHas('status_error', true);
        $this->assertSame('failed', $stuck->fresh()->status);

        // A fresh one keeps showing progress, and a completed one lands on the new direction with a clear message.
        $fresh = DesignDirectionGeneration::create(['requested_name' => '', 'direction_key' => 'new', 'source_url' => 'https://shopify.com', 'status' => 'running', 'requested_by' => $admin->id]);
        $this->actingAs($admin)->get(route('admin.design-directions.generating', $fresh))->assertOk()->assertSee('Capturing');
        $d = DesignDirection::create(['direction_key' => 'shopify_x', 'name' => 'Commerce Clarity', 'version' => 1, 'is_selectable' => false, 'created_at' => now(), 'recipe_json' => ['template_key' => 'minimal']]);
        $fresh->update(['status' => 'complete', 'design_direction_id' => $d->id]);
        $this->actingAs($admin)->get(route('admin.design-directions.generating', $fresh))->assertRedirect(route('admin.design-directions.edit', $d))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'A new Design Direction “Commerce Clarity” has been created from https://shopify.com'));
    }

    public function test_the_reference_screenshot_falls_back_to_browserless_and_is_optional(): void
    {
        Storage::fake('public');
        config(['redo.browser.provider' => 'browserbase', 'redo.browser.fallback' => 'browserless', 'redo.browser.browserbase_api_key' => '', 'redo.browser.browserless_token' => 't', 'redo.browser.browserless_url' => 'https://prod.browserless.test']);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        Http::fake(['prod.browserless.test/screenshot*' => Http::sequence()->push($png, 200, ['Content-Type' => 'image/png'])->push('', 500)]);

        $url = app(ReferenceScreenshot::class)->capture('https://kobbe.io/', 'kobbe_style');
        $this->assertStringContainsString('direction-previews/kobbe_style-', (string) $url);
        Storage::disk('public')->assertExists('direction-previews/'.basename($url));

        $this->assertNull(app(ReferenceScreenshot::class)->capture('http://169.254.169.254/', 'x'), 'private addresses never reach a browser');
        $this->assertNull(app(ReferenceScreenshot::class)->capture('https://kobbe.io/', 'y'), 'no screenshot is not an error');
    }

    public function test_a_direction_capture_run_keeps_its_purpose_and_stays_out_of_the_admin_stats(): void
    {
        $run = \App\Models\AnalysisRun::create(['public_id' => 'dc1', 'user_id' => null, 'submitted_url' => 'https://shopify.com', 'normalized_url' => 'https://shopify.com/', 'idempotency_key' => 'k1', 'status' => 'complete', 'current_stage' => 'ready', 'source_mode' => 'live', 'purpose' => 'direction_capture']);
        $this->assertSame('direction_capture', $run->fresh()->purpose, 'purpose must be mass-assignable, or captures run the whole redesign pipeline');

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('shopify.com');
    }
}
