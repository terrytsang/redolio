<?php

namespace App\Services\Blueprint;

/**
 * Applies a design direction's layout personality to a blueprint. A blueprint fixes the
 * information architecture (which sections, in what order); a direction may choose among
 * the registered VARIANTS of those sections (e.g. an editorial hero vs a split hero).
 * All variants of a component share one prop schema, so swapping is always content-safe.
 * A blueprint can lock a section whose variant is part of its design (variant_locked).
 */
class LayoutResolver
{
    public function __construct(private readonly SectionRegistry $registry) {}

    public function resolve(array $blueprint, array $direction): array
    {
        $prefs = $direction['layout_preferences'] ?? [];
        foreach ($blueprint['section_sequence'] as $i => $section) {
            if (! empty($section['variant_locked'])) {
                continue;
            }
            $preferred = $prefs[$section['component_id']] ?? null;
            if (is_string($preferred) && $this->registry->has($section['component_id'], $preferred)) {
                $blueprint['section_sequence'][$i]['variant_id'] = $preferred;
            }
        }

        return $blueprint;
    }
}
