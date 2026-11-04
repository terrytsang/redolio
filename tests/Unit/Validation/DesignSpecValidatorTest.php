<?php

namespace Tests\Unit\Validation;

use App\Services\MetaApi\Validation\DesignSpecValidator;
use Tests\TestCase;

class DesignSpecValidatorTest extends TestCase
{
    public function test_unknown_template_key_falls_back_to_default(): void
    {
        $validator = new DesignSpecValidator;

        $result = $validator->validate(['template_key' => 'evil_custom_template'], [], []);

        $this->assertSame(config('design_allowlist.default_template'), $result['template_key']);
    }

    public function test_unknown_color_token_falls_back_to_default(): void
    {
        $validator = new DesignSpecValidator;

        $result = $validator->validate([
            'brand' => ['color_tokens' => ['primary' => 'hotpink-hacked']],
        ], [], []);

        $this->assertSame(config('design_allowlist.default_color_tokens.primary'), $result['brand']['color_tokens']['primary']);
    }

    public function test_strips_script_tags_from_text_fields(): void
    {
        $validator = new DesignSpecValidator;

        $result = $validator->validate([
            'hero' => ['headline' => '<script>alert(1)</script>Great headline'],
        ], [], []);

        $this->assertStringNotContainsString('<script>', $result['hero']['headline']);
        $this->assertStringContainsString('Great headline', $result['hero']['headline']);
    }

    public function test_drops_sections_with_unknown_section_key(): void
    {
        $validator = new DesignSpecValidator;

        $result = $validator->validate([
            'sections' => [
                ['section_key' => 'totally_made_up', 'heading' => 'x'],
                ['section_key' => 'offerings', 'heading' => 'Our offerings'],
            ],
        ], [], []);

        $this->assertCount(1, $result['sections']);
        $this->assertSame('offerings', $result['sections'][0]['section_key']);
    }

    public function test_only_keeps_evidence_and_issue_ids_that_exist(): void
    {
        $validator = new DesignSpecValidator;

        $result = $validator->validate([
            'sections' => [
                [
                    'section_key' => 'trust',
                    'heading' => 'Trust',
                    'source_evidence_ids' => ['p0-title', 'fake-evidence'],
                    'addresses_issue_ids' => ['issue-1', 'fake-issue'],
                ],
            ],
        ], ['issue-1'], ['p0-title']);

        $this->assertSame(['p0-title'], $result['sections'][0]['source_evidence_ids']);
        $this->assertSame(['issue-1'], $result['sections'][0]['addresses_issue_ids']);
    }

    public function test_drops_asset_references_with_unknown_asset_id(): void
    {
        $validator = new DesignSpecValidator;

        $result = $validator->validate([
            'asset_references' => [
                ['asset_id' => 'a-real-asset', 'role' => 'hero_portrait', 'reason' => 'founder photo'],
                ['asset_id' => 'made-up-asset', 'role' => 'logo', 'reason' => 'fabricated'],
            ],
        ], [], [], ['a-real-asset']);

        $this->assertCount(1, $result['asset_references']);
        $this->assertSame('a-real-asset', $result['asset_references'][0]['asset_id']);
        $this->assertSame('hero_portrait', $result['asset_references'][0]['role']);
    }

    public function test_layout_composition_falls_back_to_first_allowed_when_direction_known(): void
    {
        $validator = new DesignSpecValidator;

        $result = $validator->validate([
            'layout_composition' => 'made_up_composition',
        ], [], [], [], ['asymmetric_two_column', 'full_width_editorial']);

        $this->assertSame('asymmetric_two_column', $result['layout_composition']);
    }
}
