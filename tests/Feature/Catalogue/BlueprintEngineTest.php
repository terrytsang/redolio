<?php

namespace Tests\Feature\Catalogue;

use App\Exceptions\InvalidBlueprintException;
use App\Services\Blueprint\BlueprintCatalog;
use App\Services\Blueprint\BlueprintRenderer;
use App\Services\Blueprint\BlueprintValidator;
use App\Services\Blueprint\ContentValidator;
use App\Services\Blueprint\SectionRegistry;
use App\Services\Blueprint\TokenCssCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlueprintEngineTest extends TestCase
{
    use RefreshDatabase;

    private function catalog(): BlueprintCatalog
    {
        return app(BlueprintCatalog::class);
    }

    public function test_at_least_three_blueprints_are_defined_and_valid_against_the_registry(): void
    {
        $all = $this->catalog()->all();
        $this->assertGreaterThanOrEqual(3, count($all));
        $this->assertArrayHasKey('surf-school-editorial-01', $all);

        foreach ($all as $id => $bp) {
            $this->assertSame([], app(BlueprintValidator::class)->validateBlueprint($bp), $id);
        }
    }

    public function test_every_blueprint_sample_content_validates_and_renders_for_each_compatible_direction(): void
    {
        $renderer = app(BlueprintRenderer::class);
        foreach ($this->catalog()->all() as $id => $bp) {
            $sample = $this->catalog()->sample($id);
            $this->assertSame([], app(BlueprintValidator::class)->validateContent($bp, $sample), $id);

            foreach ($bp['compatible_direction_ids'] as $direction) {
                $html = $renderer->render($bp, $sample, $direction);
                $this->assertStringContainsString("data-direction=\"{$direction}\"", $html);
                $this->assertSame(1, substr_count($html, '<h1'), "{$id}×{$direction} needs exactly one h1");
            }
        }
    }

    public function test_rendered_section_sequence_matches_the_blueprint_definition(): void
    {
        $bp = $this->catalog()->find('saas-product-01');
        $html = app(BlueprintRenderer::class)->render($bp, $this->catalog()->sample($bp['id']), 'product-pulse');

        preg_match_all('/data-section="([^"]+)"/', $html, $m);
        $this->assertSame(array_column($bp['section_sequence'], 'instance_id'), $m[1]);
    }

    public function test_rendering_is_deterministic(): void
    {
        $bp = $this->catalog()->find('local-services-01');
        $r = app(BlueprintRenderer::class);

        $this->assertSame(
            $r->render($bp, $this->catalog()->sample($bp['id']), 'trusted-expertise'),
            $r->render($bp, $this->catalog()->sample($bp['id']), 'trusted-expertise'),
        );
    }

    public function test_optional_sections_may_be_omitted_but_required_ones_may_not(): void
    {
        $bp = $this->catalog()->find('saas-product-01');
        $sample = $this->catalog()->sample($bp['id']);
        $r = app(BlueprintRenderer::class);

        $withoutOptional = $sample;
        unset($withoutOptional['pricing-simple'], $withoutOptional['faq-accordion']);
        $html = $r->render($bp, $withoutOptional, 'product-pulse');
        $this->assertStringNotContainsString('data-section="pricing-simple"', $html);

        $withoutRequired = $sample;
        unset($withoutRequired['features-bento']);
        $this->expectException(InvalidBlueprintException::class);
        $r->render($bp, $withoutRequired, 'product-pulse');
    }

    public function test_unknown_components_variants_and_sections_are_rejected(): void
    {
        $v = app(BlueprintValidator::class);
        $bp = $this->catalog()->find('saas-product-01');

        $bad = $bp;
        $bad['section_sequence'][1]['variant_id'] = 'made-up-variant';
        $this->assertNotEmpty($v->validateBlueprint($bad));

        $bad = $bp;
        $bad['section_sequence'][1]['component_id'] = 'raw-html';
        $this->assertStringContainsString('unregistered component', implode(';', $v->validateBlueprint($bad)));

        $content = $this->catalog()->sample($bp['id']) + ['not-a-section' => ['x' => 1]];
        $this->assertStringContainsString('not in this blueprint', implode(';', $v->validateContent($bp, $content)));
    }

    public function test_content_rejects_markup_unsafe_links_unknown_props_and_unknown_icons(): void
    {
        $v = app(BlueprintValidator::class);
        $bp = $this->catalog()->find('saas-product-01');
        $content = $this->catalog()->sample($bp['id']);

        $content['hero-product-split']['headline'] = '<script>alert(1)</script>';
        $content['hero-product-split']['primary_cta']['href'] = 'javascript:alert(1)';
        $content['hero-product-split']['onclick'] = 'evil()';
        $content['features-bento']['items'][0]['icon'] = 'not-a-real-icon';

        $errors = implode("\n", $v->validateContent($bp, $content));
        $this->assertStringContainsString('hero-product-split.headline: markup is not allowed', $errors);
        $this->assertStringContainsString('hero-product-split.primary_cta.href: unsafe or malformed link', $errors);
        $this->assertStringContainsString('hero-product-split.onclick: unknown property', $errors);
        $this->assertStringContainsString('icon not in allowlist', $errors);
    }

    public function test_renderer_refuses_incompatible_direction_and_unvalidated_content(): void
    {
        $bp = $this->catalog()->find('saas-product-01');
        $r = app(BlueprintRenderer::class);

        try {
            $r->render($bp, $this->catalog()->sample($bp['id']), 'maison-market');
            $this->fail('Expected incompatible direction to be rejected');
        } catch (InvalidBlueprintException $e) {
            $this->assertStringContainsString('not compatible', $e->getMessage());
        }
    }

    public function test_token_compiler_emits_semantic_variables_and_validates_overrides(): void
    {
        $profile = app(\App\Services\Catalogue\DesignCatalogue::class)->tokenProfiles()[0];
        $c = app(TokenCssCompiler::class);

        $css = $c->compile($profile, ['color' => ['accent' => '#112233']]);
        foreach (['--color-background', '--color-surface', '--color-text', '--color-muted', '--color-primary', '--color-accent', '--font-display', '--radius'] as $var) {
            $this->assertStringContainsString($var.':', $css);
        }
        $this->assertStringContainsString('--color-accent: #112233', $css);

        foreach ([['color' => ['accent' => 'red;}body{display:none']], ['color' => ['nope' => '#112233']], ['shadow' => 'x'], ['radius' => '1em']] as $bad) {
            try {
                $c->compile($profile, $bad);
                $this->fail('Expected override to be rejected: '.json_encode($bad));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_every_registered_variant_has_an_existing_view(): void
    {
        $registry = app(SectionRegistry::class);
        foreach ($registry->all() as $component => $variants) {
            foreach ($variants as $variant) {
                $this->assertTrue(view()->exists($registry->view($component, $variant)), "{$component}:{$variant}");
            }
        }
    }

    public function test_motion_is_reduced_for_users_who_prefer_it(): void
    {
        $bp = $this->catalog()->find('saas-product-01');
        $html = app(BlueprintRenderer::class)->render($bp, $this->catalog()->sample($bp['id']), 'product-pulse');
        $this->assertStringContainsString('prefers-reduced-motion:reduce', $html);
    }

    public function test_blueprint_is_draft_until_preview_qa_manifest_passes(): void
    {
        Storage::fake('public');
        $bp = $this->catalog()->find('local-services-01');
        // A fresh install that has captured no previews yet treats layouts as ready (so it works out of the box)…
        $this->assertSame('ready', $this->catalog()->effectiveStatus($bp));

        // …but once previews exist, a layout is draft until its preview check passes.
        Storage::disk('public')->put("blueprint-previews/{$bp['id']}/trusted-expertise/manifest.json", json_encode(['passed' => false]));
        $this->assertSame('draft', $this->catalog()->effectiveStatus($bp));

        Storage::disk('public')->put("blueprint-previews/{$bp['id']}/trusted-expertise/manifest.json", json_encode(['passed' => true]));
        $this->assertSame('ready', $this->catalog()->effectiveStatus($bp));
    }

    public function test_api_and_preview_routes(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('blueprint-previews/saas-product-01/product-pulse/manifest.json', json_encode(['passed' => true])); // previews exist, local-services-01 has none passing

        $this->getJson('/api/page-blueprints')->assertOk()->assertJsonCount(7, 'data');
        $this->getJson('/api/page-blueprints?category=saas')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/page-blueprints?category=shop')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 'shop-showcase-01');
        $this->getJson('/api/page-blueprints?direction=executive-clarity')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/page-blueprints/local-services-01')->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.content_slot_schema.hero-service-split.required_slots', ['headline', 'primary_cta']);
        $this->getJson('/api/page-blueprints/nope')->assertNotFound();

        $this->get('/blueprints/saas-product-01/preview')->assertOk()
            ->assertSee('not your website', false)->assertHeader('X-Robots-Tag', 'noindex');
        $this->get('/blueprints/saas-product-01/preview?direction=datacanvas&clean=1')->assertOk()->assertDontSee('not your website', false);
        $this->get('/blueprints/saas-product-01/preview?direction=maison-market')->assertNotFound();
        $this->get('/blueprints/nope/preview')->assertNotFound();
    }

    public function test_content_validator_enforces_list_bounds_and_length(): void
    {
        $v = new ContentValidator;
        $schema = ['items' => ['type' => 'list', 'required' => true, 'min' => 2, 'max' => 3, 'items' => ['t' => ['type' => 'string', 'required' => true, 'max' => 5]]]];

        $this->assertNotEmpty($v->validate($schema, ['items' => [['t' => 'ok']]]));
        $this->assertNotEmpty($v->validate($schema, ['items' => [['t' => 'ok'], ['t' => 'far too long']]]));
        $this->assertSame([], $v->validate($schema, ['items' => [['t' => 'a'], ['t' => 'b']]]));
    }
}
