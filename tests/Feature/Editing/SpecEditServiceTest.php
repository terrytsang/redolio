<?php

namespace Tests\Feature\Editing;

use App\Models\DesignSpec;
use App\Models\User;
use App\Models\VisualQaReport;
use App\Services\Blueprint\BlueprintCatalog;
use App\Services\Editing\SpecEditException;
use App\Services\Editing\SpecEditService;
use App\Services\Rendering\PreviewRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;

class SpecEditServiceTest extends TestCase
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

    private function heroId(DesignSpec $s): string
    {
        return collect($s->design_spec_json['v15']['pages'][0]['sections'])->firstWhere('component_id', 'hero')['instance_id'];
    }

    private function heroProps(DesignSpec $s): array
    {
        return collect($s->design_spec_json['v15']['pages'][0]['sections'])->firstWhere('component_id', 'hero')['props'];
    }

    private function edit(DesignSpec $s, array $ops, ?User $u = null): array
    {
        return app(SpecEditService::class)->apply($s, $s->version, $ops, $u);
    }

    public function test_editing_the_hero_creates_a_new_version_and_keeps_the_original(): void
    {
        [$run, $spec] = $this->site();
        $hid = $this->heroId($spec);

        $r = $this->edit($spec, [['op' => 'set', 'instance_id' => $hid, 'path' => 'headline', 'value' => 'Dentistry your whole family will like']]);

        $this->assertSame(2, $r['spec']->version);
        $this->assertSame('Dentistry your whole family will like', $this->heroProps($r['spec'])['headline']);
        $this->assertSame('Gentle care for the whole family', $this->heroProps($spec->fresh())['headline'], 'the previous version is untouched');
        $this->assertSame('Dentistry your whole family will like', $r['spec']->design_spec_json['hero']['headline'], 'legacy hero fields stay in sync');
        $this->assertSame('user_edited', $r['spec']->design_spec_json['v16']['hero_content']['approval_status']);
        $this->assertContains('headline', array_column($r['spec']->design_spec_json['v16']['user_edits'], 'path'));
        $this->assertSame(2, $run->currentDesignSpec()->version);
    }

    public function test_description_cta_label_and_destination_can_be_edited_with_a_real_destination(): void
    {
        [$run, $spec] = $this->site();
        $hid = $this->heroId($spec);

        $r = $this->edit($spec, [
            ['op' => 'set', 'instance_id' => $hid, 'path' => 'subheadline', 'value' => "Routine checkups and whitening\nfor every age."],
            ['op' => 'set_cta', 'instance_id' => $hid, 'path' => 'primary_cta', 'label' => 'Book a visit', 'destination_key' => 'section:contact'],
        ]);

        $p = $this->heroProps($r['spec']);
        $this->assertStringContainsString('every age', $p['subheadline']);
        $this->assertSame('Book a visit', $p['primary_cta']['label']);
        $this->assertStringStartsWith('#', $p['primary_cta']['href']);
        $this->assertSame('Book a visit', $r['spec']->design_spec_json['hero']['primary_cta_label']);
    }

    public function test_markup_is_stripped_and_never_reaches_the_page(): void
    {
        [$run, $spec] = $this->site();

        $r = $this->edit($spec, [['op' => 'set', 'instance_id' => $this->heroId($spec), 'path' => 'headline', 'value' => '<img src=x onerror=alert(1)>Hello <b>world</b>']]);

        $this->assertSame('Hello world', $this->heroProps($r['spec'])['headline']);
        $this->assertStringContainsString('Formatting and markup were removed', json_encode($r['warnings']));
    }

    public function test_unsafe_links_and_unknown_destinations_are_refused_and_nothing_is_saved(): void
    {
        [$run, $spec] = $this->site();
        $hid = $this->heroId($spec);

        foreach ([['href' => 'javascript:alert(1)'], ['href' => 'http://insecure.example'], ['destination_key' => 'https://evil.example'], ['href' => 'data:text/html,hi']] as $dest) {
            try {
                $this->edit($spec, [['op' => 'set_cta', 'instance_id' => $hid, 'path' => 'primary_cta', 'label' => 'Go'] + $dest]);
                $this->fail('expected refusal for '.json_encode($dest));
            } catch (SpecEditException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertSame(1, $run->designSpecs()->count());

        $ok = $this->edit($spec, [['op' => 'set_cta', 'instance_id' => $hid, 'path' => 'primary_cta', 'label' => 'Book online', 'href' => 'https://booking.example/brightleaf']]);
        $this->assertSame('https://booking.example/brightleaf', $this->heroProps($ok['spec'])['primary_cta']['href']);
    }

    public function test_only_registered_text_props_can_be_edited_and_length_limits_hold(): void
    {
        [$run, $spec] = $this->site();
        $hid = $this->heroId($spec);

        foreach ([['path' => 'nope', 'value' => 'x'], ['path' => 'primary_cta', 'value' => 'x'], ['path' => 'headline', 'value' => str_repeat('a', 141)], ['path' => 'headline', 'value' => '   ']] as $bad) {
            try {
                $this->edit($spec, [['op' => 'set', 'instance_id' => $hid] + $bad]);
                $this->fail('expected refusal for '.json_encode($bad));
            } catch (SpecEditException $e) {
                $this->assertSame(1, $run->designSpecs()->count());
            }
        }
        $this->expectException(SpecEditException::class);
        $this->edit($spec, [['op' => 'set', 'instance_id' => 'no-such-section', 'path' => 'headline', 'value' => 'x']]);
    }

    public function test_list_item_text_can_be_edited_by_path(): void
    {
        [$run, $spec] = $this->site();
        $svc = collect($spec->design_spec_json['v15']['pages'][0]['sections'])->first(fn ($s) => isset($s['props']['items'][0]['title']));

        $r = $this->edit($spec, [['op' => 'set', 'instance_id' => $svc['instance_id'], 'path' => 'items.0.title', 'value' => 'Family checkups']]);

        $sec = collect($r['spec']->design_spec_json['v15']['pages'][0]['sections'])->firstWhere('instance_id', $svc['instance_id']);
        $this->assertSame('Family checkups', $sec['props']['items'][0]['title']);
    }

    public function test_images_can_only_come_from_this_projects_own_assets(): void
    {
        [$run, $spec] = $this->site();
        $hid = $this->heroId($spec);

        $r = $this->edit($spec, [['op' => 'set_image', 'instance_id' => $hid, 'path' => 'image', 'asset_id' => 'team1', 'alt' => 'Our team']]);
        $this->assertSame('team1', $this->heroProps($r['spec'])['image']['asset_id']);
        $sec = collect($r['spec']->design_spec_json['v15']['pages'][0]['sections'])->firstWhere('component_id', 'hero');
        $this->assertContains('team1', array_column($sec['asset_bindings'], 'asset_id'));

        $other = $this->makeRun(null, ['foreign' => 'hero_image']);
        $this->expectException(SpecEditException::class);
        $this->edit($r['spec'], [['op' => 'set_image', 'instance_id' => $hid, 'path' => 'image', 'asset_id' => 'foreign', 'alt' => 'x']]);
    }

    public function test_optional_sections_can_be_hidden_and_shown_but_required_ones_cannot(): void
    {
        [$run, $spec] = $this->site();
        $sections = $spec->design_spec_json['v15']['pages'][0]['sections'];
        $bp = app(BlueprintCatalog::class)->find($spec->design_spec_json['v15']['design']['blueprint_id']);
        $optional = collect($bp['section_sequence'])->first(fn ($s) => $s['optional'] && collect($sections)->contains('instance_id', $s['instance_id']));
        $this->assertNotNull($optional, 'fixture blueprint has a present optional section');

        $r = $this->edit($spec, [['op' => 'hide_section', 'instance_id' => $optional['instance_id']]]);
        $this->assertFalse(collect($r['spec']->design_spec_json['v15']['pages'][0]['sections'])->contains('instance_id', $optional['instance_id']));
        $this->assertContains($optional['instance_id'], array_column($r['spec']->design_spec_json['v15']['content_mapping']['omitted_sections'], 'instance_id'));

        $back = $this->edit($r['spec'], [['op' => 'show_section', 'instance_id' => $optional['instance_id']]]);
        $this->assertTrue(collect($back['spec']->design_spec_json['v15']['pages'][0]['sections'])->contains('instance_id', $optional['instance_id']));
        $this->assertNotContains($optional['instance_id'], array_column($back['spec']->design_spec_json['v15']['content_mapping']['omitted_sections'], 'instance_id'));

        $this->expectException(SpecEditException::class);
        $this->edit($back['spec'], [['op' => 'hide_section', 'instance_id' => $this->heroId($spec)]]);
    }

    public function test_revert_restores_the_generated_wording_for_a_field_or_the_whole_section(): void
    {
        [$run, $spec] = $this->site();
        $hid = $this->heroId($spec);
        $r = $this->edit($spec, [['op' => 'set', 'instance_id' => $hid, 'path' => 'headline', 'value' => 'Brand new'], ['op' => 'set', 'instance_id' => $hid, 'path' => 'subheadline', 'value' => 'Different']]);

        $one = $this->edit($r['spec'], [['op' => 'revert', 'instance_id' => $hid, 'path' => 'headline']]);
        $this->assertSame('Gentle care for the whole family', $this->heroProps($one['spec'])['headline']);
        $this->assertSame('Different', $this->heroProps($one['spec'])['subheadline']);

        $all = $this->edit($one['spec'], [['op' => 'revert', 'instance_id' => $hid]]);
        $this->assertSame($this->heroProps($spec)['subheadline'], $this->heroProps($all['spec'])['subheadline']);
        $this->assertSame([], $all['spec']->design_spec_json['v16']['user_edits']);
    }

    public function test_an_editing_session_reuses_its_draft_but_never_a_reviewed_or_other_users_version(): void
    {
        [$run, $spec] = $this->site();
        $u = User::factory()->create();
        $hid = $this->heroId($spec);

        $a = $this->edit($spec, [['op' => 'set', 'instance_id' => $hid, 'path' => 'headline', 'value' => 'One']], $u);
        $b = $this->edit($a['spec'], [['op' => 'set', 'instance_id' => $hid, 'path' => 'headline', 'value' => 'Two']], $u);
        $this->assertTrue($b['coalesced']);
        $this->assertSame(2, $run->designSpecs()->count(), 'autosave does not create a version per keystroke');

        VisualQaReport::create(['design_spec_id' => $b['spec']->id, 'analysis_run_id' => $run->id, 'status' => 'complete', 'report_json' => []]);
        $c = $this->edit($b['spec']->fresh(), [['op' => 'set', 'instance_id' => $hid, 'path' => 'headline', 'value' => 'Three']], $u);
        $this->assertFalse($c['coalesced'], 'a version that was QA-reviewed is never rewritten');
        $this->assertSame(3, $run->designSpecs()->count());

        $d = $this->edit($c['spec'], [['op' => 'set', 'instance_id' => $hid, 'path' => 'headline', 'value' => 'Four']], User::factory()->create());
        $this->assertFalse($d['coalesced']);
    }

    public function test_a_stale_edit_is_refused_so_two_tabs_cannot_overwrite_each_other(): void
    {
        [$run, $spec] = $this->site();
        $hid = $this->heroId($spec);
        $this->edit($spec, [['op' => 'set', 'instance_id' => $hid, 'path' => 'headline', 'value' => 'From tab one']]);

        try {
            $this->edit($spec, [['op' => 'set', 'instance_id' => $hid, 'path' => 'headline', 'value' => 'From tab two']]);
            $this->fail('expected a stale refusal');
        } catch (SpecEditException $e) {
            $this->assertTrue($e->stale);
        }
    }

    public function test_restore_brings_an_old_version_back_as_a_new_version(): void
    {
        [$run, $spec] = $this->site();
        $r = $this->edit($spec, [['op' => 'set', 'instance_id' => $this->heroId($spec), 'path' => 'headline', 'value' => 'Edited']]);

        $restored = app(SpecEditService::class)->restore($r['spec'], $spec->fresh(), null);

        $this->assertSame(3, $restored->version);
        $this->assertSame('Gentle care for the whole family', $this->heroProps($restored)['headline']);
        $this->assertSame('Edited', $this->heroProps($r['spec']->fresh())['headline'], 'history is kept');
    }

    public function test_the_edited_spec_still_renders_and_shows_the_new_text(): void
    {
        [$run, $spec] = $this->site();
        $r = $this->edit($spec, [['op' => 'set', 'instance_id' => $this->heroId($spec), 'path' => 'headline', 'value' => 'Smiles for every age']]);

        $html = app(PreviewRenderer::class)->renderBlueprintDocument($r['spec']->design_spec_json, $run);

        $this->assertStringContainsString('Smiles for every age', $html);
        $this->assertStringNotContainsString('Gentle care for the whole family', $html);
    }

    public function test_restore_carries_the_restored_versions_asset_rows_not_the_current_ones(): void
    {
        [$run, $spec] = $this->site();
        $r = $this->edit($spec, [['op' => 'set', 'instance_id' => $this->heroId($spec), 'path' => 'headline', 'value' => 'Something else entirely']]);
        $r['spec']->specAssets()->delete();
        $r['spec']->specAssets()->create(['asset_key' => 'only-in-v2', 'component_role' => 'x', 'usage_json' => []]);

        $restored = app(SpecEditService::class)->restore($r['spec'], $spec->fresh(), null);

        $this->assertEqualsCanonicalizing($spec->specAssets()->pluck('asset_key')->all(), $restored->specAssets()->pluck('asset_key')->all());
        $this->assertNotContains('only-in-v2', $restored->specAssets()->pluck('asset_key')->all());
    }
}
