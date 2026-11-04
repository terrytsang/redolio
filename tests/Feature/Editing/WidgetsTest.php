<?php

namespace Tests\Feature\Editing;

use App\Models\DesignSpec;
use App\Services\Blueprint\BlueprintRenderer;
use App\Services\Editing\SpecEditException;
use App\Services\Editing\SpecEditService;
use App\Services\Publishing\ArtifactValidator;
use App\Services\Publishing\ProductionArtifactGenerator;
use App\Services\Rendering\PreviewRenderer;
use App\Services\Widgets\SocialLinks;
use App\Services\Widgets\WidgetCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;

class WidgetsTest extends TestCase
{
    use BuildsBlueprintSpecs;
    use RefreshDatabase;

    private function site(): array
    {
        $run = $this->makeRun(null, ['hero1' => 'hero_image', 'team1' => 'team_portrait']);
        [$res, $spec] = $this->attach($run, $this->legacySpec());
        $this->assertTrue($res['attached'], implode('; ', $res['errors']));

        return [$run, $spec];
    }

    private function add(DesignSpec $s, string $type, array $config = [], string $after = 'top'): DesignSpec
    {
        return app(SpecEditService::class)->apply($s, $s->version, [['op' => 'add_widget', 'type' => $type, 'after' => $after, 'config' => $config]], null)['spec'];
    }

    private function widgets(DesignSpec $s): array
    {
        return $s->design_spec_json['v15']['pages'][0]['widgets'] ?? [];
    }

    private function html(DesignSpec $s, bool $publish = false): string
    {
        return app(PreviewRenderer::class)->renderBlueprintDocument($s->design_spec_json, $s->analysisRun, false, $publish);
    }

    private function firstSection(DesignSpec $s): string
    {
        return $s->design_spec_json['v15']['pages'][0]['sections'][0]['instance_id'];
    }

    public function test_every_widget_type_can_be_added_with_its_defaults_and_pages_stay_valid(): void
    {
        [$run, $spec] = $this->site();
        $types = array_keys(app(WidgetCatalog::class)->types());
        $this->assertGreaterThanOrEqual(11, count($types));
        foreach ($types as $t) {
            $spec = $this->add($spec, $t, [], $this->firstSection($spec));
        }
        $this->assertCount(count($types), $this->widgets($spec));
        $this->assertTrue($spec->design_spec_json['v15']['validation']['schema_valid']);
        $html = $this->html($spec);
        $this->assertStringContainsString('data-widget-type="hero"', $html);
        $this->assertStringContainsString('data-widget-type="testimonials"', $html);
        $this->assertSame(1, substr_count($html, '<h1'), 'a hero add-on never adds a second <h1>');
        $this->assertSame(1, substr_count($html, 'id="top"'), 'nor a second #top');
    }

    public function test_the_six_hero_designs_render(): void
    {
        [$run, $spec] = $this->site();
        foreach (array_keys(WidgetCatalog::HEROES) as $v) {
            $s = $this->add($spec, 'hero', ['variant' => $v, 'headline' => "Hero {$v}"], 'top');
            $this->assertStringContainsString("Hero {$v}", $this->html($s), $v);
            $spec = app(SpecEditService::class)->apply($s, $s->version, [['op' => 'remove_widget', 'widget_id' => $this->widgets($s)[0]['id']]], null)['spec'];
        }
        $this->assertSame([], $this->widgets($spec));
        $this->assertArrayNotHasKey('widgets', $spec->design_spec_json['v15']['pages'][0]);
    }

    public function test_update_move_remove_and_singletons(): void
    {
        [$run, $spec] = $this->site();
        $svc = app(SpecEditService::class);
        $first = $this->firstSection($spec);
        $s = $this->add($spec, 'cta-banner', [], $first);
        $wid = $this->widgets($s)[0]['id'];

        $s = $svc->apply($s, $s->version, [['op' => 'update_widget', 'widget_id' => $wid, 'config' => ['heading' => 'Book this week']]], null)['spec'];
        $this->assertSame('Book this week', $this->widgets($s)[0]['config']['heading']);
        $this->assertSame('Contact us', $this->widgets($s)[0]['config']['button_label'], 'a partial update keeps the rest');

        $last = collect($s->design_spec_json['v15']['pages'][0]['sections'])->last()['instance_id'];
        $s = $svc->apply($s, $s->version, [['op' => 'move_widget', 'widget_id' => $wid, 'after' => $last]], null)['spec'];
        $this->assertSame($last, $this->widgets($s)[0]['after']);
        $html = $this->html($s);
        $this->assertGreaterThan(strpos($html, 'data-section="'.$last.'"'), strpos($html, 'data-widget="'.$wid.'"'));

        $s = $this->add($s, 'floating-chat', ['items' => [['platform' => 'whatsapp', 'value' => '+60 12-345 6789']]]);
        $this->expectException(SpecEditException::class);
        $this->add($s, 'floating-chat');
    }

