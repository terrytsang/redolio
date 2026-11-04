<?php

namespace App\Support;

/**
 * A single realistic, generic DesignSpec reused across every direction
 * card on the gallery and the direction picker — rendered through the
 * same allowlisted templates real users get (PreviewRenderer), just with
 * placeholder business content instead of a real analysis. This lets a
 * direction card show its actual hero section, real buttons and real
 * layout instead of an abstract swatch.
 */
class SampleDesignSpec
{
    public static function forDirection(string $directionKey): array
    {
        return [
            'template_key' => $directionKey,
            'business_name' => 'Harper & Co',
            'primary_goal' => 'inquiry',
            'brand' => [
                'visual_tone' => 'professional',
                'color_tokens' => ['primary' => 'ink', 'secondary' => 'slate', 'accent' => 'amber', 'background' => 'white', 'text' => 'ink'],
                'typography' => 'modern_sans',
                'spacing_scale' => 'balanced',
                'button_style' => 'solid',
            ],
            'hero' => [
                'eyebrow' => 'Trusted since 2012',
                'headline' => 'Grow with a website that finally works as hard as you do',
                'subheadline' => 'We help small teams turn visitors into customers with clear, confident design.',
                'primary_cta_label' => 'Get started',
                'primary_cta_action' => 'contact',
                'secondary_cta_label' => 'See our work',
                'image_asset_key' => null,
            ],
            'sections' => [
                [
                    'section_key' => 'offerings',
                    'heading' => 'What we do',
                    'body' => 'Three ways we help your business grow.',
                    'items' => [
                        ['title' => 'Strategy', 'description' => 'Clear plans grounded in real evidence, not guesswork.', 'label' => 'Discovery', 'asset_key' => null],
                        ['title' => 'Design', 'description' => 'Interfaces people actually enjoy using, built around your brand.', 'label' => 'Design', 'asset_key' => null],
                        ['title' => 'Growth', 'description' => 'Ongoing improvements after launch, not a one-off project.', 'label' => 'Growth', 'asset_key' => null],
                    ],
                    'source_evidence_ids' => [],
                    'addresses_issue_ids' => [],
                ],
                [
                    'section_key' => 'trust',
                    'heading' => 'Why teams choose us',
                    'body' => 'Real results, delivered for real businesses like yours.',
                    'items' => [
                        ['title' => 'Proven process', 'description' => 'A clear path from first call to launch day.', 'label' => null, 'asset_key' => null],
                        ['title' => 'Honest reporting', 'description' => 'You always know exactly where things stand.', 'label' => null, 'asset_key' => null],
                    ],
                    'source_evidence_ids' => [],
                    'addresses_issue_ids' => [],
                ],
            ],
            'footer' => [
                'summary' => 'Based in your city, working with clients everywhere.',
                'contact_text' => 'hello@harperandco.example',
                'cta_label' => 'Start a project',
            ],
            'rationale' => [],
            'asset_references' => [],
        ];
    }
}
