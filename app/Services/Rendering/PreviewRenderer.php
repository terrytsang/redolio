<?php

namespace App\Services\Rendering;

use App\Models\AnalysisRun;
use App\Services\Assets\AssetResolver;
use App\Services\Blueprint\BlueprintRenderer;
use RuntimeException;

/**
 * Dispatches a validated DesignSpec to its allowlisted Blade template.
 * template_key has already been constrained to the allowlist by
 * DesignSpecValidator; this map is a second, independent guard so an
 * unknown key can never resolve to an arbitrary view path.
 */
class PreviewRenderer
{
    private const TEMPLATE_VIEWS = [
        'smb_default' => 'components.preview.smb-default',
        'editorial' => 'components.preview.editorial',
        'minimal' => 'components.preview.minimal',
        'modern_corporate' => 'components.preview.modern-corporate',
        'creative_studio' => 'components.preview.creative-studio',
        'premium_luxury' => 'components.preview.premium-luxury',
        'commerce_energetic' => 'components.preview.commerce-energetic',
    ];

    public function __construct(
        private readonly AssetResolver $assetResolver,
        private readonly BlueprintRenderer $blueprintRenderer,
    ) {}

    /**
     * Full standalone HTML document for a spec carrying a validated v1.5
     * blueprint block, or null for legacy specs. Image URLs are resolved
     * server-side from the project's own assets — never taken from the spec.
     */
    public function renderBlueprintDocument(array $spec, ?AnalysisRun $run, bool $noindex = true, bool $publish = false): ?string
    {
        $v15 = $spec['v15'] ?? null;
        if (! is_array($v15)) {
            return null;
        }

        $urls = [];
        $alpha = []; // pictures with see-through areas: shown as they are, without a frame colour behind them
        if ($run) {
            foreach (array_merge($v15['pages'][0]['sections'], array_map(fn ($w) => ['props' => $w['config'] ?? []], $v15['pages'][0]['widgets'] ?? [])) as $section) {
                array_walk_recursive($section['props'], function ($v, $k) use (&$urls, &$alpha, $run) {
                    if ($k === 'asset_id' && ! isset($urls[$v]) && ($asset = $this->assetResolver->byAssetKey($run, $v)) && ($url = $asset->url())) {
                        $urls[$v] = $url;
                        $asset->has_alpha && $alpha[$v] = true;
                    }
                });
            }
        }

        return $this->blueprintRenderer->renderSpec($v15, $urls, ['noindex' => $noindex, 'publish' => $publish, 'alpha_assets' => $alpha]);
    }

    public function render(array $spec, ?AnalysisRun $run = null): string
    {
        // Blueprint-generated sites are standalone documents with their own
        // stylesheet; embed them sandboxed (no scripts, no forms, no popups) so they cannot affect the
        // app page. allow-same-origin only lets the page load its own self-hosted fonts.
        if (($document = $this->renderBlueprintDocument($spec, $run)) !== null) {
            // Rendered as a real 1440px-wide DESKTOP page and scaled down to the panel (see redoFitFrames()),
            // so the preview shows the desktop layout — not the cramped mobile layout a narrow panel would trigger.
            return '<div class="bp-frame-host" data-desktop-width="1440" style="position:relative;overflow:hidden;width:100%;height:80vh;min-height:520px">'
                .'<iframe title="Generated website preview" sandbox="allow-same-origin" loading="lazy" class="bp-frame" scrolling="yes" '
                .'style="position:absolute;left:0;top:0;width:1440px;height:2000px;border:0;transform-origin:0 0;background:#fff" srcdoc="'.e($document).'"></iframe></div>';
        }

        $view = self::TEMPLATE_VIEWS[$spec['template_key']] ?? null;

        if ($view === null) {
            throw new RuntimeException("No allowlisted view for template_key [{$spec['template_key']}].");
        }

        $html = view($view, [
            'spec' => $spec,
            'heroPortraitUrl' => $this->resolveAssetKeyUrl($run, $spec['hero']['image_asset_key'] ?? null)
                ?? $this->resolveFirstByRole($spec, $run, 'hero_portrait')
                ?? ($run ? $this->assetResolver->heroPortraitUrl($run) : null),
            'logoUrl' => $this->resolveFirstByRole($spec, $run, 'logo') ?? ($run ? $this->assetResolver->logoUrl($run) : null),
            // One entry per section item, in the same flattened order every
            // template already iterates — see resolveItemImageUrls().
            'sectionImageUrls' => $this->resolveItemImageUrls($spec, $run),
        ])->render();

        if ($run && $run->user?->hasLeadGenAccess()) {
            $html .= view('components.preview.lead-capture', ['run' => $run])->render();
        }

        return $html;
    }

    /**
     * Prefers the model's explicit asset_references (it has seen the whole
     * evidence pack and can pick the single best-fitting asset) over the
     * generic "best match" heuristic — but only ever resolves asset_id
     * values DesignSpecValidator already confirmed belong to this run.
     */
    private function resolveFirstByRole(array $spec, ?AnalysisRun $run, string $role): ?string
    {
        return $this->resolveAllByRole($spec, $run, $role)[0] ?? null;
    }

    /**
     * @return string[]
     */
    private function resolveAllByRole(array $spec, ?AnalysisRun $run, string $role): array
    {
        if (! $run) {
            return [];
        }

        $urls = [];
        foreach ($spec['asset_references'] ?? [] as $ref) {
            if (($ref['role'] ?? null) !== $role) {
                continue;
            }

            $url = $this->assetResolver->byAssetKey($run, $ref['asset_id'])?->url();
            if ($url) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    private function resolveAssetKeyUrl(?AnalysisRun $run, ?string $assetKey): ?string
    {
        if (! $run || ! $assetKey) {
            return null;
        }

        return $this->assetResolver->byAssetKey($run, $assetKey)?->url();
    }

    /**
     * Each item's OWN asset_key is resolved first — the fix for an image
     * landing on a card it has nothing to do with (a sequential "whatever
     * is next in the pool" assignment used to ignore which item the model
     * actually meant the image for). Items with no asset_key of their own
     * fall back to the next not-yet-claimed section_image from
     * asset_references, so a real image the model referenced only via
     * asset_references (not a specific item) still shows up somewhere.
     *
     * @return array<int, ?string> one entry per item, in template
     *                             iteration order (section, then item)
     */
    private function resolveItemImageUrls(array $spec, ?AnalysisRun $run): array
    {
        if (! $run) {
            return [];
        }

        $claimedAssetKeys = collect($spec['sections'] ?? [])
            ->flatMap(fn ($section) => $section['items'] ?? [])
            ->pluck('asset_key')
            ->filter()
            ->flip();

        $fallbackPool = collect($spec['asset_references'] ?? [])
            ->where('role', 'section_image')
            ->pluck('asset_id')
            ->reject(fn ($assetId) => isset($claimedAssetKeys[$assetId]))
            ->map(fn ($assetId) => $this->assetResolver->byAssetKey($run, $assetId)?->url())
            ->filter()
            ->values();

        $fallbackIndex = 0;
        $urls = [];

        foreach ($spec['sections'] ?? [] as $section) {
            foreach ($section['items'] ?? [] as $item) {
                $ownUrl = $this->resolveAssetKeyUrl($run, $item['asset_key'] ?? null);

                if ($ownUrl) {
                    $urls[] = $ownUrl;

                    continue;
                }

                $urls[] = $fallbackPool[$fallbackIndex] ?? null;
                $fallbackIndex++;
            }
        }

        return $urls;
    }
}
