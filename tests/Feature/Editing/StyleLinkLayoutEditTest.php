<?php

namespace Tests\Feature\Editing;

use App\Models\DesignSpec;
use App\Models\User;
use App\Services\Editing\SpecEditException;
use App\Services\Editing\SpecEditService;
use App\Services\Rendering\PreviewRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;

class StyleLinkLayoutEditTest extends TestCase
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

    private function hero(DesignSpec $s): array
    {
        return collect($s->design_spec_json['v15']['pages'][0]['sections'])->firstWhere('component_id', 'hero');
    }

    private function edit(DesignSpec $s, array $ops, ?User $u = null): array
    {
        return app(SpecEditService::class)->apply($s, $s->version, $ops, $u);
    }

    private function html(DesignSpec $s): string
    {
        return app(PreviewRenderer::class)->renderBlueprintDocument($s->design_spec_json, $s->analysisRun, false);
    }

    public function test_text_size_and_font_are_stored_beside_the_props_and_rendered_on_that_text_only(): void
    {
        [$run, $spec] = $this->site();
        $hid = $this->hero($spec)['instance_id'];

        $r = $this->edit($spec, [['op' => 'set_style', 'instance_id' => $hid, 'path' => 'headline', 'size' => 64, 'font' => 'playfair-display']]);

        $this->assertSame(['size' => 64, 'font' => 'playfair-display'], $this->hero($r['spec'])['overrides']['fields']['headline']);
        $this->assertSame($this->hero($spec)['props'], $this->hero($r['spec'])['props'], 'wording is untouched');
        $html = $this->html($r['spec']);
        $this->assertMatchesRegularExpression('/<h1[^>]*style="[^"]*font-size:clamp\(\d+px,5\.33vw,64px\)[^"]*font-family:[^"]*Playfair Display/', $html);
        $this->assertDoesNotMatchRegularExpression('/<p class="bp-lead"[^>]*style=/', $html, 'other text is not restyled');
        $this->assertStringContainsString('font-family:"Playfair Display"', str_replace('font-family: ', 'font-family:', $html), 'the chosen font is loaded or at least declared');
    }

    public function test_size_and_font_are_validated_and_can_be_cleared(): void
    {
        [$run, $spec] = $this->site();
        $hid = $this->hero($spec)['instance_id'];
        foreach ([['size' => 4], ['size' => 500], ['font' => 'comic-sans'], ['font' => 'x;}body{display:none']] as $bad) {
            try {
                $this->edit($spec, [['op' => 'set_style', 'instance_id' => $hid, 'path' => 'headline'] + $bad]);
                $this->fail('expected refusal for '.json_encode($bad));
            } catch (SpecEditException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $set = $this->edit($spec, [['op' => 'set_style', 'instance_id' => $hid, 'path' => 'headline', 'size' => 50]]);
        $clear = $this->edit($set['spec'], [['op' => 'set_style', 'instance_id' => $hid, 'path' => 'headline', 'size' => null, 'font' => null]]);
        $this->assertArrayNotHasKey('overrides', $this->hero($clear['spec']));
    }

    public function test_links_on_text_are_safe_wrap_the_words_and_can_be_removed(): void
    {
        [$run, $spec] = $this->site();
        $hid = $this->hero($spec)['instance_id'];
        foreach (['javascript:alert(1)', 'http://insecure.example', 'data:text/html,hi', '//evil.example'] as $href) {
            try {
                $this->edit($spec, [['op' => 'set_link', 'instance_id' => $hid, 'path' => 'subheadline', 'href' => $href]]);
                $this->fail("expected refusal for {$href}");
            } catch (SpecEditException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $r = $this->edit($spec, [['op' => 'set_link', 'instance_id' => $hid, 'path' => 'subheadline', 'href' => 'https://example.com/about']]);
        $this->assertMatchesRegularExpression('#<p class="bp-lead"[^>]*><a href="https://example.com/about"[^>]*class="bp-textlink"[^>]*>#', $this->html($r['spec']));

        $gone = $this->edit($r['spec'], [['op' => 'set_link', 'instance_id' => $hid, 'path' => 'subheadline', 'href' => '']]);
        $this->assertStringNotContainsString('bp-textlink"', $this->html($gone['spec']));
    }

    public function test_a_picture_can_carry_a_link_but_a_button_must_use_its_own_destination(): void
    {
        [$run, $spec] = $this->site();
        $h = $this->hero($spec);
        $imagePath = collect(['image'])->first(fn ($p) => isset($h['props'][$p])) ?? null;
        if ($imagePath) {
            $r = $this->edit($spec, [['op' => 'set_link', 'instance_id' => $h['instance_id'], 'path' => 'image', 'href' => 'https://example.com/gallery']]);
            $this->assertSame('https://example.com/gallery', $this->hero($r['spec'])['overrides']['fields']['image']['href']);
        }
        $this->expectException(SpecEditException::class);
        $this->edit($spec, [['op' => 'set_link', 'instance_id' => $h['instance_id'], 'path' => 'primary_cta', 'href' => 'https://example.com']]);
    }

    public function test_container_width_and_column_split_are_rendered_and_bounded(): void
    {
        [$run, $spec] = $this->site();
        $h = $this->hero($spec);
        $r = $this->edit($spec, [['op' => 'set_layout', 'instance_id' => $h['instance_id'], 'width' => 'narrow']]);
        $this->assertStringContainsString('--container:760px', $this->html($r['spec']));

        foreach ([['split' => 10], ['split' => 95], ['width' => 'huge']] as $bad) {
            try {
                $this->edit($r['spec'], [['op' => 'set_layout', 'instance_id' => $h['instance_id']] + $bad]);
                $this->fail('expected refusal for '.json_encode($bad));
            } catch (SpecEditException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }

        if ($h['variant_id'] === 'split') {
            $s = $this->edit($r['spec'], [['op' => 'set_layout', 'instance_id' => $h['instance_id'], 'split' => 40]]);
            $this->assertStringContainsString('--cols:40fr 60fr', $this->html($s['spec']));
        }
        $std = $this->edit($r['spec'], [['op' => 'set_layout', 'instance_id' => $h['instance_id'], 'width' => 'standard', 'split' => null]]);
        $this->assertArrayNotHasKey('overrides', $this->hero($std['spec']));
    }

    public function test_revert_clears_presentation_choices_and_pages_without_overrides_render_unchanged(): void
    {
        [$run, $spec] = $this->site();
        $hid = $this->hero($spec)['instance_id'];
        $before = $this->html($spec);
        $r = $this->edit($spec, [['op' => 'set_style', 'instance_id' => $hid, 'path' => 'headline', 'size' => 70], ['op' => 'set_layout', 'instance_id' => $hid, 'width' => 'wide']]);
        $this->assertNotSame($before, $this->html($r['spec']));
        $back = $this->edit($r['spec'], [['op' => 'revert', 'instance_id' => $hid]]);
        $this->assertArrayNotHasKey('overrides', $this->hero($back['spec']));
        $this->assertSame($before, $this->html($back['spec']));
    }

    public function test_save_closes_the_draft_so_the_next_edit_is_a_new_version(): void
    {
        [$run, $spec] = $this->site();
        $svc = app(SpecEditService::class);
        $hid = $this->hero($spec)['instance_id'];
        $a = $this->edit($spec, [['op' => 'set', 'instance_id' => $hid, 'path' => 'headline', 'value' => 'First wording']]);
        $this->assertNotEmpty($a['spec']->design_spec_json['v16']['edit_draft']);

        $saved = $svc->save($a['spec'], $a['spec']->version, null);
        $this->assertNull($saved->design_spec_json['v16']['edit_draft']);
        $this->assertStringStartsWith('Saved layout', $saved->design_spec_json['v16']['change_summary']);
        $this->assertSame($a['spec']->version, $saved->version, 'saving does not invent a version');

        $b = $this->edit($saved, [['op' => 'set', 'instance_id' => $hid, 'path' => 'headline', 'value' => 'Second wording']]);
        $this->assertSame($saved->version + 1, $b['spec']->version);
        $this->assertSame('First wording', $this->hero($saved->fresh())['props']['headline'], 'the saved layout is still there to restore');
        $again = $svc->save($b['spec'], $b['spec']->version, null);
        $this->assertSame($b['spec']->version, $svc->save($again, $again->version, null)->version, 'saving with nothing new is a no-op');
    }
}