    public function test_widget_input_is_cleaned_and_unsafe_values_are_refused(): void
    {
        [$run, $spec] = $this->site();
        $bad = [
            ['cta-banner', ['button_label' => 'Go', 'button_href' => 'javascript:alert(1)']],
            ['form', ['destination' => 'endpoint', 'endpoint' => 'http://insecure.example/post']],
            ['form', ['destination' => 'email', 'email' => 'not-an-email']],
            ['social', ['items' => [['platform' => 'whatsapp', 'value' => 'abc']]]],
            ['analytics', ['measurement_id' => 'UA-<script>']],
            ['video', ['url' => 'https://evil.example/watch?v=aaaaaaaaaaa']],
            ['hero', ['headline' => '']],
        ];
        foreach ($bad as [$type, $cfg]) {
            try {
                $this->add($spec, $type, $cfg);
                $this->fail("expected refusal for {$type}: ".json_encode($cfg));
            } catch (SpecEditException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $s = $this->add($spec, 'cta-banner', ['heading' => '<img src=x onerror=alert(1)>Hello', 'button_label' => 'Go', 'button_href' => 'example.com/start']);
        $c = $this->widgets($s)[0]['config'];
        $this->assertSame('Hello', $c['heading']);
        $this->assertSame('https://example.com/start', $c['button_href']);
    }

    public function test_social_links_are_built_safely_per_platform(): void
    {
        $this->assertSame('https://wa.me/60123456789?text=Hi%20there', SocialLinks::href('whatsapp', '+60 12-345 6789', 'Hi there'));
        $this->assertSame('mailto:hi@example.com', SocialLinks::href('email', 'hi@example.com'));
        $this->assertSame('tel:+60123456789', SocialLinks::href('phone', '+60 12 345 6789'));
        $this->assertSame('https://www.instagram.com/brightleaf', SocialLinks::href('instagram', '@brightleaf'));
        $this->assertSame('https://shopee.com.my/brightleaf', SocialLinks::href('shopee', 'shopee.com.my/brightleaf'));
        $this->assertNull(SocialLinks::href('facebook', 'javascript:alert(1)'));
        $this->assertNull(SocialLinks::href('website', 'http://insecure.example'));
        $this->assertNull(SocialLinks::href('whatsapp', '123'));
        foreach (array_keys(SocialLinks::PLATFORMS) as $p) {
            $this->assertStringContainsString('<svg', SocialLinks::icon($p));
        }
    }

    public function test_embed_code_is_never_in_the_page_and_publishes_as_a_sandboxed_frame_file(): void
    {
        [$run, $spec] = $this->site();
        $s = $this->add($spec, 'embed', ['code' => '<script>alert(document.cookie)</script><div>Book now</div>', 'height' => 300]);
        $id = $this->widgets($s)[0]['id'];

        $preview = $this->html($s);
        $this->assertStringNotContainsString('alert(document.cookie)', $preview);
        $this->assertStringNotContainsString('<iframe', $preview);

        $pub = $this->html($s, true);
        $this->assertStringNotContainsString('alert(document.cookie)', $pub);
        $this->assertStringContainsString('src="embeds/'.$id.'.html"', $pub);
        $this->assertStringContainsString('sandbox="allow-scripts allow-popups allow-forms allow-popups-to-escape-sandbox"', $pub);
        $files = app(BlueprintRenderer::class)->widgetFiles($s->design_spec_json['v15']);
        $this->assertStringContainsString('alert(document.cookie)', $files['embeds'][$id]);
    }

    public function test_analytics_and_carousel_only_load_scripts_on_the_published_page(): void
    {
        [$run, $spec] = $this->site();
        $s = $this->add($spec, 'analytics', ['measurement_id' => 'G-ABCD123456']);
        $s = $this->add($s, 'testimonials', [], $this->firstSection($s));

        $this->assertStringNotContainsString('googletagmanager', $this->html($s));
        $this->assertStringNotContainsString('redo-widgets.js', $this->html($s));
        $pub = $this->html($s, true);
        $this->assertStringContainsString('https://www.googletagmanager.com/gtag/js?id=G-ABCD123456', $pub);
        $this->assertStringContainsString('<script src="assets/redo-widgets.js" defer>', $pub);
    }

    public function test_the_published_page_ships_widget_files_and_serves_embeds_sandboxed(): void
    {
        [$run, $spec] = $this->site();
        $s = $this->add($spec, 'embed', ['code' => '<p>hello</p>']);
        $s = $this->add($s, 'testimonials', [], $this->firstSection($s));
        $dir = sys_get_temp_dir().'/redo-pub-'.uniqid();
        app(ProductionArtifactGenerator::class)->generate($run->fresh(), $s, $dir, false);
        $id = collect($this->widgets($s))->firstWhere('type', 'embed')['id'];
        $this->assertFileExists("{$dir}/embeds/{$id}.html");
        $this->assertFileExists("{$dir}/assets/redo-widgets.js");
        $this->assertTrue(app(ArtifactValidator::class)->validate($dir, $s)['valid']);
        File::deleteDirectory($dir);
    }

    public function test_the_page_foot_has_no_form_by_default(): void
    {
        [$run, $spec] = $this->site();
        $this->assertStringNotContainsString('<form', $this->html($spec));
    }

    private function order(DesignSpec $s): array
    {
        // What the page shows, top to bottom: section ids and add-on labels in drawing order.
        $html = $this->html($s);
        preg_match_all('/data-section="([^"]+)"|data-widget="([^"]+)"/', $html, $m, PREG_SET_ORDER);

        return array_map(fn ($x) => $x[2] ?? false ? 'w:'.$x[2] : $x[1], $m);
    }

    public function test_an_add_on_can_be_moved_up_and_down_between_rows_and_above_or_below_other_add_ons(): void
    {
        [$run, $spec] = $this->site();
        $svc = app(SpecEditService::class);
        $ids = array_column($spec->design_spec_json['v15']['pages'][0]['sections'], 'instance_id');
        $last = end($ids);
        $first = $ids[0];

        // Two add-ons after the last section (the footer).
        $spec = $this->add($spec, 'hero', [], $last);
        $spec = $this->add($spec, 'cta-banner', ['heading' => 'Book now', 'button_label' => 'Call us', 'button_href' => '#contact'], $last);
        [$hero, $cta] = array_column($this->widgets($spec), 'id');
        $this->assertSame([$first], array_slice(array_filter($this->order($spec), fn ($x) => ! str_starts_with($x, 'w:')), 0, 1));
        $this->assertSame(["w:{$hero}", "w:{$cta}"], array_slice($this->order($spec), -2), 'they follow the footer in the order they were added');

        // The hero add-on goes from the bottom to the very top of the page.
        $spec = $svc->apply($spec, $spec->version, [['op' => 'move_widget', 'widget_id' => $hero, 'after' => 'top']], null)['spec'];
        $this->assertSame("w:{$hero}", $this->order($spec)[0]);
        $this->assertSame("w:{$cta}", (function ($a) { return end($a); })($this->order($spec)));

        // Right after the first section, and back down below the footer — above the other add-on (explicit position).
        $spec = $svc->apply($spec, $spec->version, [['op' => 'move_widget', 'widget_id' => $hero, 'after' => $first]], null)['spec'];
        $o = $this->order($spec);
        $this->assertSame([$first, "w:{$hero}"], array_slice($o, array_search($first, $o), 2));
        $spec = $svc->apply($spec, $spec->version, [['op' => 'move_widget', 'widget_id' => $hero, 'after' => $last, 'before' => $cta]], null)['spec'];
        $this->assertSame(["w:{$hero}", "w:{$cta}"], array_slice($this->order($spec), -2));
        $spec = $svc->apply($spec, $spec->version, [['op' => 'move_widget', 'widget_id' => $cta, 'after' => $last, 'before' => $hero]], null)['spec'];
        $this->assertSame(["w:{$cta}", "w:{$hero}"], array_slice($this->order($spec), -2), 'a move can land above another add-on in the same row');

        // A "before" add-on that sits somewhere else is refused, never silently misplaced.
        try {
            $svc->apply($spec, $spec->version, [['op' => 'move_widget', 'widget_id' => $cta, 'after' => $first, 'before' => $hero]], null);
            $this->fail('expected a refusal');
        } catch (SpecEditException $e) {
            $this->assertStringContainsString('changed', $e->getMessage());
        }
        $this->assertCount(2, $this->widgets($spec), 'no add-on is lost by moving');
    }

    public function test_only_row_add_ons_get_a_drag_grip_and_a_top_bar_stays_first(): void
    {
        [$run, $spec] = $this->site();
        $spec = $this->add($spec, 'hero', [], $this->firstSection($spec));
        $spec = $this->add($spec, 'floating-chat', ['items' => [['platform' => 'whatsapp', 'value' => '60123456789']]]);
        $spec = $this->add($spec, 'announcement', ['text' => 'Launch week']);
        $html = $this->html($spec);

        $this->assertMatchesRegularExpression('/data-widget-type="hero" data-widget-place="inline"/', $html);
        $this->assertMatchesRegularExpression('/data-widget-type="announcement" data-widget-place="top"/', $html);
        $this->assertMatchesRegularExpression('/data-widget-type="floating-chat" data-widget-place="global"/', $html);
        $order = $this->order($spec);
        $bar = array_values(array_filter($this->widgets($spec), fn ($w) => $w['type'] === 'announcement'))[0]['id'];
        $this->assertSame("w:{$bar}", $order[0], 'the announcement bar is above the page even when added last');

        // Moving a bar or floating add-on is refused: they stay where they are.
        foreach (['announcement', 'floating-chat'] as $type) {
            $id = array_values(array_filter($this->widgets($spec), fn ($w) => $w['type'] === $type))[0]['id'];
            try {
                app(SpecEditService::class)->apply($spec, $spec->version, [['op' => 'move_widget', 'widget_id' => $id, 'after' => 'top']], null);
                $this->fail("{$type} should stay where it is");
            } catch (SpecEditException $e) {
                $this->assertStringContainsString('stays where it is', $e->getMessage());
            }
        }
    }

    public function test_a_button_can_be_saved_one_field_at_a_time_and_only_shows_once_it_has_both_text_and_link(): void
    {
        [$run, $spec] = $this->site();
        $svc = app(SpecEditService::class);
        $spec = $this->add($spec, 'hero', [], $this->firstSection($spec));
        $wid = $this->widgets($spec)[0]['id'];

        // The editor saves each field as it is filled in: the text first, the link a moment later.
        $spec = $svc->apply($spec, $spec->version, [['op' => 'update_widget', 'widget_id' => $wid, 'config' => ['secondary_label' => 'See pricing']]], null)['spec'];
        $this->assertSame('See pricing', $this->widgets($spec)[0]['config']['secondary_label'], 'the text is kept, not cleared with an error');
        $this->assertStringNotContainsString('See pricing', $this->html($spec), 'a half-filled button stays hidden');

        $spec = $svc->apply($spec, $spec->version, [['op' => 'update_widget', 'widget_id' => $wid, 'config' => ['secondary_href' => '#pricing']]], null)['spec'];
        $this->assertStringContainsString('See pricing', $this->html($spec));
        $this->assertStringContainsString('href="#pricing"', $this->html($spec));

        // The same goes for the call-to-action banner's button and the announcement bar's link; the hero still needs its headline.
        $spec = $this->add($spec, 'cta-banner', ['heading' => 'Hi', 'button_label' => '', 'button_href' => ''], $this->firstSection($spec));
        $cta = collect($this->widgets($spec))->firstWhere('type', 'cta-banner')['id'];
        $spec = $svc->apply($spec, $spec->version, [['op' => 'update_widget', 'widget_id' => $cta, 'config' => ['button_label' => 'Call us']]], null)['spec'];
        $this->assertSame('Call us', collect($this->widgets($spec))->firstWhere('type', 'cta-banner')['config']['button_label']);
        try {
            $svc->apply($spec, $spec->version, [['op' => 'update_widget', 'widget_id' => $wid, 'config' => ['headline' => '']]], null);
            $this->fail('the hero needs a headline');
        } catch (SpecEditException $e) {
            $this->assertStringContainsString('headline', $e->getMessage());
        }
    }
}
