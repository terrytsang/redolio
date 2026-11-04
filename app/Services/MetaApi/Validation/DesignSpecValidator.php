<?php

namespace App\Services\MetaApi\Validation;

/**
 * Validates a model-produced DesignSpec (AI-04/FR-007). Only allowlisted
 * template, section, token, typography and CTA-action identifiers survive;
 * unknown values are mapped to safe defaults rather than rendered as-is.
 * Any field value is plain-text only — this validator never accepts or
 * passes through markup, scripts, or executable content (AT-10/AT-19).
 */
class DesignSpecValidator
{
    /**
     * @param  string[]  $validAssetKeys  Real website_assets.asset_key values for this run (AT-24: never trust a model-supplied asset_id blindly).
     * @param  string[]  $allowedLayoutCompositions  The selected design direction's recipe_json['layout_compositions']; empty when no direction was requested.
     */
    public function validate(array $spec, array $diagnosisIssueIds, array $evidenceIds, array $validAssetKeys = [], array $allowedLayoutCompositions = []): array
    {
        $allow = config('design_allowlist');

        return [
            'template_key' => $this->enum($spec['template_key'] ?? null, $allow['templates'], $allow['default_template']),
            'business_name' => $this->text($spec['business_name'] ?? ''),
            'primary_goal' => $this->enum($spec['primary_goal'] ?? null, $allow['primary_goals'], $allow['default_primary_goal']),
            'brand' => $this->validateBrand($spec['brand'] ?? [], $allow),
            'hero' => $this->validateHero($spec['hero'] ?? [], $allow, $validAssetKeys),
            'sections' => $this->validateSections($spec['sections'] ?? [], $allow, $diagnosisIssueIds, $evidenceIds, $validAssetKeys),
            'footer' => $this->validateFooter($spec['footer'] ?? []),
            'rationale' => $this->validateRationale($spec['rationale'] ?? [], $diagnosisIssueIds),
            'design_personality' => $this->stringList($spec['design_personality'] ?? []),
            'visual_direction' => $this->text($spec['visual_direction'] ?? ''),
            'media_strategy' => $this->text($spec['media_strategy'] ?? ''),
            'layout_composition' => empty($allowedLayoutCompositions)
                ? $this->text($spec['layout_composition'] ?? '')
                : $this->enum($spec['layout_composition'] ?? null, $allowedLayoutCompositions, $allowedLayoutCompositions[0]),
            'conversion_hierarchy' => $this->stringList($spec['conversion_hierarchy'] ?? []),
            'asset_references' => $this->validateAssetReferences($spec['asset_references'] ?? [], $validAssetKeys),
        ];
    }

    /**
     * @param  string[]  $validAssetKeys
     */
    private function validateAssetReferences(array $references, array $validAssetKeys): array
    {
        $validSet = array_flip($validAssetKeys);
        $allowedRoles = ['hero_portrait', 'logo', 'section_image'];

        $out = [];
        foreach ($references as $ref) {
            if (! is_array($ref) || empty($ref['asset_id']) || ! isset($validSet[$ref['asset_id']])) {
                continue; // Unknown asset_id — never resolved, never rendered (AT-24).
            }

            $out[] = [
                'asset_id' => (string) $ref['asset_id'],
                'role' => $this->enum($ref['role'] ?? null, $allowedRoles, 'section_image'),
                'reason' => $this->text($ref['reason'] ?? ''),
            ];
        }

        return $out;
    }

    private function stringList(mixed $items): array
    {
        return array_values(array_filter(array_map(
            fn ($v) => is_string($v) ? trim(strip_tags($v)) : null,
            (array) $items
        )));
    }

    private function validateBrand(array $brand, array $allow): array
    {
        $colorTokens = is_array($brand['color_tokens'] ?? null) ? $brand['color_tokens'] : [];
        $resolvedColors = [];

        foreach ($allow['default_color_tokens'] as $slot => $default) {
            $resolvedColors[$slot] = $this->enum($colorTokens[$slot] ?? null, $allow['color_tokens'], $default);
        }

        return [
            'visual_tone' => $this->enum($brand['visual_tone'] ?? null, $allow['visual_tones'], $allow['default_visual_tone']),
            'color_tokens' => $resolvedColors,
            'typography' => $this->enum($brand['typography'] ?? null, $allow['typography'], $allow['default_typography']),
            'spacing_scale' => $this->enum($brand['spacing_scale'] ?? null, $allow['spacing_scales'], $allow['default_spacing_scale']),
            'button_style' => $this->enum($brand['button_style'] ?? null, $allow['button_styles'], $allow['default_button_style']),
        ];
    }

