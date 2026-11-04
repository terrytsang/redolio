<?php

namespace App\Services\Blueprint;

/**
 * The hero layouts a person can pick on the layout-selection page, after choosing a design direction. A choice (plus, where it
 * makes sense, a left/right side) maps to one registered hero variant; it overrides the direction's own hero preference.
 */
final class HeroLayoutChoices
{
    /** key => [label, blurb, sides (null = no side choice), side label] */
    public const CHOICES = [
        'image-left' => ['label' => 'Picture left · content right', 'blurb' => 'A large picture on the left; headline, description and buttons on the right.', 'sides' => null],
        'image-right' => ['label' => 'Content left · picture right', 'blurb' => 'Headline, description and buttons on the left; a large picture on the right.', 'sides' => null],
        'banner' => ['label' => 'Full-width banner', 'blurb' => 'A wide banner across the top with your headline and buttons laid over it.', 'sides' => 'Content on the'],
        'fullscreen' => ['label' => 'Full-screen hero', 'blurb' => 'Fills the whole screen. The picture sits on one side so it never covers the headline; buttons sit right below the text.', 'sides' => 'Picture on the'],
        'bold' => ['label' => 'Big type', 'blurb' => 'A huge headline carries the page; description and buttons with icons sit along the bottom.', 'sides' => 'Buttons at the bottom'],
    ];

    public static function valid(?string $choice): bool
    {
        return $choice !== null && isset(self::CHOICES[$choice]);
    }

    public static function variant(?string $choice, ?string $side): ?string
    {
        $side = $side === 'right' ? 'right' : 'left';

        return match ($choice) {
            'image-left' => 'image-left',
            'image-right' => 'split',
            'banner' => "banner-{$side}",
            'fullscreen' => "fullscreen-{$side}",
            'bold' => "bold-{$side}",
            default => null,
        };
    }

    /** Apply a person's hero choice to a resolved blueprint (their explicit choice wins, even over a locked hero). */
    public static function apply(array $blueprint, array $preferences): array
    {
        $variant = self::variant($preferences['hero_layout'] ?? null, $preferences['hero_side'] ?? null);
        if (! $variant) {
            return $blueprint;
        }
        foreach ($blueprint['section_sequence'] as $i => $s) {
            if ($s['component_id'] === 'hero') {
                $blueprint['section_sequence'][$i]['variant_id'] = $variant;
            }
        }

        return $blueprint;
    }
}
