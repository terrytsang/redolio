<?php

namespace App\Services\Rendering;

/**
 * Deterministic half of the preview_quality_gate (PRD upgrade v1.2).
 * Runs against an already-validated DesignSpec — DesignSpecValidator has
 * already guaranteed every template/section/token/CTA-action/asset_id is
 * allowlisted or known-real, so this layer checks for the failure mode
 * validation can't catch: a structurally valid but *empty* or incomplete
 * spec (exactly what slipped through before minItems/section_key enums
 * were added — see the empty-sections incident). The qualitative review
 * half (visual hierarchy, brand fit) stays a human/manual judgment call
 * for P0; no browser rendering is available to automate it.
 */
class PreviewQualityEvaluator
{
    /**
     * @param  string[]  $validAssetKeys
     * @return array{status: string, critical: bool, findings: string[]}
     */
    public function evaluate(array $spec, array $validAssetKeys): array
    {
        $findings = [];
        $critical = false;

        if (trim($spec['hero']['headline'] ?? '') === '') {
            $findings[] = 'Hero headline is empty.';
            $critical = true;
        }

        if (trim($spec['hero']['primary_cta_label'] ?? '') === '') {
            $findings[] = 'Primary CTA label is empty.';
            $critical = true;
        }

        $sections = $spec['sections'] ?? [];
        if (count($sections) < 1) {
            $findings[] = 'No sections were generated — the preview body would be empty.';
            $critical = true;
        } elseif (! collect($sections)->contains(fn ($s) => trim($s['heading'] ?? '') !== '')) {
            $findings[] = 'Every section is missing a heading.';
            $critical = true;
        }

        foreach ($spec['asset_references'] ?? [] as $ref) {
            if (! in_array($ref['asset_id'] ?? null, $validAssetKeys, true)) {
                // Should be unreachable — DesignSpecValidator already drops
                // these — but a quality gate that trusts its input isn't one.
                $findings[] = "asset_references contains an asset_id outside this run's inventory.";
                $critical = true;
            }
        }

        if (trim($spec['visual_direction'] ?? '') === '') {
            $findings[] = 'No visual_direction rationale was provided.';
        }
        if (empty($spec['design_personality'] ?? [])) {
            $findings[] = 'No design_personality traits were provided.';
        }

        $status = match (true) {
            $critical => 'needs_attention',
            ! empty($findings) => 'maintained',
            default => 'improved',
        };

        return ['status' => $status, 'critical' => $critical, 'findings' => $findings];
    }
}
