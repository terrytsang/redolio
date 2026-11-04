<?php

namespace App\Services\Publishing;

use App\Models\AnalysisRun;
use App\Models\DesignSpec;
use App\Services\Blueprint\BlueprintRenderer;
use App\Services\Rendering\PreviewRenderer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Converts an approved DesignSpec into a real, standalone static website
 * package: the same allowlisted Blade render used for the in-app preview,
 * wrapped in a full HTML document, with the compiled Tailwind CSS and any
 * real preserved assets (logo/portrait/portfolio) copied in and localized
 * to relative paths so the package is servable from anywhere (production_
 * artifact_pipeline.generation_rules — never execute model-generated code,
 * compile only from trusted templates and validated structured data).
 */
class ProductionArtifactGenerator
{
    public function __construct(private readonly PreviewRenderer $renderer) {}

    /**
     * @return array{index_html_bytes: int, asset_count: int, files: string[]}
     */
    public function generate(AnalysisRun $run, DesignSpec $spec, string $destinationDir, bool $includeProvenance = true): array
    {
        File::ensureDirectoryExists($destinationDir);
        File::ensureDirectoryExists("{$destinationDir}/assets");

        $businessName = $spec->design_spec_json['business_name'] ?? $run->normalized_url;

        // Blueprint sites already are a complete, self-contained document (inline tokens + styles).
        $document = $this->renderer->renderBlueprintDocument($spec->design_spec_json, $run, noindex: false, publish: true);
        if ($document !== null) {
            $html = $this->localizeStorageAssets($document, "{$destinationDir}/assets");
            $html = $this->bundleFonts($html, "{$destinationDir}/assets/fonts");
            $this->writeWidgetFiles($spec->design_spec_json['v15'], $destinationDir);
        } else {
            $fragment = $this->renderer->render($spec->design_spec_json, $run);
            $fragment = $this->localizeStorageAssets($fragment, "{$destinationDir}/assets");
            $this->copyCompiledCss("{$destinationDir}/assets");
            $html = $this->wrapDocument($businessName, $fragment);
        }
        File::put("{$destinationDir}/index.html", $html);

        // The analysis behind a redesign belongs to the customer's download, never to the public website.
        if ($includeProvenance) {
            File::ensureDirectoryExists("{$destinationDir}/design");
            File::ensureDirectoryExists("{$destinationDir}/redo");
            File::put("{$destinationDir}/design/design-spec.json", json_encode($spec->design_spec_json, JSON_PRETTY_PRINT));
            File::put("{$destinationDir}/redo/website-dna.json", json_encode($run->currentWebsiteDna()?->dna_json ?? [], JSON_PRETTY_PRINT));
            File::put("{$destinationDir}/redo/diagnosis.json", json_encode($run->currentDiagnosisReport()?->issues_json ?? [], JSON_PRETTY_PRINT));
            File::put("{$destinationDir}/README.md", $this->readme($businessName, $run));
        }

        $files = collect(File::allFiles($destinationDir))->map(fn ($f) => $f->getRelativePathname())->values()->all();

        return [
            'index_html_bytes' => File::size("{$destinationDir}/index.html"),
            'asset_count' => count(File::files("{$destinationDir}/assets")),
            'files' => $files,
        ];
    }

    /** Add-on widgets: the small enhancement script (carousel, analytics) and each Embed's isolated document. */
    private function writeWidgetFiles(array $v15, string $dir): void
    {
        $files = app(BlueprintRenderer::class)->widgetFiles($v15);
        if ($files['script']) {
            File::copy(resource_path('widgets/redo-widgets.js'), "{$dir}/assets/redo-widgets.js");
        }
        if ($files['embeds']) {
            File::ensureDirectoryExists("{$dir}/embeds");
            foreach ($files['embeds'] as $id => $html) {
                File::put("{$dir}/embeds/{$id}.html", $html);
            }
        }
    }

    /**
     * Blueprint pages reference their approved self-hosted fonts at /build/assets/*.woff2.
     * A standalone package cannot reach the app, so each font is copied next to the page and relinked.
     */
    /**
     * A complete standalone page for a validated SAMPLE spec rendered through a legacy template
     * (used to screenshot custom, admin-created directions). Same renderer, same compiled CSS.
     *
     * @param  array<string, string>  $cssVars  e.g. ['--redo-accent' => '#112233'] to tint a shared template
     */
    public function generateSample(array $spec, string $destinationDir, array $cssVars = []): void
    {
        File::ensureDirectoryExists("{$destinationDir}/assets");
        $fragment = $this->renderer->render($spec, null);
        $this->copyCompiledCss("{$destinationDir}/assets");
        $style = collect($cssVars)->filter(fn ($v, $k) => preg_match('/^--redo-[a-z-]+$/', $k) && preg_match('/^#[0-9A-Fa-f]{3,8}$/', $v))->map(fn ($v, $k) => "{$k}: {$v};")->implode(' ');
        File::put("{$destinationDir}/index.html", $this->wrapDocument($spec['business_name'] ?? 'Sample', $fragment, $style));
    }

    public function bundleFonts(string $html, string $fontsDir): string
    {
        return preg_replace_callback('#/build/assets/([A-Za-z0-9._-]+\.woff2)#', function (array $m) use ($fontsDir) {
            $source = public_path("build/assets/{$m[1]}");
            if (! File::exists($source)) {
                return $m[0];
            }
            File::ensureDirectoryExists($fontsDir);
            File::copy($source, "{$fontsDir}/{$m[1]}");

            return "assets/fonts/{$m[1]}";
        }, $html);
    }