    /**
     * @param  string[]  $validAssetKeys
     */
    private function validateHero(array $hero, array $allow, array $validAssetKeys): array
    {
        return [
            'eyebrow' => $this->nullableText($hero['eyebrow'] ?? null),
            'headline' => $this->text($hero['headline'] ?? ''),
            'subheadline' => $this->text($hero['subheadline'] ?? ''),
            'primary_cta_label' => $this->text($hero['primary_cta_label'] ?? 'Get in touch'),
            'primary_cta_action' => $this->enum($hero['primary_cta_action'] ?? null, $allow['cta_actions'], $allow['default_cta_action']),
            'secondary_cta_label' => $this->nullableText($hero['secondary_cta_label'] ?? null),
            // Real per-run asset_key, never the old static placeholder
            // list — this is what lets the model name an actual image for
            // the hero instead of a meaningless constant the renderer
            // ignored anyway.
            'image_asset_key' => $this->enum($hero['image_asset_key'] ?? null, $validAssetKeys, null),
        ];
    }

    /**
     * @param  string[]  $validAssetKeys
     */
    private function validateSections(array $sections, array $allow, array $diagnosisIssueIds, array $evidenceIds, array $validAssetKeys): array
    {
        $out = [];
        $diagnosisSet = array_flip($diagnosisIssueIds);
        $evidenceSet = array_flip($evidenceIds);

        foreach ($sections as $section) {
            if (! is_array($section) || empty($section['section_key'])) {
                continue;
            }

            if (! in_array($section['section_key'], $allow['sections'], true)) {
                continue; // Unknown section identifiers are dropped, not guessed.
            }

            $out[] = [
                'section_key' => $section['section_key'],
                'heading' => $this->text($section['heading'] ?? ''),
                'body' => $this->nullableText($section['body'] ?? null),
                'items' => $this->validateItems($section['items'] ?? [], $allow, $validAssetKeys),
                'source_evidence_ids' => array_values(array_filter(
                    (array) ($section['source_evidence_ids'] ?? []),
                    fn ($id) => is_string($id) && isset($evidenceSet[$id])
                )),
                'addresses_issue_ids' => array_values(array_filter(
                    (array) ($section['addresses_issue_ids'] ?? []),
                    fn ($id) => is_string($id) && isset($diagnosisSet[$id])
                )),
            ];
        }

        return $out;
    }

    /**
     * @param  string[]  $validAssetKeys
     */
    private function validateItems(array $items, array $allow, array $validAssetKeys): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item) || empty($item['title'])) {
                continue;
            }
            $out[] = [
                'title' => $this->text($item['title']),
                'description' => $this->text($item['description'] ?? ''),
                'label' => $this->nullableText($item['label'] ?? null),
                // Real per-run asset_key (see validateHero) — a card only
                // gets an image when the model named a specific, actually
                // relevant asset for THIS item, never a round-robin leftover.
                'asset_key' => $this->enum($item['asset_key'] ?? null, $validAssetKeys, null),
                'icon' => $this->enum($item['icon'] ?? null, $allow['icons'], null),
            ];
        }

        return $out;
    }

    private function validateFooter(array $footer): array
    {
        return [
            'summary' => $this->nullableText($footer['summary'] ?? null),
            'contact_text' => $this->nullableText($footer['contact_text'] ?? null),
            'cta_label' => $this->nullableText($footer['cta_label'] ?? null),
        ];
    }

    private function validateRationale(array $rationale, array $diagnosisIssueIds): array
    {
        $diagnosisSet = array_flip($diagnosisIssueIds);
        $out = [];

        foreach ($rationale as $entry) {
            if (! is_array($entry) || empty($entry['decision'])) {
                continue;
            }
            $out[] = [
                'decision' => $this->text($entry['decision']),
                'addresses_issue_ids' => array_values(array_filter(
                    (array) ($entry['addresses_issue_ids'] ?? []),
                    fn ($id) => is_string($id) && isset($diagnosisSet[$id])
                )),
            ];
        }

        return $out;
    }

    /**
     * Strips any markup/scripting before plain-text use; Blade output
     * escaping is still applied at render time as a second layer.
     */
    private function text(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim(strip_tags($value));
    }

    private function nullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim(strip_tags($value));

        return $text === '' ? null : $text;
    }

    private function enum(mixed $value, array $allowed, ?string $default): ?string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
