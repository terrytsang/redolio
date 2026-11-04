<?php

namespace App\Services\MetaApi\Validation;

/**
 * Validates the design-direction ranking response. Per the PRD's
 * must_follow rule, only eligible (is_selectable=true) direction_key
 * values are ever accepted — an unknown or ineligible key is dropped,
 * never rendered as a recommendation (AT-26/AT-32).
 */
class DesignDirectionRankingValidator
{
    /**
     * @param  string[]  $eligibleDirectionKeys
     * @return array<int, array{direction_key: string, rank: int, score: float, rationale: string, is_recommended: bool}>
     */
    public function validate(array $data, array $eligibleDirectionKeys): array
    {
        $eligibleSet = array_flip($eligibleDirectionKeys);
        $recommendedKeys = array_values(array_intersect(
            array_unique((array) ($data['recommended_direction_keys'] ?? [])),
            $eligibleDirectionKeys,
        ));
        $recommendedKeys = array_slice($recommendedKeys, 0, 3);

        $out = [];
        foreach (($data['recommendations'] ?? []) as $entry) {
            if (! is_array($entry) || empty($entry['direction_key']) || ! isset($eligibleSet[$entry['direction_key']])) {
                continue;
            }

            $out[$entry['direction_key']] = [
                'direction_key' => $entry['direction_key'],
                'rank' => max(1, (int) ($entry['rank'] ?? 99)),
                'score' => (float) ($entry['score'] ?? 0),
                'rationale' => is_string($entry['rationale'] ?? null) ? trim(strip_tags($entry['rationale'])) : '',
                'is_recommended' => false,
            ];
        }

        // Guarantee every eligible direction appears, even if the model
        // omitted one — a missing entry is not the same as "ranked last".
        foreach ($eligibleDirectionKeys as $key) {
            if (! isset($out[$key])) {
                $out[$key] = [
                    'direction_key' => $key,
                    'rank' => 99,
                    'score' => 0.0,
                    'rationale' => 'No rationale returned for this direction.',
                    'is_recommended' => false,
                ];
            }
        }

        $out = array_values($out);
        usort($out, fn ($a, $b) => $a['rank'] <=> $b['rank']);

        if (empty($recommendedKeys)) {
            $recommendedKeys = array_map(fn ($e) => $e['direction_key'], array_slice($out, 0, 3));
        }
        $recommendedSet = array_flip($recommendedKeys);

        foreach ($out as &$entry) {
            $entry['is_recommended'] = isset($recommendedSet[$entry['direction_key']]);
        }

        return $out;
    }
}