    /**
     * Rewrites every reference to our own /storage/... asset URL to a
     * package-relative assets/ path and copies the real file alongside —
     * the package must work as a standalone static site, never hotlink
     * back to the REDO application (code_export.download_rules).
     */
    private function localizeStorageAssets(string $html, string $assetsDir): string
    {
        $base = rtrim(Storage::disk('public')->url(''), '/');

        return preg_replace_callback(
            '#'.preg_quote($base, '#').'/([^"\'\s]+)#',
            function (array $m) use ($assetsDir) {
                $storedPath = $m[1];
                $sourcePath = Storage::disk('public')->path($storedPath);

                if (! File::exists($sourcePath)) {
                    return $m[0];
                }

                $localName = substr(md5($storedPath), 0, 8).'-'.basename($storedPath);
                File::copy($sourcePath, "{$assetsDir}/{$localName}");

                return "assets/{$localName}";
            },
            $html,
        );
    }

    /**
     * The compiled Tailwind CSS already contains every utility class used
     * across the app's Blade templates (including the preview components),
     * since Tailwind v4 content-scans the project at build time — so the
     * exported package needs no separate CSS build step.
     */
    private function copyCompiledCss(string $assetsDir): void
    {
        $manifestPath = public_path('build/manifest.json');

        if (! File::exists($manifestPath)) {
            return;
        }

        $manifest = json_decode(File::get($manifestPath), true);
        $cssFile = $manifest['resources/css/app.css']['file'] ?? null;

        if (! $cssFile || ! File::exists(public_path("build/{$cssFile}"))) {
            return;
        }

        $css = File::get(public_path("build/{$cssFile}"));
        $css = str_replace('/build/assets/', './', $css);
        $css .= $this->containerQueriesAsViewportQueries($css);
        File::put("{$assetsDir}/app.css", $css);

        foreach (File::files(public_path('build/assets')) as $file) {
            if (in_array($file->getExtension(), ['woff', 'woff2'], true)) {
                File::copy($file->getPathname(), "{$assetsDir}/{$file->getFilename()}");
            }
        }
    }

    /**
     * The preview templates use @container queries (@lg:, @5xl: etc.) so
     * they respond correctly to the width of the split preview pane
     * in-app. A standalone exported page has no meaningful "container" —
     * its root element is the whole page — so duplicate every container
     * query rule as an identical standard @media rule, making the exact
     * same classes respond to real viewport width too. No template
     * changes needed, and this stays correct as templates evolve.
     */
    private function containerQueriesAsViewportQueries(string $css): string
    {
        $extra = '';

        // Only genuine @container at-rules have a parenthesized condition
        // right after the keyword — the escaped class selector for the
        // "@container" utility itself (.\@container{...}) contains the same
        // substring but with no "(", so this pattern can't match it.
        if (! preg_match_all('/@container\s*(\([^)]+\))\s*\{/', $css, $matches, PREG_OFFSET_CAPTURE)) {
            return $extra;
        }

        foreach ($matches[0] as $i => [$fullMatch, $matchPos]) {
            $condition = $matches[1][$i][0];
            $openBrace = $matchPos + strlen($fullMatch) - 1;

            $depth = 0;
            $end = $openBrace;
            for ($pos = $openBrace; $pos < strlen($css); $pos++) {
                if ($css[$pos] === '{') {
                    $depth++;
                } elseif ($css[$pos] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $end = $pos;
                        break;
                    }
                }
            }

            $body = substr($css, $openBrace, $end - $openBrace + 1);
            $extra .= "@media {$condition} {$body}\n";
        }

        return $extra;
    }

    private function wrapDocument(string $businessName, string $fragment, string $bodyStyle = ''): string
    {
        $title = e($businessName);
        $styleAttr = $bodyStyle !== '' ? ' style="'.e($bodyStyle).'"' : '';

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$title}</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body{$styleAttr}>
{$fragment}
</body>
</html>
HTML;
    }

    private function readme(string $businessName, AnalysisRun $run): string
    {
        $name = Str::of($businessName)->trim();
        $supportEmail = config('redo.support_email');

        return <<<MD
# {$name} — REDOLIO production export

Generated by REDOLIO from an approved redesign of {$run->normalized_url}.

## Deploy

This is a static website — `index.html` plus `assets/`. Serve it with any static
host (Netlify, Vercel, S3 + CloudFront, GitHub Pages, nginx, etc.) by pointing
the web root at this folder. No build step or server runtime is required.

### On ordinary web hosting (cPanel, Hostinger, GoDaddy, Bluehost…)

1. Unzip this package. You will see `index.html` and an `assets` folder.
2. Log in to your hosting account and open **File Manager** (or connect with an FTP app such as FileZilla).
3. Open your website's main folder — usually `public_html` (sometimes `www` or `htdocs`).
4. Upload **everything inside** this folder (`index.html` and `assets/`) straight into it — or upload the zip and use **Extract**.
5. If an old `index.html`, `index.php` or "coming soon" page is there, rename or delete it.
6. Open your domain and hard-refresh (Ctrl+Shift+R, or ⌘+Shift+R on a Mac).

Need a hand? Email {$supportEmail}.

## Contents

- `index.html` — the production page.
- `assets/` — compiled CSS, fonts, and your real preserved images/logo.
- `design/design-spec.json` — the validated DesignSpec this page was rendered from.
- `redo/website-dna.json`, `redo/diagnosis.json` — the REDO analysis that informed this redesign.

## Notes

- This package is generated from REDOLIO's own validated templates and your approved
  design — it contains no AI-generated executable code.
- Review asset licensing before using any preserved third-party imagery in production.
MD;
    }
}
