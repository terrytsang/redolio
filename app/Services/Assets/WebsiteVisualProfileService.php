<?php

namespace App\Services\Assets;

use App\DTO\Crawler\SiteSnapshotDto;
use App\Models\AnalysisRun;
use App\Models\WebsiteAsset;
use App\Models\WebsiteDesignProfile;
use App\Services\MetaApi\EvidencePackFormatter;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\MetaModelClient;
use App\Services\MetaApi\Validation\WebsiteVisualIntelligenceValidator;
use App\Services\Orchestration\ModelCallLogger;
use Illuminate\Support\Collection;

/**
 * Builds the deterministic visual-clue baseline, calls the Meta
 * website_visual_intelligence stage to interpret it alongside the asset
 * inventory, validates the response, and persists both the design profile
 * and any asset reclassification. Best-effort by design (called from
 * AnalyzeWebsiteAction inside a try/catch) — the core DNA/diagnosis/
 * redesign pipeline must work even if this never runs.
 */
class WebsiteVisualProfileService
{
    public function __construct(
        private readonly MetaModelClient $metaClient,
        private readonly EvidencePackFormatter $evidencePackFormatter,
        private readonly WebsiteVisualIntelligenceValidator $validator,
        private readonly ModelCallLogger $modelCallLogger,
    ) {}

    public function build(AnalysisRun $run, SiteSnapshotDto $snapshot): ?WebsiteDesignProfile
    {
        $assets = $run->websiteAssets()->get();

        if ($assets->isEmpty()) {
            return null; // Nothing to interpret or classify.
        }

        $evidencePack = $this->evidencePackFormatter->build(
            $snapshot->toSnapshotJson(),
            [] // Visual intelligence reasons over page structure, not evidence excerpts.
        );

        $deterministicClues = $this->deterministicClues($snapshot);
        $assetInventory = $assets->map(fn (WebsiteAsset $a) => [
            'asset_id' => $a->asset_key,
            'asset_type' => $a->asset_type,
            'dom_context' => $a->dom_context,
            'alt_text' => $a->alt_text,
            'nearby_heading' => $a->nearby_heading,
            'width' => $a->width,
            'height' => $a->height,
            'source_page_url' => $a->source_page_url,
        ])->all();

        $requestHash = hash('sha256', (string) json_encode([
            'stage' => 'website_visual_intelligence',
            'clues' => $deterministicClues,
            'assets' => $assetInventory,
        ]));

        try {
            $response = $this->metaClient->generateVisualIntelligence(
                $evidencePack,
                $deterministicClues,
                $assetInventory,
                ['run_id' => $run->public_id]
            );
            $this->modelCallLogger->logSuccess($run, 'website_visual_intelligence', $requestHash, $response);
        } catch (MetaApiException $e) {
            $this->modelCallLogger->logFailure($run, 'website_visual_intelligence', $requestHash, $e->errorCode);

            throw $e;
        }

        $validated = $this->validator->validate($response->data, $assets->pluck('asset_key')->all());

        $this->applyAssetClassifications($assets, $validated['asset_classifications']);

        return WebsiteDesignProfile::create([
            'analysis_run_id' => $run->id,
            'profile_json' => $validated['visual_profile'],
            'profile_version' => 1,
            'model_identifier' => $response->modelIdentifier,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array{theme_colors: array<int, array{page_url: string, hex: string}>, favicon_present: bool, page_count: int}
     */
    private function deterministicClues(SiteSnapshotDto $snapshot): array
    {
        $themeColors = [];
        $faviconPresent = false;

        foreach ($snapshot->pages as $page) {
            if ($page->themeColor) {
                $themeColors[] = ['page_url' => $page->url, 'hex' => $page->themeColor];
            }
            if ($page->faviconUrl) {
                $faviconPresent = true;
            }
        }

        return [
            'theme_colors' => $themeColors,
            'favicon_present' => $faviconPresent,
            'page_count' => count($snapshot->pages),
        ];
    }

    /**
     * @param  Collection<int, WebsiteAsset>  $assets
     * @param  array<int, array{asset_id: string, asset_type: string, recommended_role: string, preservation_priority: string, recommended_action: string}>  $classifications
     */
    private function applyAssetClassifications($assets, array $classifications): void
    {
        $byKey = $assets->keyBy('asset_key');

        foreach ($classifications as $classification) {
            $asset = $byKey->get($classification['asset_id']);

            if (! $asset) {
                continue;
            }

            $asset->update([
                'detected_role' => $classification['recommended_role'],
                'preservation_priority' => $classification['preservation_priority'],
                'recommended_action' => $classification['recommended_action'],
                'recommended_usage' => $classification['reason'],
            ]);
        }
    }
}
