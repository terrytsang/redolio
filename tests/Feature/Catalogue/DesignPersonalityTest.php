<?php

namespace Tests\Feature\Catalogue;

use App\Models\DesignDirection;
use App\Services\Blueprint\BlueprintCatalog;
use App\Services\Blueprint\BlueprintFonts;
use App\Services\Blueprint\BlueprintRenderer;
use App\Services\Blueprint\DirectionShowcase;
use App\Services\Blueprint\LayoutResolver;
use App\Services\Blueprint\SectionRegistry;
use App\Services\Blueprint\TokenCssCompiler;
use App\Services\Catalogue\DesignCatalogue;
use App\Support\GenerativeArt;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DesignPersonalityTest extends TestCase
{
    private function directions(): array
    {
        return app(DesignCatalogue::class)->directions();
    }

    private function resolved(array $d): array
    {
        $cat = app(DesignCatalogue::class);
        $profile = collect($cat->tokenProfiles())->firstWhere('id', $d['token_profile_base']);

        return app(TokenCssCompiler::class)->resolve($profile, $d['token_overrides']);
    }

    public function test_every_direction_has_a_distinct_visual_identity(): void
    {
        $dirs = $this->directions();
        $this->assertCount(12, $dirs);

        $keyed = [
            'art style' => fn ($d) => $d['art_style'],
            'font pairing' => fn ($d) => $this->resolved($d)['typography']['display'].'+'.$this->resolved($d)['typography']['body'],
            'primary colour' => fn ($d) => strtolower($this->resolved($d)['color']['primary']),
            'palette' => fn ($d) => json_encode($this->resolved($d)['color']),
        ];
        foreach ($keyed as $what => $fn) {
            $values = array_map($fn, $dirs);
            $this->assertSame(count($values), count(array_unique($values)), "{$what} must be unique per direction");
        }

        // Directions that share a showcase blueprint must differ in layout choices, not just colour.
        $byBlueprint = collect($dirs)->groupBy('showcase_blueprint_id');
        foreach ($byBlueprint as $bp => $group) {
            $layouts = $group->map(fn ($d) => json_encode($d['layout_preferences']))->all();
            $this->assertSame(count($layouts), count(array_unique($layouts)), "directions on {$bp} must not share a layout signature");
        }
    }

    public function test_every_directions_resolved_palette_meets_wcag_contrast(): void
    {
        foreach ($this->directions() as $d) {
            $c = $this->resolved($d)['color'];
            foreach ([['text', 'background'], ['text', 'surface'], ['muted', 'background'], ['muted', 'surface'], ['primary_contrast', 'primary'], ['accent', 'background']] as [$fg, $bg]) {
                $this->assertGreaterThanOrEqual(4.5, $this->contrast($c[$fg], $c[$bg]), "{$d['id']}: {$fg} on {$bg} ({$c[$fg]} / {$c[$bg]})");
            }
        }
    }

    public function test_each_direction_resolves_to_approved_fonts_that_are_bundled(): void
    {
        $fonts = app(BlueprintFonts::class);
        foreach ($this->directions() as $d) {
            $t = $this->resolved($d)['typography'];
            foreach ($t as $family) {
                $this->assertTrue($fonts->isApproved($family), "{$d['id']} uses unapproved font {$family}");
                $this->assertNotSame('', $fonts->css([$family]), "{$family} must be in the font build (npm run build)");
            }
        }
        $this->assertStringContainsString('format("woff2")', $fonts->css(['lora']));
        $this->assertStringNotContainsString('format("woff")', str_replace('format("woff2")', '', $fonts->css(['lora'])));
        $this->assertSame([], $fonts->files(['not-a-font']));
    }

    public function test_every_directions_showcase_is_valid_compatible_and_renders_with_its_own_personality(): void
    {
        $catalog = app(BlueprintCatalog::class);
        $renderer = app(BlueprintRenderer::class);
        $registry = app(SectionRegistry::class);
        $docs = [];

        foreach ($this->directions() as $d) {
            $bp = $catalog->find($d['showcase_blueprint_id']);
            $this->assertNotNull($bp, "{$d['id']} showcase blueprint");
            $this->assertContains($d['id'], $bp['compatible_direction_ids']);
            foreach ($d['layout_preferences'] as $component => $variant) {
                $this->assertTrue($registry->has($component, $variant), "{$d['id']} prefers unregistered {$component}:{$variant}");
            }
            $this->assertFileExists(database_path("catalogue/samples/{$bp['id']}.{$d['id']}.json"), "{$d['id']} needs its own showcase content");

            $html = $renderer->render($bp, $catalog->sample($bp['id'], $d['id']), $d['id'], ['sample_banner' => false]);
            $this->assertStringContainsString('data-direction="'.$d['id'].'"', $html);
            $this->assertStringContainsString('@font-face', $html);
            $this->assertStringContainsString($this->resolved($d)['color']['primary'], $html);
            $docs[$d['id']] = $html;
        }
        $this->assertCount(12, array_unique($docs), 'no two direction showcases render the same markup');
    }

    public function test_directions_choose_layout_variants_within_the_blueprints_limits(): void
    {
        $catalog = app(BlueprintCatalog::class);
        $resolver = app(LayoutResolver::class);
        $dirs = collect($this->directions())->keyBy('id');

        $studio = $catalog->find('creative-studio-01');
        $auteur = $resolver->resolve($studio, $dirs['auteur']);
        $gridcraft = $resolver->resolve($studio, $dirs['gridcraft']);
        $variant = fn ($bp, $instance) => collect($bp['section_sequence'])->firstWhere('instance_id', $instance)['variant_id'];
        $this->assertSame('typography-led', $variant($auteur, 'hero-studio'));
        $this->assertSame('split', $variant($gridcraft, 'hero-studio'));
        $this->assertSame('edge-to-edge-grid', $variant($auteur, 'gallery-selected-work'));

        // A locked section keeps the blueprint's variant whatever the direction prefers.
        $surf = $catalog->find('surf-school-editorial-01');
        $resolved = $resolver->resolve($surf, ['layout_preferences' => ['hero' => 'typography-led', 'navigation' => 'floating']]);
        $this->assertSame('aerial-fullscreen', $variant($resolved, 'hero-aerial-fullscreen'));
        $this->assertSame('minimal', $variant($resolved, 'navigation-minimal'));

        // Unregistered preferences are ignored, never rendered.
        $ignored = $resolver->resolve($studio, ['layout_preferences' => ['hero' => 'made-up']]);
        $this->assertSame('editorial-asymmetric', $variant($ignored, 'hero-studio'));
    }

    public function test_every_blueprint_is_compatible_with_directions_that_can_actually_render_it(): void
    {
        $catalog = app(BlueprintCatalog::class);
        $renderer = app(BlueprintRenderer::class);
        foreach ($catalog->all() as $id => $bp) {
            foreach ($bp['compatible_direction_ids'] as $dir) {
                $html = $renderer->render($bp, $catalog->sample($id, $dir), $dir);
                $this->assertStringContainsString('data-blueprint="'.$id.'"', $html, "{$id} × {$dir}");
            }
        }
        $covered = collect($catalog->all())->flatMap(fn ($b) => $b['compatible_direction_ids'])->unique()->all();
        foreach ($this->directions() as $d) {
            $this->assertContains($d['id'], $covered, "{$d['id']} needs at least one blueprint");
        }
    }

    public function test_token_compiler_supports_personality_groups_and_rejects_unsafe_values(): void
    {
        $c = app(TokenCssCompiler::class);
        $profile = $this->directions()[0];
        $base = collect(app(DesignCatalogue::class)->tokenProfiles())->firstWhere('id', $profile['token_profile_base']);

        $css = $c->compile($base, ['typography' => ['display' => 'syne', 'body' => 'dm-sans'], 'density' => 'spacious', 'heading_weight' => 800, 'tracking' => '-0.035em', 'button_radius' => '999px', 'eyebrow_case' => 'none']);
        $this->assertStringContainsString('--font-display: "Syne"', $css);
        $this->assertStringContainsString('--heading-weight: 800', $css);
        $this->assertStringContainsString('--heading-tracking: -0.035em', $css);
        $this->assertStringContainsString('--button-radius: 999px', $css);
        $this->assertStringContainsString('--eyebrow-transform: none', $css);
        $this->assertStringContainsString('9.5rem', $css);

        foreach ([['typography' => ['display' => 'Comic Sans']], ['typography' => ['display' => '"x";}body{display:none']], ['heading_weight' => 900], ['tracking' => '1em;color:red'],
            ['density' => 'huge'], ['button_radius' => '999em'], ['eyebrow_case' => 'lowercase'], ['font_url' => 'https://evil.test/x.woff2']] as $bad) {
            try {
                $c->compile($base, $bad);
                $this->fail('Expected rejection of '.json_encode($bad));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_generative_art_is_deterministic_seeded_safe_and_covers_every_style(): void
    {
        $this->assertCount(12, GenerativeArt::STYLES);
        $seen = [];
        foreach (GenerativeArt::STYLES as $style) {
            $svg = GenerativeArt::svg($style, 'seed-a');
            $this->assertSame($svg, GenerativeArt::svg($style, 'seed-a'), "{$style} must be deterministic");
            $this->assertNotFalse(simplexml_load_string($svg), "{$style} must be valid SVG");
            $this->assertStringNotContainsString('<script', $svg);
            $this->assertStringNotContainsString('href=', $svg);
            $seen[$style] = md5($svg);
        }
        $this->assertCount(12, array_unique($seen), 'every style draws something different');
        $this->assertNotSame(GenerativeArt::svg('orbs', 'a'), GenerativeArt::svg('orbs', 'b'));
        $this->assertSame(GenerativeArt::svg('stripes', 'x'), GenerativeArt::svg('not-a-style', 'x'), 'unknown styles fall back safely');
        // Seeds are hashed, never printed.
        $this->assertStringNotContainsString('seed-a', GenerativeArt::svg('grid', 'seed-a'));
    }

    public function test_uniqueness_check_rejects_duplicates_and_near_duplicates(): void
    {
        $show = app(DirectionShowcase::class);
        $flat = fn (int $v) => array_fill(0, 32 * 20 * 3, $v);
        $a = ['thumb' => $flat(10), 'sha256' => 'aaa'];
        $b = ['thumb' => $flat(200), 'sha256' => 'bbb'];

        $ok = $show->uniqueness(['one' => $a, 'two' => $b]);
        $this->assertTrue($ok['passed']);
        $this->assertEqualsWithDelta(190.0, $ok['min'], 0.01);

        $dupe = $show->uniqueness(['one' => $a, 'two' => ['thumb' => $flat(10), 'sha256' => 'aaa']]);
        $this->assertFalse($dupe['passed']);
        $this->assertSame(['one|two'], $dupe['duplicates']);

        $near = $show->uniqueness(['one' => $a, 'two' => ['thumb' => $flat(14), 'sha256' => 'ccc']]);
        $this->assertFalse($near['passed'], 'visually near-identical screenshots must fail');
        $this->assertSame(['one', 'two'], $near['closest']);
    }

    public function test_direction_cards_use_the_real_screenshot_and_fall_back_without_one(): void
    {
        Storage::fake('public');
        $direction = new DesignDirection(['direction_key' => 'auteur', 'name' => 'Auteur', 'recipe_json' => ['template_key' => 'editorial', 'color_system' => []]]);

        $without = Blade::render('<x-direction-screenshot :direction="$d" />', ['d' => $direction]);
        $this->assertStringNotContainsString('direction-previews', $without);

        Storage::disk('public')->put('direction-previews/auteur/hero.png', 'png');
        $with = Blade::render('<x-direction-screenshot :direction="$d" />', ['d' => $direction]);
        $this->assertStringContainsString('direction-previews/auteur/hero.png', $with);
        $this->assertStringContainsString('Screenshot of the Auteur direction', $with);
    }

    /** Opt-in (≈40s): renders and screenshots all twelve directions in a real browser and checks uniqueness. */
    public function test_real_browser_screenshots_of_all_directions_are_unique(): void
    {
        if (! env('REDO_SLOW_TESTS')) {
            $this->markTestSkipped('Set REDO_SLOW_TESTS=1 to run the real-browser direction capture.');
        }
        Storage::fake('public');
        $show = app(DirectionShowcase::class);
        $captures = [];
        foreach ($this->directions() as $d) {
            $captures[$d['id']] = $show->capture($d['id']);
            $this->assertSame([], $captures[$d['id']]['errors'], "{$d['id']} console/network errors");
            $this->assertNotEmpty($captures[$d['id']]['fonts'], "{$d['id']} web fonts must actually load");
        }
        $u = $show->uniqueness($captures);
        $this->assertTrue($u['passed'], 'closest pair '.json_encode($u['closest']).' at '.$u['min']);
    }

    private function contrast(string $a, string $b): float
    {
        $l = function (string $hex): float {
            $hex = ltrim($hex, '#');
            [$r, $g, $bl] = array_map(fn ($i) => (function ($v) {
                $v = hexdec($v) / 255;

                return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
            })(substr($hex, $i, 2)), [0, 2, 4]);

            return 0.2126 * $r + 0.7152 * $g + 0.0722 * $bl;
        };
        [$hi, $lo] = [max($l($a), $l($b)), min($l($a), $l($b))];

        return ($hi + 0.05) / ($lo + 0.05);
    }
}
