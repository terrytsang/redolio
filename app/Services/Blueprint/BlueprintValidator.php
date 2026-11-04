<?php

namespace App\Services\Blueprint;

use App\Services\Catalogue\DesignCatalogue;

class BlueprintValidator
{
    private const REQUIRED = [
        'id', 'name', 'description', 'category_ids', 'compatible_direction_ids', 'page_type',
        'section_sequence', 'responsive_rules', 'interaction_contracts', 'preview_assets', 'version', 'status',
    ];

    public function __construct(
        private readonly SectionRegistry $registry,
        private readonly DesignCatalogue $catalogue,
        private readonly ContentValidator $content,
    ) {}

    /** @return string[] */
    public function validateBlueprint(array $bp): array
    {
        $errors = [];
        foreach (self::REQUIRED as $field) {
            if (! array_key_exists($field, $bp)) {
                $errors[] = "missing field [{$field}]";
            }
        }
        if ($errors) {
            return $errors;
        }

        $categoryIds = array_column($this->catalogue->categories(), 'id');
        foreach ($bp['category_ids'] as $c) {
            in_array($c, $categoryIds, true) || $errors[] = "unknown category [{$c}]";
        }
        foreach ($bp['compatible_direction_ids'] as $d) {
            $this->catalogue->direction($d) || $errors[] = "unknown direction [{$d}]";
        }
        in_array($bp['status'], ['draft', 'ready', 'retired'], true) || $errors[] = "invalid status [{$bp['status']}]";

        $seen = [];
        foreach ($bp['section_sequence'] as $i => $s) {
            $id = $s['instance_id'] ?? "#{$i}";
            if (isset($seen[$id])) {
                $errors[] = "duplicate instance_id [{$id}]";
            }
            $seen[$id] = true;
            if (! $this->registry->has($s['component_id'] ?? '', $s['variant_id'] ?? '')) {
                $errors[] = "unregistered component [{$id}: ".($s['component_id'] ?? '?').':'.($s['variant_id'] ?? '?').']';
            }
            if (! in_array($s['fallback'] ?? null, ['required', 'omit'], true) || (($s['optional'] ?? false) !== (($s['fallback'] ?? null) === 'omit'))) {
                $errors[] = "section [{$id}] optional/fallback rule is inconsistent";
            }
        }

        return $errors;
    }

    /**
     * Validates content for a blueprint. Required sections must be present;
     * optional sections may be absent (omitted). Unknown instance_ids are rejected.
     *
     * @return string[]
     */
    public function validateContent(array $bp, array $content): array
    {
        $errors = [];
        $known = array_column($bp['section_sequence'], 'instance_id');
        foreach (array_diff(array_keys($content), $known) as $unknown) {
            $errors[] = "{$unknown}: content for a section not in this blueprint";
        }

        foreach ($bp['section_sequence'] as $s) {
            $data = $content[$s['instance_id']] ?? null;
            if ($data === null) {
                if (! $s['optional']) {
                    $errors[] = "{$s['instance_id']}: required section has no content";
                }

                continue;
            }
            $schema = $this->registry->props($s['component_id'], $s['variant_id']);
            array_push($errors, ...$this->content->validate($schema, $data, $s['instance_id']));
        }

        return $errors;
    }
}
