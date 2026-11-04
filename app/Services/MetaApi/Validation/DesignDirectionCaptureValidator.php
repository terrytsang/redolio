<?php

namespace App\Services\MetaApi\Validation;

/**
 * Validates the design_direction_capture response (admin "generate a Design
 * Direction from a reference URL" feature). The captured recipe always
 * targets one of the already-implemented preview templates — only
 * recipe_json metadata (colors, typography, traits) is AI-generated; the
 * actual Blade markup is never invented, matching PreviewRenderer's
 * allowlist-only template resolution.
 */
class DesignDirectionCaptureValidator
{
    /**
     * @param  string[]  $eligibleTemplateKeys
     */
    public function validate(array $data, array $eligibleTemplateKeys): array
    {
        $templateKey = in_array($data['template_key'] ?? null, $eligibleTemplateKeys, true)
            ? $data['template_key']
            : ($eligibleTemplateKeys[0] ?? 'smb_default');

        $colors = (array) ($data['color_system'] ?? []);
        $colorSystem = [];
        foreach (['background', 'surface', 'text', 'text_muted', 'accent', 'accent_contrast'] as $role) {
            $colorSystem[$role] = $this->normalizeHex($colors[$role] ?? null) ?? '#0A0B0D';
        }

        $typography = (array) ($data['typography_system'] ?? []);
        $typographyEnum = ['font-sans', 'font-serif', 'font-mono'];

        return [
            'family' => is_string($data['family'] ?? null) ? trim(strip_tags($data['family'])) : 'custom',
            'description' => is_string($data['description'] ?? null) ? trim(strip_tags($data['description'])) : '',
            'best_for' => $this->stringList($data['best_for'] ?? []),
            'brand_traits' => $this->stringList($data['brand_traits'] ?? []),
            'color_system' => $colorSystem,
            'typography_system' => [
                'display' => in_array($typography['display'] ?? null, $typographyEnum, true) ? $typography['display'] : 'font-sans',
                'body' => in_array($typography['body'] ?? null, $typographyEnum, true) ? $typography['body'] : 'font-sans',
            ],
            'layout_compositions' => $this->stringList($data['layout_compositions'] ?? []),
            'hero_patterns' => $this->stringList($data['hero_patterns'] ?? []),
            'section_patterns' => $this->stringList($data['section_patterns'] ?? []),
            'image_treatment' => is_string($data['image_treatment'] ?? null) ? trim(strip_tags($data['image_treatment'])) : '',
            'spacing_system' => in_array($data['spacing_system'] ?? null, ['spacious', 'balanced', 'compact'], true) ? $data['spacing_system'] : 'balanced',
            'component_theme' => is_string($data['component_theme'] ?? null) ? trim(strip_tags($data['component_theme'])) : '',
            'responsive_rules' => is_string($data['responsive_rules'] ?? null) ? trim(strip_tags($data['responsive_rules'])) : '',
            'accessibility_rules' => is_string($data['accessibility_rules'] ?? null) ? trim(strip_tags($data['accessibility_rules'])) : '',
            'template_key' => $templateKey,
        ];
    }

    private function normalizeHex(?string $value): ?string
    {
        if (! $value || ! preg_match('/^#?[0-9a-fA-F]{6}$/', $value)) {
            return null;
        }

        return '#'.ltrim($value, '#');
    }

    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($v) => is_string($v) ? trim(strip_tags($v)) : null,
            $value,
        )));
    }
}
