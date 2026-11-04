<?php

namespace App\Services\Publishing;

use App\Models\DesignSpec;
use DOMDocument;
use Illuminate\Support\Facades\File;

/**
 * Minimum validation from production_artifact_pipeline before an artifact
 * can be called production-ready (AT-39): required pages exist, HTML
 * parses, no broken local asset references, required hero/CTA present.
 */
class ArtifactValidator
{
    /**
     * @return array{valid: bool, errors: string[]}
     */
    public function validate(string $destinationDir, DesignSpec $spec): array
    {
        $errors = [];

        $indexPath = "{$destinationDir}/index.html";
        if (! File::exists($indexPath)) {
            return ['valid' => false, 'errors' => ['index.html was not generated.']];
        }

        $html = File::get($indexPath);
        if (trim($html) === '') {
            $errors[] = 'Generated index.html is empty.';
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $parsed = $dom->loadHTML($html);
        $xmlErrors = libxml_get_errors();
        libxml_clear_errors();
        if (! $parsed) {
            $errors[] = 'Generated HTML failed to parse.';
        }
        $fatalParseErrors = array_filter($xmlErrors, fn ($e) => $e->level === LIBXML_ERR_FATAL);
        if (! empty($fatalParseErrors)) {
            $errors[] = 'Generated HTML has fatal parse errors.';
        }

        $hero = $spec->design_spec_json['hero'] ?? [];
        if (empty($hero['headline'])) {
            $errors[] = 'DesignSpec has no hero headline.';
        }
        if (empty($hero['primary_cta_label'])) {
            $errors[] = 'DesignSpec has no primary call to action.';
        }

        // Blueprint sites carry their stylesheet inline; legacy exports link a compiled CSS file.
        if (! isset($spec->design_spec_json['v15']) && ! File::exists("{$destinationDir}/assets/app.css")) {
            $errors[] = 'Compiled stylesheet is missing from the package.';
        }

        // Every local asset src/href the HTML references must actually exist
        // in the package — no broken references (minimum_validation).
        if (preg_match_all('/(?:src|href)="(assets\/[^"]+)"/', $html, $matches)) {
            foreach ($matches[1] as $relativePath) {
                if (! File::exists("{$destinationDir}/{$relativePath}")) {
                    $errors[] = "Referenced asset is missing from the package: {$relativePath}";
                }
            }
        }

        // Fonts (and anything else) referenced from inline CSS must be packaged as well.
        if (preg_match_all('/url\(["\']?(assets\/[^)"\']+)["\']?\)/', $html, $cssRefs)) {
            foreach (array_unique($cssRefs[1]) as $relativePath) {
                if (! File::exists("{$destinationDir}/{$relativePath}")) {
                    $errors[] = "Referenced font/asset is missing from the package: {$relativePath}";
                }
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }
}
