<?php

namespace App\Services\MetaApi\Validation;

/**
 * Validates the combined visual-profile + asset-classification response
 * (PRD upgrade v1.2 website_intelligence_engine.visual_profile +
 * asset_preservation_intelligence.model_classification_fields). Per AT-24,
 * any asset_classifications entry referencing an asset_id that isn't in
 * the current run's asset inventory is dropped, never guessed or kept.
 */
class WebsiteVisualIntelligenceValidator
{
    private const CONFIDENCES = ['low', 'medium', 'high'];

    private const RECOMMENDED_ACTIONS = ['preserve', 'reuse', 'reposition', 'crop', 'replace', 'omit', 'needs_user_review'];

    /**
     * @param  string[]  $validAssetKeys
     */
    public function validate(array $data, array $validAssetKeys): array
    {
        $profile = is_array($data['visual_profile'] ?? null) ? $data['visual_profile'] : [];
        $validAssetSet = array_flip($validAssetKeys);

        $classifications = [];
        foreach (($data['asset_classifications'] ?? []) as $entry) {
            if (! is_array($entry) || empty($entry['asset_id']) || ! isset($validAssetSet[$entry['asset_id']])) {
                continue; // Unknown or missing asset_id — never rendered, never guessed (AT-24).
            }

            $classifications[] = [
                'asset_id' => (string) $entry['asset_id'],
                'asset_type' => $this->text($entry['asset_type'] ?? ''),
                'business_relevance' => $this->enum($entry['business_relevance'] ?? null, self::CONFIDENCES, 'low'),
                'visual_relevance' => $this->enum($entry['visual_relevance'] ?? null, self::CONFIDENCES, 'low'),
                'recommended_role' => $this->text($entry['recommended_role'] ?? ''),
                'preservation_priority' => $this->enum($entry['preservation_priority'] ?? null, self::CONFIDENCES, 'low'),
                'recommended_action' => $this->enum($entry['recommended_action'] ?? null, self::RECOMMENDED_ACTIONS, 'needs_user_review'),
                'reason' => $this->text($entry['reason'] ?? ''),
                'confidence' => $this->enum($entry['confidence'] ?? null, self::CONFIDENCES, 'low'),
            ];
        }

        return [
            'visual_profile' => [
                'detected_colors' => $this->colors($profile['detected_colors'] ?? []),
                'typography_clues' => $this->typography($profile['typography_clues'] ?? []),
                'layout_patterns' => $this->stringList($profile['layout_patterns'] ?? []),
                'image_usage' => $this->imageUsage($profile['image_usage'] ?? []),
                'brand_personality' => $this->stringList($profile['brand_personality'] ?? []),
                'design_strengths' => $this->stringList($profile['design_strengths'] ?? []),
                'design_weaknesses' => $this->stringList($profile['design_weaknesses'] ?? []),
                'unknowns' => $this->stringList($profile['unknowns'] ?? []),
            ],
            'asset_classifications' => $classifications,
        ];
    }

    private function colors(mixed $items): array
    {
        $out = [];
        foreach ((array) $items as $item) {
            if (! is_array($item) || empty($item['hex']) || ! preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $item['hex'])) {
                continue;
            }

            $out[] = [
                'hex' => strtoupper((string) $item['hex']),
                'role' => $this->text($item['role'] ?? ''),
                'source' => in_array($item['source'] ?? null, ['css_declared', 'model_inference'], true) ? $item['source'] : 'model_inference',
                'confidence' => $this->enum($item['confidence'] ?? null, self::CONFIDENCES, 'low'),
            ];
        }

        return $out;
    }

    private function typography(mixed $items): array
    {
        $out = [];
        foreach ((array) $items as $item) {
            if (! is_array($item) || empty($item['family'])) {
                continue;
            }

            $out[] = [
                'role' => $this->text($item['role'] ?? ''),
                'family' => $this->text($item['family']),
                'confidence' => $this->enum($item['confidence'] ?? null, self::CONFIDENCES, 'low'),
            ];
        }

        return $out;
    }

    private function imageUsage(mixed $items): array
    {
        $out = [];
        foreach ((array) $items as $item) {
            if (! is_array($item) || empty($item['role'])) {
                continue;
            }

            $out[] = [
                'role' => $this->text($item['role']),
                'count' => max(0, (int) ($item['count'] ?? 0)),
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

    private function text(mixed $value): string
    {
        return is_string($value) ? trim(strip_tags($value)) : '';
    }

    private function enum(mixed $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
