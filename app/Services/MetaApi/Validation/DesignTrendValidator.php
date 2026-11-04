<?php

namespace App\Services\MetaApi\Validation;

/**
 * Validates Design Trend Intelligence output. Beyond the usual allowlist
 * checks, this enforces the copyright-safety constraint from the scouting
 * instruction: a pattern description must be a short, generalized idea,
 * not a long verbatim-looking passage that could be lifted straight from
 * a source page. There is no way to algorithmically verify "transformed
 * enough", so this is a blunt but meaningful backstop — reject anything
 * implausibly long for a generalized pattern note.
 */
class DesignTrendValidator
{
    private const MAX_DESCRIPTION_LENGTH = 400;

    private const MAX_TITLE_LENGTH = 120;

    public function validate(array $data): array
    {
        $allow = config('design_allowlist');
        $out = [];

        foreach ($data['patterns'] ?? [] as $pattern) {
            if (! is_array($pattern) || empty($pattern['title']) || empty($pattern['description'])) {
                continue;
            }

            $description = $this->text($pattern['description']);
            $title = $this->text($pattern['title']);

            if ($description === '' || mb_strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
                continue; // Too long to plausibly be a generalized pattern note.
            }
            if ($title === '' || mb_strlen($title) > self::MAX_TITLE_LENGTH) {
                continue;
            }

            $directions = array_values(array_intersect((array) ($pattern['applicable_directions'] ?? []), $allow['pattern_directions']));
            if (empty($directions)) {
                $directions = ['general'];
            }

            $domains = array_values(array_intersect((array) ($pattern['source_domains'] ?? []), $allow['pattern_source_domains']));

            $out[] = [
                'pattern_type' => in_array($pattern['pattern_type'] ?? null, $allow['pattern_types'], true) ? $pattern['pattern_type'] : 'component',
                'title' => $title,
                'description' => $description,
                'applicable_directions' => $directions,
                'source_domains' => $domains,
                'confidence' => in_array($pattern['confidence'] ?? null, ['low', 'medium', 'high'], true) ? $pattern['confidence'] : 'low',
            ];
        }

        return [
            'patterns' => $out,
            'sources_reachable' => array_values(array_intersect(
                array_map('strval', (array) ($data['sources_reachable'] ?? [])),
                $allow['pattern_source_domains']
            )),
        ];
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? trim(strip_tags($value)) : '';
    }
}
