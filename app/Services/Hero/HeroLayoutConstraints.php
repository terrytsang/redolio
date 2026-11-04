<?php

namespace App\Services\Hero;

/** Per-hero-variant text constraints (config/hero_layout.php). Estimates are preflight only; the browser is authoritative. */
final class HeroLayoutConstraints
{
    public static function variant(?string $variantId): string
    {
        return isset(config('hero_layout.variants')[$variantId]) ? $variantId : config('hero_layout.default_variant');
    }

    /** @return array{family: string, h1_lines: array<string,int>, description_lines: array<string,int>, chars_per_line: array<string,int>} */
    public static function for(?string $variantId): array
    {
        return config('hero_layout.variants')[self::variant($variantId)];
    }

    public static function preferredH1Lines(?string $variantId, string $viewport): int
    {
        return self::for($variantId)['h1_lines'][$viewport];
    }

    public static function preferredDescriptionLines(?string $variantId, string $viewport): int
    {
        return self::for($variantId)['description_lines'][$viewport];
    }

    /** Rough line count from character budget per line (word-aware). */
    public static function estimateLines(string $text, ?string $variantId, string $viewport, float $descriptionFactor = 1.0): int
    {
        $width = max(8, (int) round(self::for($variantId)['chars_per_line'][$viewport] * $descriptionFactor));
        $lines = 1;
        $cur = 0;
        foreach (preg_split('/\s+/', trim($text)) ?: [] as $w) {
            $len = mb_strlen($w);
            if ($cur > 0 && $cur + 1 + $len > $width) {
                $lines++;
                $cur = $len;
            } else {
                $cur += ($cur > 0 ? 1 : 0) + $len;
            }
        }

        return $lines;
    }

    public static function copyRule(?string $variantId): string
    {
        return config('hero_layout.copy_rules')[self::variant($variantId)] ?? '';
    }
}
