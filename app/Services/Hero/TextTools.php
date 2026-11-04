<?php

namespace App\Services\Hero;

/** Small, deterministic text helpers shared by the v1.6 services (no model involved). */
final class TextTools
{
    public const COUNTRIES = ['China', 'Malaysia', 'Thailand', 'Singapore', 'Indonesia', 'Vietnam', 'Philippines', 'India', 'Japan', 'Korea', 'Taiwan', 'Hong Kong', 'Australia', 'New Zealand', 'United States', 'USA', 'Canada', 'Mexico', 'Brazil', 'United Kingdom', 'UK', 'Germany', 'France', 'Italy', 'Spain', 'Netherlands', 'Poland', 'Turkey', 'United Arab Emirates', 'UAE', 'Saudi Arabia', 'Qatar', 'Egypt', 'South Africa', 'Nigeria', 'Kenya', 'Pakistan', 'Bangladesh', 'Sri Lanka', 'Cambodia', 'Myanmar', 'Asia', 'Europe', 'Africa', 'Americas', 'worldwide', 'globally'];

    private const STOP = ['the', 'and', 'for', 'with', 'that', 'this', 'from', 'your', 'our', 'are', 'you', 'who', 'all', 'their', 'have', 'been', 'into', 'more', 'than', 'also', 'will', 'can', 'its', 'has', 'was', 'were', 'but', 'not', 'any', 'one', 'out', 'use', 'such', 'they', 'them', 'these', 'those', 'which', 'when', 'what', 'where', 'while', 'about', 'over', 'across', 'only', 'each', 'both'];

    /** @return string[] lowercase alphanumeric tokens */
    public static function words(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\'’\-]*/u', mb_strtolower($text), $m);

        return $m[0];
    }

    public static function wordCount(string $text): int
    {
        return count(self::words($text));
    }

    /** Light stem so plural/singular compare equal ("plates" ~ "plate"). */
    public static function stem(string $word): string
    {
        $word = mb_strtolower($word);
        if (mb_strlen($word) > 4 && str_ends_with($word, 'ies')) {
            return mb_substr($word, 0, -3).'y';
        }
        if (mb_strlen($word) > 4 && str_ends_with($word, 's') && ! str_ends_with($word, 'ss')) {
            return mb_substr($word, 0, -1);
        }

        return $word;
    }

    /** @return string[] distinct stemmed content words (≥4 letters or any number) */
    public static function significant(string $text): array
    {
        $out = [];
        foreach (self::words($text) as $w) {
            if (in_array($w, self::STOP, true)) {
                continue;
            }
            if (mb_strlen($w) >= 4 || preg_match('/\d/', $w)) {
                $out[self::stem($w)] = true;
            }
        }

        return array_keys($out);
    }

    /** Fraction (0–1) of the needle's significant words found in the haystack. */
    public static function coverage(string $needle, string $haystack): float
    {
        $n = self::significant($needle);
        if ($n === []) {
            return 0.0;
        }
        $h = array_flip(self::significant($haystack));

        return count(array_filter($n, fn ($w) => isset($h[$w]))) / count($n);
    }

    /** Jaccard overlap of significant words. */
    public static function overlap(string $a, string $b): float
    {
        $x = self::significant($a);
        $y = self::significant($b);
        if ($x === [] || $y === []) {
            return 0.0;
        }

        return count(array_intersect($x, $y)) / count(array_unique(array_merge($x, $y)));
    }

    /** @return string[] */
    public static function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+|\n+/u', trim($text)) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($s) => mb_strlen($s) > 2));
    }

    /** Distinct numeric tokens with thousands separators removed ("10,000" → "10000"). @return string[] */
    public static function numbers(string $text): array
    {
        preg_match_all('/(?<![\p{L}\d])\d[\d,]*(?:\.\d+)?/u', $text, $m);

        return array_values(array_unique(array_map(fn ($n) => rtrim(str_replace(',', '', $n), '.'), $m[0])));
    }

    public static function fit(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['<', '>'], ' ', $text))));

        return mb_strlen($text) <= $max ? $text : rtrim(mb_substr($text, 0, $max - 1), " ,.;:-").'…';
    }
}
