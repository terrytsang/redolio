<?php

namespace Tests\Feature;

use App\Models\DesignDirection;
use App\Services\Blueprint\BlueprintCatalog;
use App\Services\Blueprint\BlueprintRenderer;
use App\Services\Catalogue\DesignCatalogue;
use App\Services\Recommendation\BlueprintRecommender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A Design Direction an admin imported or captured (a database row) can use page layouts, so the designs made with it are editable. */
class ImportedDirectionLayoutsTest extends TestCase
{
    use RefreshDatabase;

    private function importDirection(string $key = 'kobbe_style', string $template = 'editorial'): void
    {
        DesignDirection::create(['direction_key' => $key, 'name' => 'Kobbe style', 'version' => 1, 'is_selectable' => true, 'created_at' => now(), 'recipe_json' => [
            'template_key' => $template, 'description' => 'Warm editorial analytics.', 'spacing_system' => 'spacious',
            'color_system' => ['background' => '#FAF9F8', 'surface' => '#F5F3F0', 'text' => '#2B2B2B', 'text_muted' => '#75736E', 'accent' => '#2B2B2B', 'accent_contrast' => '#FAF9F8'],
            'typography_system' => ['display' => 'font-sans', 'body' => 'font-sans'],
        ]]);
    }

    public function test_an_imported_direction_borrows_the_layouts_of_the_built_in_direction_with_the_same_template_and_keeps_its_own_look(): void
    {
        $this->importDirection();
        $cat = app(DesignCatalogue::class);
        $d = $cat->direction('kobbe_style');

        $this->assertNotNull($d);
        $this->assertTrue($d['imported']);
        $this->assertSame('editorial', $d['template_key']);
        $this->assertContains($d['layout_donor'], ['studio-editorial', 'modern-heritage', 'auteur']);
        $this->assertSame('#FAF9F8', $d['token_overrides']['color']['background']);
        $this->assertSame('#2B2B2B', $d['token_overrides']['color']['primary']);
        $this->assertSame('spacious', $d['token_overrides']['density']);
        $this->assertSame($cat->direction($d['layout_donor'])['layout_preferences'], $d['layout_preferences'], 'its page structure comes from the donor');

        $layouts = app(BlueprintCatalog::class)->forDirection('kobbe_style');
        $this->assertNotEmpty($layouts);
        $this->assertSame(count(app(BlueprintCatalog::class)->forDirection($d['layout_donor'])), count($layouts));
    }

    public function test_the_built_in_directions_keep_exactly_the_layouts_they_list_and_unknown_directions_get_none(): void
    {
        $this->importDirection();
        $catalog = app(BlueprintCatalog::class);
        foreach ($catalog->all() as $bp) {
            foreach ($bp['compatible_direction_ids'] as $d) {
                $this->assertTrue($catalog->compatible($bp, $d));
            }
        }
        // modern-heritage must not pick up layouts just because another editorial direction has them.
        $explicit = array_filter($catalog->all(), fn ($b) => in_array('modern-heritage', $b['compatible_direction_ids'], true));
        $this->assertCount(count($explicit), $catalog->forDirection('modern-heritage'));
        $this->assertSame([], $catalog->forDirection('no-such-direction'));
        $this->assertNull(app(DesignCatalogue::class)->direction('no-such-direction'));
    }

    public function test_a_layout_renders_with_the_imported_directions_palette_and_a_direction_without_a_recipe_is_refused(): void
    {
        $this->importDirection();
        $catalog = app(BlueprintCatalog::class);
        $bp = $catalog->forDirection('kobbe_style')[0];

        $html = app(BlueprintRenderer::class)->render($bp, $catalog->sample($bp['id'], 'kobbe_style'), 'kobbe_style', ['sample_banner' => false]);
        $this->assertStringContainsStringIgnoringCase('#FAF9F8', $html);
        $this->assertStringContainsStringIgnoringCase('#2B2B2B', $html);

        DesignDirection::create(['direction_key' => 'empty_one', 'name' => 'Empty', 'version' => 1, 'is_selectable' => true, 'created_at' => now(), 'recipe_json' => ['template_key' => 'editorial']]);
        $this->assertSame([], $catalog->forDirection('empty_one'), 'a direction with no recipe colours cannot be rendered by the layout engine');
        $this->assertNotEmpty(app(BlueprintRecommender::class)->eligible('kobbe_style'));
    }
}
