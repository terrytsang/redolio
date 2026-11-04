<?php

namespace Tests\Feature\Catalogue;

use App\Jobs\GenerateDesignSpecJob;
use App\Models\DesignSelection;
use App\Models\DesignSpec;
use App\Services\Blueprint\BlueprintCatalog;
use App\Services\Blueprint\BlueprintRenderer;
use App\Services\Blueprint\DesignSpecV15;
use App\Services\Blueprint\JsonSchemaSubset;
use App\Services\Blueprint\SectionRegistry;
use App\Services\Orchestration\GenerateDesignSpecAction;
use App\Services\Publishing\ArtifactValidator;
use App\Services\Publishing\ProductionArtifactGenerator;
use App\Services\Rendering\PreviewRenderer;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\DesignDirectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;

class BlueprintGenerationTest extends TestCase
{
    use BuildsBlueprintSpecs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DesignDirectionSeeder::class);
        $this->seed(CatalogueSeeder::class);
    }

    public function test_full_source_content_fills_the_blueprint_and_every_section_is_traceable(): void
    {
        $run = $this->makeRun();
        [$result, $spec] = $this->attach($run, $this->legacySpec());

        $this->assertTrue($result['attached'], implode('; ', $result['errors']));
        $v15 = $spec->design_spec_json['v15'];
        $this->assertSame('1.5', $v15['schema_version']);
        $this->assertSame('complete', $v15['content_mapping']['status']);
        $this->assertSame(['schema_valid' => true, 'registry_valid' => true, 'content_integrity_valid' => true, 'asset_integrity_valid' => true], $v15['validation']);

        $sections = collect($v15['pages'][0]['sections'])->keyBy('instance_id');
        $this->assertSame(['navigation-simple', 'hero-service-split', 'services-image-grid', 'testimonials-cards', 'contact-form', 'footer-contact'], $sections->keys()->all());
        $this->assertSame('Gentle care for the whole family', $sections['hero-service-split']['props']['headline']);
        $this->assertSame('#contact', $sections['hero-service-split']['props']['primary_cta']['href']);
        $this->assertSame('mailto:hello@brightleaf.example', $sections['contact-form']['props']['form_action']);
        $this->assertSame('spec.sections.offerings.items', $sections['services-image-grid']['content_bindings']['items']);
        $this->assertSame(['ev1', 'ev2'], $v15['provenance']['content_evidence_ids']);
        $this->assertSame('mailto', $v15['forms'][0]['submission_adapter']);
        $this->assertSame('preserve-logo', $v15['brand']['brand_continuity_policy']);
        $design = $v15['design'];
        $this->assertSame(['category_id' => 'services', 'direction_id' => 'trusted-expertise', 'direction_version' => 1, 'blueprint_id' => 'local-services-01', 'blueprint_version' => 1, 'token_profile_id' => 'trusted-expertise', 'catalogue_version' => '1.5.0'],
            array_diff_key($design, ['token_overrides' => 1]));
        $this->assertSame('lora', $design['token_overrides']['typography']['display'], 'the direction personality is recorded in the spec');
    }

    public function test_nothing_is_invented_for_content_the_site_does_not_have(): void
    {
        $dna = $this->dna(['trust_signals' => []]); // no testimonials
        $run = $this->makeRun($dna);
        [, $spec] = $this->attach($run, $this->legacySpec());
        $v15 = $spec->design_spec_json['v15'];

        $ids = array_column($v15['pages'][0]['sections'], 'instance_id');
        $this->assertNotContains('testimonials-cards', $ids, 'unverified testimonials must not render');
        $this->assertNotContains('trust-statistics', $ids);
        $this->assertNotContains('project-gallery', $ids);
        $reasons = collect($v15['content_mapping']['omitted_sections'])->keyBy('instance_id');
        $this->assertStringContainsString('none are invented', $reasons['testimonials-cards']['reason']);
        $this->assertStringContainsString('made up', $reasons['trust-statistics']['reason']);
        $this->assertFalse($reasons['project-gallery']['required']);
    }

    public function test_missing_contact_email_omits_the_required_form_and_marks_the_spec_partial(): void
    {
        $run = $this->makeRun();
        $spec = $this->legacySpec();
        $spec['footer']['contact_text'] = 'Visit us on Main Street';
        [$result, $row] = $this->attach($run, $spec);

        $v15 = $row->design_spec_json['v15'];
        $this->assertTrue($result['attached']);
        $this->assertTrue($result['partial']);
        $this->assertSame('partial', $v15['content_mapping']['status']);
        $this->assertFalse($v15['content_mapping']['required_slots_complete']);
        $this->assertNotContains('contact-form', array_column($v15['pages'][0]['sections'], 'instance_id'));
        $this->assertSame([], $v15['forms']);
        $omitted = collect($v15['content_mapping']['omitted_sections'])->firstWhere('instance_id', 'contact-form');
        $this->assertTrue($omitted['required']);
        $this->assertNotSame('#contact', $v15['pages'][0]['sections'][1]['props']['primary_cta']['href']);
        $this->assertStringStartsNotWith('#contact', json_encode($v15['pages'][0]['sections'][0]['props']['links']));
    }

    public function test_assets_are_bound_only_when_they_belong_to_the_project(): void
    {
        $run = $this->makeRun(null, ['hero1' => 'hero_image', 'p1' => 'portfolio_image', 'p2' => 'portfolio_image', 'p3' => 'portfolio_image']);
        $spec = $this->legacySpec(['hero' => ['image_asset_key' => 'hero1']]);
        $spec['sections'][0]['items'][0]['asset_key'] = 'p1';
        $spec['sections'][0]['items'][1]['asset_key'] = 'not-a-real-asset';
        [, $row] = $this->attach($run, $spec);

        $v15 = $row->design_spec_json['v15'];
        $sections = collect($v15['pages'][0]['sections'])->keyBy('instance_id');
        $this->assertSame('hero1', $sections['hero-service-split']['props']['image']['asset_id']);
        $this->assertSame('p1', $sections['services-image-grid']['props']['items'][0]['image']['asset_id']);
        $this->assertArrayNotHasKey('image', $sections['services-image-grid']['props']['items'][1], 'unknown asset keys are dropped');
        $this->assertCount(3, $sections['project-gallery']['props']['items'], 'gallery uses real project photos, not the one already used as the hero');
        $this->assertContains('hero1', $v15['provenance']['asset_source_ids']);
        $this->assertNotContains('not-a-real-asset', $v15['provenance']['asset_source_ids']);
    }

    public function test_source_text_is_conformed_to_slot_limits_without_markup(): void
    {
        $run = $this->makeRun();
        $spec = $this->legacySpec(['hero' => ['headline' => str_repeat('Very long headline ', 20).'<script>x</script>']]);
        [$result, $row] = $this->attach($run, $spec);

        $headline = $row->design_spec_json['v15']['pages'][0]['sections'][1]['props']['headline'];
        $this->assertTrue($result['attached']);
        $this->assertLessThanOrEqual(140, mb_strlen($headline));
        $this->assertStringNotContainsString('<', $headline);
    }

    public function test_validator_rejects_tampered_specs(): void
    {
        $run = $this->makeRun(null, ['a1' => 'hero_image']);
        [, $row] = $this->attach($run, $this->legacySpec());
        $good = $row->design_spec_json['v15'];
        $v = app(DesignSpecV15::class);
        $this->assertSame([], $v->validate($good, $run)['errors']);

        $mutate = function (callable $fn) use ($good, $v, $run) {
            $bad = $good;
            $fn($bad);

            return $v->validate($bad, $run);
        };

        $r = $mutate(function (&$s) {
            $s['pages'][0]['sections'][2]['component_id'] = 'raw-html';
        });
        $this->assertFalse($r['flags']['registry_valid']);

        $r = $mutate(function (&$s) {
            $s['pages'][0]['sections'][1]['props']['primary_cta']['href'] = 'javascript:alert(1)';
        });
        $this->assertFalse($r['flags']['content_integrity_valid']);

        $r = $mutate(function (&$s) {
            $s['pages'][0]['sections'][1]['props']['headline'] = '<img src=x onerror=alert(1)>';
        });
        $this->assertFalse($r['flags']['content_integrity_valid']);

        $r = $mutate(function (&$s) {
            $s['pages'][0]['sections'][1]['props']['image'] = ['asset_id' => 'someone-elses-asset', 'alt' => 'x'];
        });
        $this->assertFalse($r['flags']['asset_integrity_valid']);

        $r = $mutate(function (&$s) {
            $s['pages'][0]['sections'][1]['props']['secondary_cta'] = ['label' => 'Go', 'href' => '#nowhere'];
        });
        $this->assertStringContainsString('does not exist on the page', implode(';', $r['errors']));

        $r = $mutate(function (&$s) {
            $s['design']['direction_id'] = 'maison-market';
        });
        $this->assertStringContainsString('not compatible', implode(';', $r['errors']));

        $r = $mutate(function (&$s) {
            $s['design']['token_overrides'] = ['color' => ['accent' => 'red;}body{display:none']];
        });
        $this->assertFalse($r['flags']['registry_valid']);

        $r = $mutate(function (&$s) {
            $s['injected'] = '<script>';
        });
        $this->assertFalse($r['flags']['schema_valid']);

        $r = $mutate(function (&$s) {
            $s['forms'] = [];
        });
        $this->assertStringContainsString('no form adapter', implode(';', $r['errors']));

        $r = $mutate(function (&$s) {
            $s['pages'][0]['sections'][0]['props']['unexpected'] = 'x';
        });
        $this->assertStringContainsString('unknown property', implode(';', $r['errors']));
    }

    public function test_renderer_compiles_exactly_the_spec_deterministically(): void
    {
        $run = $this->makeRun(null, ['hero1' => 'hero_image']);
        [, $row] = $this->attach($run, $this->legacySpec(['hero' => ['image_asset_key' => 'hero1']]));
        $v15 = $row->design_spec_json['v15'];
        $r = app(BlueprintRenderer::class);

        $html = $r->renderSpec($v15, ['hero1' => '/storage/assets/hero1.jpg']);
        $this->assertSame($html, $r->renderSpec($v15, ['hero1' => '/storage/assets/hero1.jpg']));

        preg_match_all('/data-section="([^"]+)"/', $html, $m);
        $this->assertSame(array_column($v15['pages'][0]['sections'], 'instance_id'), $m[1]);
        $this->assertStringContainsString('<img class="bp-visual__img" src="/storage/assets/hero1.jpg"', $html);
        $this->assertStringContainsString('<title>Brightleaf Dental</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Gentle family dentistry.">', $html);
        $this->assertStringContainsString('"@type":"Organization","name":"Brightleaf Dental"', $html);
        $this->assertStringNotContainsString('name="robots"', $html, 'a published site must be indexable');
        $this->assertStringContainsString('name="robots"', $r->renderSpec($v15, [], ['noindex' => true]));
        $this->assertStringNotContainsString('<form', $html, 'no contact form is rendered at the foot of a page');
        $this->assertStringContainsString('id="contact"', $html);
        $this->assertStringNotContainsString('not your website', $html);
    }

    public function test_structured_data_cannot_break_out_of_its_script_tag(): void
    {
        $run = $this->makeRun();
        $spec = $this->legacySpec(['business_name' => 'Evil </script><b>x']);
        [, $row] = $this->attach($run, $spec);
        $html = app(BlueprintRenderer::class)->renderSpec($row->design_spec_json['v15']);

        $this->assertSame(1, substr_count($html, '</script>'), 'only the JSON-LD tag closes a script');
        $this->assertStringNotContainsString('<b>x', $html);
    }

    public function test_preview_renderer_embeds_blueprint_sites_sandboxed_and_leaves_legacy_specs_alone(): void
    {
        $run = $this->makeRun();
        [, $row] = $this->attach($run, $this->legacySpec());
        $renderer = app(PreviewRenderer::class);

        $iframe = $renderer->render($row->design_spec_json, $run);
        $this->assertStringStartsWith('<div class="bp-frame-host" data-desktop-width="1440"', $iframe, 'rendered as a desktop-width page that is scaled to fit its panel');
        $this->assertStringContainsString('<iframe title="Generated website preview" sandbox="allow-same-origin"', $iframe);
        $this->assertStringContainsString('width:1440px', $iframe);
        $this->assertStringNotContainsString('allow-scripts', $iframe, 'generated pages may never run scripts in the app origin');
        $this->assertStringContainsString('srcdoc="', $iframe);

        $legacy = $this->legacySpec();
        $legacy['template_key'] = 'modern_corporate';
        $legacy['footer'] += ['cta_label' => 'Book now'];
        $this->assertStringNotContainsString('<iframe', $renderer->render($legacy, $run));
    }

    public function test_export_of_a_blueprint_site_is_self_contained_and_passes_artifact_validation(): void
    {
        $run = $this->makeRun();
        [, $row] = $this->attach($run, $this->legacySpec());
        $dest = storage_path('framework/testing/export-'.uniqid());

        try {
            app(ProductionArtifactGenerator::class)->generate($run, $row, $dest);
            $html = File::get("{$dest}/index.html");

            $this->assertStringContainsString('<style>', $html);
            $this->assertStringNotContainsString('assets/app.css', $html);
            $this->assertStringNotContainsString('name="robots"', $html);
            $this->assertSame(['valid' => true, 'errors' => []], app(ArtifactValidator::class)->validate($dest, $row));
        } finally {
            File::deleteDirectory($dest);
        }
    }

    public function test_generation_job_attaches_the_blueprint_block_and_survives_a_failed_attach(): void
    {
        $run = $this->makeRun();
        $run->diagnosisReports()->create(['version' => 1, 'summary' => 'x', 'issues_json' => ['issues' => []], 'model_identifier' => 't', 'prompt_version' => 'v1']);
        $spec = $this->specFor($run, $this->legacySpec());

        $action = Mockery::mock(GenerateDesignSpecAction::class);
        $action->shouldReceive('execute')->andReturn($spec);
        $this->app->instance(GenerateDesignSpecAction::class, $action);

        $selection = $this->selection($run);
        (new GenerateDesignSpecJob($selection->id))->handle($action, app(DesignSpecV15::class));
        $this->assertSame('complete', $selection->fresh()->status);
        $this->assertSame('local-services-01', $spec->fresh()->design_spec_json['v15']['design']['blueprint_id']);

        // A blueprint that cannot be attached must never lose the redesign.
        $bad = $this->selection($run);
        $bad->update(['blueprint_id' => 'does-not-exist']);
        (new GenerateDesignSpecJob($bad->id))->handle($action, app(DesignSpecV15::class));
        $this->assertSame('complete', $bad->fresh()->status);
    }

    public function test_refinement_keeps_the_layout_for_the_same_direction_only(): void
    {
        $run = $this->makeRun();
        $run->diagnosisReports()->create(['version' => 1, 'summary' => 'x', 'issues_json' => ['issues' => []], 'model_identifier' => 't', 'prompt_version' => 'v1']);
        [, $first] = $this->attach($run, $this->legacySpec());
        $next = DesignSpec::create(['analysis_run_id' => $run->id, 'version' => 2, 'template_key' => 'modern_corporate', 'design_spec_json' => $this->legacySpec(), 'status' => 'valid']);

        $action = Mockery::mock(GenerateDesignSpecAction::class);
        $action->shouldReceive('execute')->andReturn($next);

        $same = DesignSelection::create(['analysis_run_id' => $run->id, 'direction_key' => 'trusted-expertise', 'direction_version' => 1, 'selected_at' => now(), 'status' => 'generating', 'refine_from_design_spec_id' => $first->id]);
        (new GenerateDesignSpecJob($same->id))->handle($action, app(DesignSpecV15::class));
        $this->assertSame('local-services-01', $next->fresh()->design_spec_json['v15']['design']['blueprint_id']);

        $next->update(['design_spec_json' => $this->legacySpec()]);
        $other = DesignSelection::create(['analysis_run_id' => $run->id, 'direction_key' => 'executive-clarity', 'direction_version' => 1, 'selected_at' => now(), 'status' => 'generating', 'refine_from_design_spec_id' => $first->id]);
        (new GenerateDesignSpecJob($other->id))->handle($action, app(DesignSpecV15::class));
        $this->assertArrayNotHasKey('v15', $next->fresh()->design_spec_json);
    }

    public function test_the_schema_subset_validator_enforces_the_keywords_the_schema_uses(): void
    {
        $v = new JsonSchemaSubset;
        $schema = ['type' => 'object', 'additionalProperties' => false, 'required' => ['a'], 'properties' => [
            'a' => ['type' => 'string', 'minLength' => 2, 'pattern' => '^[a-z]+$'], 'n' => ['type' => 'integer', 'minimum' => 1],
            'l' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string', 'enum' => ['x']]],
        ]];

        $this->assertSame([], $v->validate($schema, ['a' => 'ok', 'n' => 1, 'l' => ['x']]));
        $this->assertNotEmpty($v->validate($schema, []));                         // missing required
        $this->assertNotEmpty($v->validate($schema, ['a' => 'ok', 'z' => 1]));    // unknown property
        $this->assertNotEmpty($v->validate($schema, ['a' => 'A']));               // pattern + length
        $this->assertNotEmpty($v->validate($schema, ['a' => 'ok', 'n' => 0]));    // minimum
        $this->assertNotEmpty($v->validate($schema, ['a' => 'ok', 'l' => []]));   // minItems
        $this->assertNotEmpty($v->validate($schema, ['a' => 'ok', 'l' => ['y']])); // enum in items
        $this->assertNotEmpty($v->validate(['required' => ['a'], 'type' => 'object'], []), 'empty object must still fail required');
    }

    public function test_the_published_schema_file_is_valid_json_and_versioned(): void
    {
        $schema = json_decode(File::get(database_path('catalogue/schemas/design-spec-1.5.schema.json')), true);
        $this->assertSame(['1.5'], $schema['properties']['schema_version']['enum']);
        $this->assertEqualsCanonicalizing(['schema_version', 'project', 'brand', 'design', 'pages', 'content_mapping', 'responsive', 'seo', 'forms', 'provenance', 'validation'], $schema['required']);
    }

    public function test_anchors_declared_in_the_registry_match_the_rendered_markup(): void
    {
        $registry = app(SectionRegistry::class);
        $catalog = app(BlueprintCatalog::class);
        foreach ($catalog->all() as $id => $bp) {
            $html = app(BlueprintRenderer::class)->render($bp, $catalog->sample($id), $bp['compatible_direction_ids'][0]);
            foreach ($bp['section_sequence'] as $s) {
                $anchor = $registry->anchor($s['component_id'], $s['variant_id']);
                $present = isset($catalog->sample($id)[$s['instance_id']]);
                $anchor && $present && $this->assertStringContainsString("id=\"{$anchor}\"", $html, "{$id}: {$s['instance_id']} should expose #{$anchor}");
            }
        }
    }
}
