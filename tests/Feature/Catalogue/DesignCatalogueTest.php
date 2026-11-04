<?php

namespace Tests\Feature\Catalogue;

use App\Models\DesignCategory;
use App\Models\DesignDirection;
use App\Services\Catalogue\DesignCatalogue;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\DesignDirectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private const CATEGORIES = ['Business', 'SaaS', 'Portfolio', 'Shop', 'Agency', 'Landing', 'Services'];

    private const DIRECTIONS = [
        'executive-clarity' => 'Executive Clarity', 'modern-heritage' => 'Modern Heritage',
        'product-pulse' => 'Product Pulse', 'datacanvas' => 'DataCanvas',
        'auteur' => 'Auteur', 'gridcraft' => 'Gridcraft',
        'maison-market' => 'Maison Market', 'bold-commerce' => 'Bold Commerce',
        'creative-voltage' => 'Creative Voltage', 'studio-editorial' => 'Studio Editorial',
        'launch-signal' => 'Launch Signal', 'trusted-expertise' => 'Trusted Expertise',
    ];

    private function seedCatalogue(): void
    {
        $this->seed(DesignDirectionSeeder::class);
        $this->seed(CatalogueSeeder::class);
    }

    public function test_seven_categories_with_exact_names(): void
    {
        $names = array_column(app(DesignCatalogue::class)->categories(), 'name');

        $this->assertSame(self::CATEGORIES, $names);
    }

    public function test_twelve_directions_with_exact_ids_and_names(): void
    {
        $dirs = app(DesignCatalogue::class)->directions();

        $this->assertSame(self::DIRECTIONS, array_column($dirs, 'name', 'id'));
    }

    public function test_every_category_has_a_primary_direction_and_all_references_resolve(): void
    {
        $c = app(DesignCatalogue::class);
        $categoryIds = array_column($c->categories(), 'id');
        $profileIds = array_column($c->tokenProfiles(), 'id');

        foreach ($categoryIds as $id) {
            $primary = array_filter($c->directions(), fn ($d) => in_array($id, $d['primary_category_ids'], true));
            $this->assertNotEmpty($primary, "Category {$id} has no primary direction");
        }

        foreach ($c->directions() as $d) {
            foreach (array_merge($d['primary_category_ids'], $d['compatible_category_ids']) as $cid) {
                $this->assertContains($cid, $categoryIds, "{$d['id']} references unknown category {$cid}");
            }
            $this->assertEmpty(array_intersect($d['primary_category_ids'], $d['compatible_category_ids']));
            $this->assertContains($d['token_profile_base'], $profileIds, "{$d['id']} has unknown base token profile");
            foreach (['positioning', 'visual_traits', 'recommended_for', 'avoid_when', 'motion_profile'] as $f) {
                $this->assertNotEmpty($d[$f], "{$d['id']} missing {$f}");
            }
        }
    }

    public function test_category_is_discovery_default_not_a_constraint(): void
    {
        $c = app(DesignCatalogue::class);
        $saas = array_column($c->directionsForCategory('saas'), 'id');

        // Primary SaaS directions first, then cross-category compatible ones.
        $this->assertSame(['product-pulse', 'datacanvas'], array_slice($saas, 0, 2));
        $this->assertContains('executive-clarity', $saas);
        $this->assertContains('launch-signal', $saas);
    }

    public function test_six_starter_token_profiles_meet_contrast(): void
    {
        $profiles = app(DesignCatalogue::class)->tokenProfiles();
        $this->assertCount(6, $profiles);

        foreach ($profiles as $p) {
            $c = $p['color'];
            $this->assertGreaterThanOrEqual(4.5, $this->contrast($c['text'], $c['background']), "{$p['id']} text/background");
            $this->assertGreaterThanOrEqual(4.5, $this->contrast($c['text'], $c['surface']), "{$p['id']} text/surface");
            $this->assertGreaterThanOrEqual(4.5, $this->contrast($c['muted'], $c['background']), "{$p['id']} muted/background");
            $this->assertGreaterThanOrEqual(4.5, $this->contrast($c['muted'], $c['surface']), "{$p['id']} muted/surface");
            $this->assertGreaterThanOrEqual(4.5, $this->contrast($c['primary_contrast'], $c['primary']), "{$p['id']} button");
            $this->assertGreaterThanOrEqual(4.5, $this->contrast($c['accent'], $c['background']), "{$p['id']} accent text");
        }
    }

    public function test_seeding_creates_categories_and_directions_using_renderable_templates(): void
    {
        $this->seedCatalogue();

        $this->assertSame(7, DesignCategory::count());
        foreach (self::DIRECTIONS as $key => $name) {
            $d = DesignDirection::where('direction_key', $key)->firstOrFail();
            $this->assertSame($name, $d->name);
            $this->assertTrue($d->hasRenderableTemplate(), "{$key} must resolve to an allowlisted template");
            $this->assertNotEmpty($d->recipe_json['layout_compositions'], "{$key} must inherit a working recipe");
            $this->assertNotEmpty($d->primary_category_ids);
        }
    }

    public function test_v15_directions_are_selectable_by_default_and_superseded_legacy_ones_are_retired_not_deleted(): void
    {
        $this->seedCatalogue();

        $this->assertTrue(DesignDirection::where('direction_key', 'executive-clarity')->value('is_selectable'));
        $legacy = DesignDirection::where('direction_key', 'modern_corporate')->firstOrFail();
        $this->assertFalse($legacy->is_selectable);
        $this->assertTrue($legacy->hasRenderableTemplate(), 'retired recipes must still render for existing runs');
        $this->assertSame('executive-clarity', $legacy->replaced_by_key);
        $this->assertSame(['business'], $legacy->primary_category_ids);
    }

    public function test_flag_off_keeps_new_directions_hidden_and_legacy_selectable(): void
    {
        config(['redo.activate_v15_directions' => false]);
        $this->seedCatalogue();

        $this->assertFalse(DesignDirection::where('direction_key', 'executive-clarity')->value('is_selectable'));
        $this->assertTrue(DesignDirection::where('direction_key', 'modern_corporate')->value('is_selectable'));
    }

    public function test_seeding_is_idempotent(): void
    {
        $this->seedCatalogue();
        $count = DesignDirection::count();
        $this->seed(CatalogueSeeder::class);

        $this->assertSame($count, DesignDirection::count());
        $this->assertSame(7, DesignCategory::count());
    }

    public function test_api_lists_categories_and_directions(): void
    {
        $this->getJson('/api/design-categories')->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonPath('data.1.id', 'saas')
            ->assertJsonPath('data.1.primary_direction_ids', ['product-pulse', 'datacanvas']);

        $this->getJson('/api/design-directions')->assertOk()->assertJsonCount(12, 'data');

        $this->getJson('/api/design-directions?category=landing')->assertOk()
            ->assertJsonPath('data.0.id', 'launch-signal');

        $this->getJson('/api/design-directions/datacanvas')->assertOk()
            ->assertJsonPath('data.name', 'DataCanvas')
            ->assertJsonPath('data.token_profile_base', 'analytical-light')
            ->assertJsonPath('data.token_profile.id', 'analytical-light')
            ->assertJsonMissingPath('data.template_key');

        $this->getJson('/api/design-directions/nope')->assertNotFound();
    }

    private function contrast(string $a, string $b): float
    {
        $l = function (string $hex): float {
            $hex = ltrim($hex, '#');
            $rgb = array_map(fn ($i) => hexdec(substr($hex, $i, 2)) / 255, [0, 2, 4]);
            [$r, $g, $bl] = array_map(fn ($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $rgb);

            return 0.2126 * $r + 0.7152 * $g + 0.0722 * $bl;
        };
        [$hi, $lo] = [max($l($a), $l($b)), min($l($a), $l($b))];

        return ($hi + 0.05) / ($lo + 0.05);
    }
}
