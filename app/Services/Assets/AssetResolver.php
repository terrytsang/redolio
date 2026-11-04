<?php

namespace App\Services\Assets;

use App\Models\AnalysisRun;
use App\Models\WebsiteAsset;

/**
 * Resolves approved original assets to safe stored URLs for rendering.
 * Model output must reference asset_id, never a filesystem path
 * (asset_handling_rules) — this is the one place in the app allowed to
 * turn an asset_key into a public URL. Only assets explicitly
 * preserve/reuse-recommended are ever offered back for rendering.
 */
class AssetResolver
{
    public function heroPortraitUrl(AnalysisRun $run): ?string
    {
        return $this->bestMatch($run, ['profile_portrait', 'team_portrait'])?->url();
    }

    public function logoUrl(AnalysisRun $run): ?string
    {
        return $this->bestMatch($run, ['logo'])?->url();
    }

    public function byAssetKey(AnalysisRun $run, string $assetKey): ?WebsiteAsset
    {
        return $run->websiteAssets()->where('asset_key', $assetKey)->first();
    }

    private function bestMatch(AnalysisRun $run, array $assetTypes): ?WebsiteAsset
    {
        return $run->websiteAssets()
            ->whereIn('asset_type', $assetTypes)
            ->whereIn('recommended_action', ['preserve', 'reuse', 'needs_user_review'])
            ->whereNotNull('stored_path')
            ->orderByRaw("CASE preservation_priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->orderByDesc('relevance_score')
            ->first();
    }
}
