<?php

namespace App\Services\VisualQa;

use Illuminate\Support\Facades\File;
use RuntimeException;

/** Remote browsers cannot read this server's files, so a page's local images are embedded in its HTML as data: URIs. */
trait InlinesLocalAssets
{
    private const MAX_HTML_BYTES = 12_000_000;

    protected function inlineLocalAssets(string $htmlPath): string
    {
        $dir = dirname($htmlPath);
        $html = File::get($htmlPath);
        $cache = [];
        // Every reference to a bundled file — src="assets/x.png", srcset, and CSS url("assets/fonts/x.woff2") for fonts and
        // backgrounds — becomes a data: URI. Without the fonts a remote render falls back to a default font and the layout shifts.
        $html = preg_replace_callback('#(?<=["\'(,\s])assets/[^"\')\s,>]+#', function ($m) use ($dir, &$cache) {
            $ref = $m[0];
            $file = "{$dir}/".strtok($ref, '?#');
            if (! is_file($file)) {
                return $ref;
            }

            return $cache[$ref] ??= 'data:'.$this->mimeFor($file).';base64,'.base64_encode(File::get($file));
        }, $html);

        if (strlen($html) > self::MAX_HTML_BYTES) {
            throw new RuntimeException('The page is too large to send to the remote browser.');
        }

        return $html;
    }

    private function mimeFor(string $file): string
    {
        return ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
            'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf', 'otf' => 'font/otf', 'css' => 'text/css', 'ico' => 'image/x-icon'][strtolower(pathinfo($file, PATHINFO_EXTENSION))]
            ?? (File::mimeType($file) ?: 'application/octet-stream');
    }
}
