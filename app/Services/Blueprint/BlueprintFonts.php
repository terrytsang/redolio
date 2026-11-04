<?php

namespace App\Services\Blueprint;

use Illuminate\Support\Facades\File;

/**
 * Resolves approved font ids to @font-face CSS from the Vite fonts manifest, and
 * lists the font files a page needs (so an export can copy them next to index.html).
 * Only woff2 is emitted — every browser REDO targets supports it.
 */
class BlueprintFonts
{
    public const STACKS = [
        'sans' => 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
        'serif' => 'Georgia, "Iowan Old Style", "Times New Roman", serif',
    ];

    private ?array $manifest = null;

    /** Ids a token profile may use: the approved list plus the two system kinds. */
    public function isApproved(string $id): bool
    {
        return isset(config('blueprint_fonts')[$id]) || isset(self::STACKS[$id]);
    }

    /** CSS font-family value (approved family first, then a robust system fallback). */
    public function stack(string $id): string
    {
        if (isset(self::STACKS[$id])) {
            return self::STACKS[$id];
        }
        $font = config('blueprint_fonts')[$id];

        return '"'.$font['family'].'", '.self::STACKS[$font['fallback']];
    }

    /** @param string[] $ids */
    public function css(array $ids, string $urlPrefix = '/build/assets/'): string
    {
        $manifest = $this->manifest();
        $out = '';
        foreach (array_unique($ids) as $id) {
            if (! isset(config('blueprint_fonts')[$id])) {
                continue;
            }
            foreach (preg_split('/\n\n/', $manifest['style']['familyStyles'][$id] ?? '') as $block) {
                if (str_contains($block, '@font-face') && str_contains($block, 'format("woff2")')) {
                    $block = preg_replace('/\s*unicode-range:[^;]+;/', '', $block);
                    $out .= str_replace('/build/assets/', $urlPrefix, trim($block))."\n";
                }
            }
        }

        return $out;
    }

    /** @param string[] $ids @return string[] woff2 paths relative to public/build/assets */
    public function files(array $ids): array
    {
        preg_match_all('#/build/assets/([^"\')\s]+\.woff2)#', $this->css($ids), $m);

        return array_values(array_unique($m[1]));
    }

    private function manifest(): array
    {
        if ($this->manifest === null) {
            $path = public_path('build/fonts-manifest.json');
            $this->manifest = File::exists($path) ? json_decode(File::get($path), true) : ['style' => ['familyStyles' => []]];
        }

        return $this->manifest;
    }
}
