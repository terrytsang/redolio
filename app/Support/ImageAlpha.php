<?php

namespace App\Support;

use GdImage;

/** Whether a picture really has see-through areas (not merely an alpha channel that is fully opaque, as most photos saved as PNG have). */
class ImageAlpha
{
    /** True when at least ~1.5% of a sampling grid is noticeably transparent. */
    public static function hasTransparency(GdImage $im): bool
    {
        $w = imagesx($im);
        $h = imagesy($im);
        if ($w < 1 || $h < 1) {
            return false;
        }
        $steps = 48;
        $see = 0;
        $n = 0;
        for ($i = 0; $i < $steps; $i++) {
            for ($j = 0; $j < $steps; $j++) {
                $alpha = (imagecolorat($im, (int) min($w - 1, ($i + .5) * $w / $steps), (int) min($h - 1, ($j + .5) * $h / $steps)) >> 24) & 0x7F; // 0 opaque … 127 clear
                $n++;
                $alpha >= 32 && $see++;
            }
        }

        return $see / $n >= 0.015;
    }

    /** Same test on a stored file (PNG / WebP), for pictures saved before this was recorded. */
    public static function fileHasTransparency(string $path): bool
    {
        $info = @getimagesize($path);
        $im = match ($info[2] ?? null) {
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if (! $im) {
            return false;
        }
        imagealphablending($im, false);

        return self::hasTransparency($im);
    }
}
