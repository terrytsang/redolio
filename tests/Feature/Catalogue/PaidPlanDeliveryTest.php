<?php

namespace Tests\Feature\Catalogue;

use App\Models\AnalysisRun;
use App\Models\OneTimePurchase;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\VisualQa\BlueprintRefiner;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\DesignDirectionSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;
use ZipArchive;

/** HTML export and publishing of blueprint sites, gated by the paid plans. */
class PaidPlanDeliveryTest extends TestCase
{
    use BuildsBlueprintSpecs;
    use RefreshDatabase;

    private array $slugs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DesignDirectionSeeder::class);
        $this->seed(CatalogueSeeder::class);
        $this->seed(PlanSeeder::class);
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        foreach ($this->slugs as $slug) {
            File::deleteDirectory(storage_path("app/private/sites/{$slug}"));
        }
        File::deleteDirectory(storage_path('app/private/exports'));
        parent::tearDown();
    }

    private function siteFor(string $plan, array $legacy = []): array
    {
        $user = User::factory()->create();
        Subscription::create(['user_id' => $user->id, 'plan_id' => Plan::where('plan_key', $plan)->value('id'), 'status' => 'active', 'started_at' => now()]);
        $run = $this->makeRun();
        $run->update(['user_id' => $user->id]);
        $this->attach($run, $this->legacySpec($legacy));

        return [$user, $run->fresh()];
    }

    private function publish(User $user, AnalysisRun $run, string $slug)
    {
        $this->slugs[] = $slug;

        return $this->actingAs($user)->post("/analyses/{$run->public_id}/publish", ['slug' => $slug]);
    }

    public function test_free_plan_cannot_publish_or_download_html(): void
    {
        [$free, $run] = $this->siteFor('free');

        $this->actingAs($free)->get("/analyses/{$run->public_id}/publish")->assertRedirect(route('pricing'));
        $this->actingAs($free)->post("/analyses/{$run->public_id}/publish", ['slug' => 'free-try'])->assertRedirect(route('pricing'));
        $this->actingAs($free)->post("/analyses/{$run->public_id}/exports")->assertRedirect(route('pricing'));
        $this->assertDatabaseCount('deployments', 0);
        $this->assertDatabaseCount('exports', 0);
    }

    public function test_starter_publishes_a_blueprint_site_that_is_served_locked_down(): void
    {
        [$user, $run] = $this->siteFor('starter');

        $this->publish($user, $run, 'calder-voss-test')->assertRedirect(route('analyses.publish', $run->public_id));
        $run->refresh();
        $this->assertSame('live', $run->activeDeployment->status);
        $this->assertSame('calder-voss-test', $run->published_slug);

        $page = $this->get('/sites/calder-voss-test/index.html');
        $page->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $html = $page->getContent();

        // The blueprint site itself.
        $this->assertStringContainsString('data-blueprint="local-services-01"', $html);
        $this->assertStringContainsString('Gentle care for the whole family', $html);
        $this->assertStringContainsString('<title>Brightleaf Dental</title>', $html);
        $this->assertStringNotContainsString('<form', $html, 'no contact form is rendered at the foot of a page');
        $this->assertStringNotContainsString('name="robots"', $html, 'a published site must be indexable');
        $this->assertStringNotContainsString('not your website', $html);

        // Static and self-contained: no scripts except the data-only structured-data block, no inline handlers.
        $dom = $this->dom($html);
        $this->assertSame(1, $dom->query('//script')->length, 'only the JSON-LD block');
        $this->assertSame('application/ld+json', $dom->query('//script')->item(0)->getAttribute('type'));
        $this->assertSame(0, $dom->query('//*[@*[starts-with(name(), "on")]]')->length, 'no inline event handlers');
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('/build/assets/', $html, 'fonts are relinked to the package');
        $this->assertStringContainsString('assets/fonts/', $html);

        // Security headers on every served file.
        $page->assertHeader('X-Content-Type-Options', 'nosniff');
        $csp = $page->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringNotContainsString('script-src', $csp, 'scripts fall back to default-src none');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("base-uri 'none'", $csp);

        // Self-hosted fonts are served with a font type.
        preg_match('#assets/fonts/([A-Za-z0-9._-]+\.woff2)#', $html, $m);
        $font = $this->get('/sites/calder-voss-test/assets/fonts/'.$m[1]);
        $font->assertOk()->assertHeader('Content-Type', 'font/woff2')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_the_analysis_behind_a_redesign_is_never_publicly_served(): void
    {
        [$user, $run] = $this->siteFor('starter');
        $this->publish($user, $run, 'private-files-test');

        foreach (['redo/website-dna.json', 'redo/diagnosis.json', 'design/design-spec.json', 'README.md', '../private-files-test/redo/website-dna.json'] as $path) {
            $this->get("/sites/private-files-test/{$path}")->assertNotFound();
        }
        $this->assertDirectoryDoesNotExist(storage_path('app/private/sites/private-files-test/redo'));
        $this->assertDirectoryDoesNotExist(storage_path('app/private/sites/private-files-test/design'));
        $this->get('/sites/private-files-test/index.html')->assertOk();
    }

    public function test_hostile_source_content_is_inert_on_the_published_site(): void
    {
        [$user, $run] = $this->siteFor('starter', [
            'business_name' => 'Evil </title><script>alert(1)</script> Dental',
            'hero' => ['headline' => 'Hello <img src=x onerror=alert(1)> world', 'primary_cta_label' => 'Click <b>me</b>'],
            'footer' => ['contact_text' => 'javascript:alert(1) hello@brightleaf.example'],
        ]);
        $this->publish($user, $run, 'hostile-test');

        $html = $this->get('/sites/hostile-test/index.html')->assertOk()->getContent();
        $dom = $this->dom($html);

        // Structural checks: hostile strings may appear only as escaped TEXT, never as markup.
        $this->assertSame(1, $dom->query('//script')->length, 'only the JSON-LD block is a script');
        $this->assertSame('application/ld+json', $dom->query('//script')->item(0)->getAttribute('type'));
        $this->assertSame(0, $dom->query('//*[@*[starts-with(name(), "on")]]')->length, 'no inline event handlers');
        $this->assertSame(0, $dom->query('//img[@src="x"]')->length);
        $this->assertSame(0, $dom->query('//b')->length);
        $this->assertSame(0, $dom->query('//a[starts-with(translate(@href, "JAVSCRIPT", "javscript"), "javascript:")]')->length);
        $this->assertSame(0, $dom->query('//form[starts-with(@action, "javascript")]')->length);
        $this->assertSame(1, $dom->query('//title')->length, 'a hostile name cannot close or add a <title>');
        $this->assertStringContainsString('Dental', $dom->query('//title')->item(0)->textContent);
        // The structured data stays valid JSON that cannot break out of its script element.
        $json = json_decode($dom->query('//script')->item(0)->textContent, true);
        $this->assertSame('Organization', $json['@type']);
    }

    public function test_starter_downloads_a_complete_self_contained_html_package(): void
    {
        [$user, $run] = $this->siteFor('starter');

        $response = $this->actingAs($user)->post("/analyses/{$run->public_id}/exports");
        $response->assertRedirect();
        $this->assertDatabaseCount('exports', 1);

        preg_match('#/exports/([^/]+)/ready#', $response->headers->get('Location'), $m);
        $token = $m[1];
        $download = $this->get("/exports/{$token}/download");
        $download->assertOk();

        $zipPath = storage_path('framework/testing/export-'.uniqid().'.zip');
        File::ensureDirectoryExists(dirname($zipPath));
        File::put($zipPath, $download->baseResponse->getFile()->getContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $index = $zip->getFromName('index.html');
        $zip->close();
        File::delete($zipPath);

        $this->assertContains('index.html', $names);
        $this->assertContains('README.md', $names);
        $this->assertContains('design/design-spec.json', $names, 'the customer keeps the full spec');
        $this->assertContains('redo/website-dna.json', $names);
        $fonts = array_filter($names, fn ($n) => str_starts_with($n, 'assets/fonts/') && str_ends_with($n, '.woff2'));
        $this->assertNotEmpty($fonts, 'approved fonts travel with the page');
        $this->assertNotContains('assets/app.css', $names, 'blueprint sites need no external stylesheet');
        $this->assertStringContainsString('<style>', $index);
        $this->assertStringContainsString('Gentle care for the whole family', $index);
        foreach ($fonts as $font) {
            $this->assertStringContainsString($font, $index, "{$font} is referenced by the page");
        }
    }

    public function test_an_export_pass_allows_html_download_but_not_publishing(): void
    {
        [$free, $run] = $this->siteFor('free');
        OneTimePurchase::create(['user_id' => $free->id, 'analysis_run_id' => $run->id, 'purchase_type' => 'export_pass', 'status' => 'paid', 'exports_remaining' => 1]);

        $this->actingAs($free)->post("/analyses/{$run->public_id}/exports")->assertRedirect()->assertSessionMissing('errors');
        $this->assertDatabaseCount('exports', 1);
        $this->assertSame(0, OneTimePurchase::first()->exports_remaining, 'the credit is consumed');

        $this->actingAs($free)->post("/analyses/{$run->public_id}/exports")->assertRedirect(route('analyses.upgrade', $run->public_id));
        $this->actingAs($free)->post("/analyses/{$run->public_id}/publish", ['slug' => 'pass-try'])->assertRedirect(route('pricing'));
        $this->assertDatabaseCount('deployments', 0);
    }

    public function test_a_refined_version_is_what_gets_published_and_rollback_restores_the_earlier_one(): void
    {
        [$user, $run] = $this->siteFor('agency');
        $v1 = $run->currentDesignSpec();
        $this->publish($user, $run, 'rollback-test');
        $first = $run->fresh()->activeDeployment;

        // A QA refinement creates v2 (drops a section); publishing again deploys it.
        $refined = app(BlueprintRefiner::class)->refine($v1, [['check' => 'No empty or missing sections', 'status' => 'failed', 'meta' => ['instance_ids' => ['testimonials-cards']]]]);
        $this->assertNotNull($refined);
        $this->publish($user, $run->fresh(), 'rollback-test');
        $this->assertStringNotContainsString('data-section="testimonials-cards"', $this->get('/sites/rollback-test/index.html')->getContent());

        $this->actingAs($user)->post("/analyses/{$run->public_id}/deployments/{$first->id}/rollback")->assertRedirect();
        $this->assertStringContainsString('data-section="testimonials-cards"', $this->get('/sites/rollback-test/index.html')->getContent());
        $this->assertDirectoryDoesNotExist(storage_path('app/private/sites/rollback-test/redo'));
    }

    private function dom(string $html): \DOMXPath
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument;
        $doc->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return new \DOMXPath($doc);
    }
}
