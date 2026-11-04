<?php

namespace App\Services\VisualQa;

use App\Models\DesignSpec;
use App\Models\DesignSpecAsset;
use App\Models\PreviewVersion;
use App\Services\Blueprint\DesignSpecV15;
use App\Services\Blueprint\SectionRegistry;

/**
 * Bounded, deterministic refinement of a blueprint site from failed QA findings
 * (PRD v1.5 refinement_policy). It may only change fields the v1.5 spec already
 * supports — drop a section that rendered empty, unbind an image that failed to
 * load, repair a dead in-page link — and never writes free-form content. Every
 * change is re-validated against the full v1.5 contract; anything it cannot fix
 * safely (overflow, console errors, unverified figures) stays a reported finding.
 * The previous version is always kept.
 */
class BlueprintRefiner
{
    public function __construct(
        private readonly DesignSpecV15 $v15Validator,
        private readonly SectionRegistry $registry,
    ) {}

    /**
     * @param  array<int, array>  $findings
     * @return array{spec: DesignSpec, fixes: array<int, array{finding: string, action: string, target: string}>}|null null when nothing safe to fix
     */
    public function refine(DesignSpec $spec, array $findings): ?array
    {
        $json = $spec->design_spec_json;
        $v15 = $json['v15'] ?? null;
        if (! is_array($v15)) {
            return null;
        }

        $failed = array_values(array_filter($findings, fn ($f) => ($f['status'] ?? null) === 'failed'));
        $fixes = [];

        $emptyIds = [];
        foreach ($failed as $f) {
            if ($f['check'] === 'No empty or missing sections') {
                array_push($emptyIds, ...($f['meta']['instance_ids'] ?? []));
            }
        }
        $emptyIds = array_values(array_unique($emptyIds));
        if ($emptyIds) {
            $before = count($v15['pages'][0]['sections']);
            $v15['pages'][0]['sections'] = array_values(array_filter($v15['pages'][0]['sections'], fn ($s) => ! in_array($s['instance_id'], $emptyIds, true)));
            foreach ($emptyIds as $id) {
                $fixes[] = ['finding' => 'No empty or missing sections', 'action' => 'Removed the empty section', 'target' => $id];
                $v15['content_mapping']['omitted_sections'][] = ['instance_id' => $id, 'required' => false, 'reason' => 'Removed after visual QA found it rendered empty.'];
            }
            if (count($v15['pages'][0]['sections']) === $before) {
                $fixes = []; // ids did not match anything: nothing was actually changed
            }
        }

        $brokenSrcs = [];
        foreach ($failed as $f) {
            if ($f['check'] === 'Images load') {
                array_push($brokenSrcs, ...($f['meta']['broken_srcs'] ?? []));
            }
        }
        if ($brokenSrcs) {
            $assets = $spec->analysisRun->websiteAssets()->get()->keyBy('asset_key');
            foreach ($v15['pages'][0]['sections'] as &$section) {
                $removed = [];
                $section['props'] = $this->unbindBroken($section['props'], $brokenSrcs, $assets, $removed);
                foreach (array_unique($removed) as $assetId) {
                    $fixes[] = ['finding' => 'Images load', 'action' => 'Unbound the image that failed to load (a brand-token visual is shown instead)', 'target' => "{$section['instance_id']}:{$assetId}"];
                    $section['asset_bindings'] = array_values(array_filter($section['asset_bindings'], fn ($b) => $b['asset_id'] !== $assetId));
                }
            }
            unset($section);
        }

        // Links that point at anything no longer on the page (including after removals above).
        $before = json_encode($v15['pages'][0]['sections']);
        $anchors = $this->anchors($v15);
        foreach ($v15['pages'][0]['sections'] as &$section) {
            $section['props'] = $this->repairLinks($section['props'], $anchors);
        }
        unset($section);
        if (json_encode($v15['pages'][0]['sections']) !== $before) {
            $fixes[] = ['finding' => 'Internal links resolve', 'action' => 'Repaired in-page links whose target is not on the page', 'target' => 'navigation and calls to action'];
        }

        if ($fixes === []) {
            return null;
        }

        $v15['content_mapping']['required_slots_complete'] = $this->requiredStillPresent($v15);
        $v15['content_mapping']['status'] = $v15['content_mapping']['required_slots_complete'] && collect($v15['content_mapping']['omitted_sections'])->where('required', true)->isEmpty() ? 'complete' : 'partial';

        $result = $this->v15Validator->validate($v15, $spec->analysisRun);
        if ($result['errors']) {
            return null; // never ship a refinement that breaks the contract
        }
        $v15['validation'] = $result['flags'];

        $json['v15'] = $v15;
        $new = $this->newVersion($spec, $json);

        return ['spec' => $new, 'fixes' => $fixes];
    }

    private function unbindBroken(array $node, array $brokenSrcs, $assets, array &$removed): array
    {
        foreach ($node as $key => $value) {
            if ($key === 'image' && is_array($value) && isset($value['asset_id'])) {
                $stored = basename((string) ($assets->get($value['asset_id'])->stored_path ?? ''));
                $match = $stored !== '' && collect($brokenSrcs)->contains(fn ($src) => str_ends_with($src, $stored));
                if ($match) {
                    $removed[] = $value['asset_id'];
                    unset($node[$key]);
                }
            } elseif (is_array($value)) {
                $node[$key] = $this->unbindBroken($value, $brokenSrcs, $assets, $removed);
            }
        }

        return $node;
    }

    /** Repairs links to anything no longer on the page (used after a section is hidden). Returns the instance ids whose props changed. */
    public function pruneDeadLinks(array &$v15): array
    {
        $changed = [];
        $anchors = $this->anchors($v15);
        foreach ($v15['pages'][0]['sections'] as &$section) {
            $before = json_encode($section['props']);
            $section['props'] = $this->repairLinks($section['props'], $anchors);
            json_encode($section['props']) !== $before && $changed[] = $section['instance_id'];
        }

        return $changed;
    }

    private function anchors(array $v15): array
    {
        $anchors = ['top' => true];
        foreach ($v15['pages'][0]['sections'] as $s) {
            ($a = $this->registry->anchor($s['component_id'], $s['variant_id'])) && $anchors[$a] = true;
        }

        return $anchors;
    }

    /** Drops dead links from lists, removes dead secondary CTAs and points a dead primary CTA at the top of the page. */
    private function repairLinks(array $node, array $anchors): array
    {
        $dead = fn ($v) => is_array($v) && isset($v['href']) && is_string($v['href']) && str_starts_with($v['href'], '#') && ! isset($anchors[substr($v['href'], 1)]);

        foreach ($node as $key => $value) {
            if (! is_array($value)) {
                continue;
            }
            if (array_is_list($value)) {
                $value = array_values(array_filter($value, fn ($item) => ! (is_array($item) && $dead($item) && isset($item['label']) && count($item) === 2)));
                $node[$key] = array_map(fn ($i) => is_array($i) ? $this->repairLinks($i, $anchors) : $i, $value);
            } elseif ($dead($value)) {
                if ($key === 'primary_cta' || $key === 'cta') {
                    $node[$key]['href'] = '#top';
                } else {
                    unset($node[$key]);
                }
            } else {
                $node[$key] = $this->repairLinks($value, $anchors);
            }
        }

        return $node;
    }

    private function requiredStillPresent(array $v15): bool
    {
        return collect($v15['content_mapping']['omitted_sections'])->where('required', true)->isEmpty();
    }

    private function newVersion(DesignSpec $from, array $json): DesignSpec
    {
        return app(\App\Services\Blueprint\DesignSpecVersioner::class)->create($from, $json);
    }
}
