<?php

namespace Tests\Feature;

use App\Models\DesignSelection;
use App\Models\User;
use App\Services\Blueprint\BlueprintCatalog;
use App\Services\Blueprint\DesignSpecV15;
use App\Services\Blueprint\HeroLayoutChoices;
use App\Services\Blueprint\SectionRegistry;
use App\Services\Catalogue\DesignCatalogue;
use App\Services\Rendering\PreviewRenderer;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\DesignDirectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;

class HeroLayoutChoiceTest extends TestCase
{
    use BuildsBlueprintSpecs;
    use RefreshDatabase;

    public function test_every_choice_maps_to_a_registered_variant_with_sides(): void
    {
        $reg = app(SectionRegistry::class);
        foreach (array_keys(HeroLayoutChoices::CHOICES) as $c) {
            foreach (['left', 'right'] as $side) {
                $v = HeroLayoutChoices::variant($c, $side);
                $this->assertTrue($reg->has('hero', $v), "{$c}/{$side} → {$v}");
                $this->assertArrayHasKey($v, config('hero_layout.variants'), 'fit rules exist for it');
            }
        }
        $this->assertSame('banner-right', HeroLayoutChoices::variant('banner', 'right'));
        $this->assertSame('image-left', HeroLayoutChoices::variant('image-left', 'right'));
        $this->assertNull(HeroLayoutChoices::variant(null, 'left'));
    }

    public function test_the_chosen_hero_layout_is_built_into_the_page_and_each_one_renders_safely(): void
    {
        $this->seed(DesignDirectionSeeder::class);
        $this->seed(CatalogueSeeder::class);
        foreach ([['image-left', null, 'bp-hero__grid--rev'], ['image-right', null, 'bp-hero__grid"'], ['banner', 'right', 'bp-hero--banner-right'], ['banner', 'left', 'bp-hero--banner-left'], ['fullscreen', 'left', 'bp-hero--full-left'], ['fullscreen', 'right', 'bp-hero--full-right'], ['bold', 'left', 'bp-hero--bold-left'], ['bold', 'right', 'bp-hero--bold-right']] as [$choice, $side, $marker]) {
            $run = $this->makeRun(null, ['hero1' => 'hero_image']);
            $spec = $this->specFor($run, $this->legacySpec());
            $sel = DesignSelection::create(['analysis_run_id' => $run->id, 'direction_key' => 'trusted-expertise', 'direction_version' => 1, 'preferences_json' => ['hero_layout' => $choice, 'hero_side' => $side], 'selected_at' => now(), 'status' => 'generating', 'blueprint_id' => 'local-services-01', 'blueprint_version' => app(BlueprintCatalog::class)->find('local-services-01')['version'], 'catalogue_version' => app(DesignCatalogue::class)->version()]);
            $r = app(DesignSpecV15::class)->attach($spec, $sel, 'local-services-01');
            $this->assertTrue($r['attached'], $choice.': '.implode('; ', $r['errors']));
            $this->assertSame(HeroLayoutChoices::variant($choice, $side), collect($spec->fresh()->design_spec_json['v15']['pages'][0]['sections'])->firstWhere('component_id', 'hero')['variant_id']);
            $html = app(PreviewRenderer::class)->renderBlueprintDocument($spec->fresh()->design_spec_json, $run);
            $this->assertStringContainsString($marker, $html, $choice);
            $this->assertSame(1, substr_count($html, '<h1'), $choice.' has exactly one h1');
            $this->assertStringContainsString('Gentle care for the whole family', $html);
        }
    }

    public function test_the_layout_page_offers_the_hero_layouts_and_the_choice_is_saved(): void
    {
        Queue::fake();
        $this->seed(DesignDirectionSeeder::class);
        $this->seed(CatalogueSeeder::class);
        $user = User::factory()->create();
        $run = $this->makeRun(null, []);
        $run->update(['user_id' => $user->id]);

        $page = $this->actingAs($user)->get(route('analyses.design-directions.layout', [$run->public_id, 'direction_key' => 'trusted-expertise']))->assertOk();
        $page->assertSee('Choose your hero layout')->assertSee('Picture left · content right')->assertSee('Content left · picture right')->assertSee('Full-width banner')->assertSee('Full-screen hero')->assertSee('Big type');

        $this->actingAs($user)->post(route('analyses.design-directions.select', $run->public_id), ['direction_key' => 'trusted-expertise', 'blueprint_id' => 'local-services-01', 'hero_layout' => 'fullscreen', 'hero_side' => 'right']);
        $sel = $run->designSelections()->first();
        $this->assertSame('fullscreen', $sel->preferences_json['hero_layout']);
        $this->assertSame('right', $sel->preferences_json['hero_side']);
        $this->actingAs($user)->post(route('analyses.design-directions.select', $run->public_id), ['direction_key' => 'trusted-expertise', 'hero_layout' => 'nonsense'])->assertSessionHasErrors('hero_layout');
    }
}
