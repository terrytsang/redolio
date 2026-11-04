<?php

namespace App\Services\Hero;

use App\Models\AnalysisRun;
use App\Models\DesignSpec;
use App\Models\HeroCopyApproval;
use App\Models\HeroCopyOption;
use App\Models\User;
use App\Services\Blueprint\BlueprintCatalog;
use App\Services\Blueprint\DesignSpecV15;
use App\Services\Blueprint\DesignSpecVersioner;
use App\Services\Blueprint\SectionRegistry;
use App\Services\Editing\SpecEditException;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\MetaModelClient;
use App\Services\Orchestration\ModelCallLogger;
use Illuminate\Support\Facades\Log;

/**
 * Bounded fit loop (PRD v1.6): measure the real hero; if it does not fit, shorten the COPY (at most two rewrites), then
 * try a roomier hero layout (at most two) — never smaller type. Every rewrite must pass the same hard factual gate and
 * editorial gate again, every attempt is a new version and a stored measurement, and when the bounds are used up the
 * user decides (edit it, or accept the layout as an exception). Nothing is silently shrunk or hidden.
 */
class HeroCopyRefinementService
{
    public function __construct(
        private readonly HeroLayoutValidationService $layout,
        private readonly MetaModelClient $model,
        private readonly ModelCallLogger $logger,
        private readonly HeroCopyValidationService $validator,
        private readonly HeroCopyVersionService $versions,
        private readonly HeroDestinations $destinations,
        private readonly DesignSpecV15 $v15,
        private readonly DesignSpecVersioner $versioner,
        private readonly BlueprintCatalog $blueprints,
        private readonly SectionRegistry $registry,
    ) {}

    /**
     * @return array{status: string, result: string, attempts: array<int, array>, spec_version: int, reasons: array, copy_refinements: int, layout_adjustments: int, font_px: ?float, screenshots: array}
     */
    public function run(AnalysisRun $run, ?User $user): array
    {
        $cfg = config('hero_layout');
        $spec = $run->currentDesignSpec() ?? throw new \RuntimeException('There is no design to measure yet.');
        $val = $this->layout->validate($run, $spec, 0, $this->optionId($spec));
        $this->stamp($spec, $val);
        $attempts = [$this->summary(0, 'measured', $val, $spec)];
        $startFont = $val['font_px'];
        $copyN = 0;
        $layoutN = 0;

        $editedMeanwhile = false;
        while ($val['result'] === 'fail') {
            if ($this->changedMeanwhile($run, $spec)) { // the owner edited while we were measuring: their version wins, we never build on a stale copy
                $editedMeanwhile = true;
                break;
            }
            $reasons = $val['reasons'];
            $copyFixable = collect($reasons)->contains(fn ($r) => in_array($r['code'], ['h1_lines', 'description_lines', 'overflow', 'clipping'], true));
            $step = null;
            if ($copyFixable && $copyN < $cfg['max_copy_refinements']) {
                $new = $this->refineCopy($run, $spec, $reasons, $user);
                $copyN++;
                $step = 'copy';
                if ($new === null) { // the rewrite was unavailable or failed a gate: it still counts against the bound
                    $attempts[] = ['attempt' => count($attempts), 'action' => 'copy_rewrite_rejected', 'result' => 'fail', 'reasons' => $reasons];

                    continue;
                }
                $spec = $new;
            } elseif ($layoutN < $cfg['max_layout_adjustments'] && ($new = $this->adjustLayout($run, $spec, $reasons))) {
                $layoutN++;
                $step = 'layout';
                $spec = $new;
            } else {
                break;
            }
            $val = $this->layout->validate($run, $spec, count($attempts), $this->optionId($spec));
            $this->stamp($spec, $val);
            $attempts[] = $this->summary(count($attempts), $step, $val, $spec);
        }

        if ($startFont !== null && $val['font_px'] !== null && abs($val['font_px'] - $startFont) > 0.5) {
            // The type size is not ours to change. If it moved (for example a different layout), it is never reported as a clean fit.
            $val['reasons'][] = ['viewport' => 'desktop', 'code' => 'font_size_changed', 'message' => 'The headline type size changed during refinement; REDOLIO never shrinks type to make copy fit.'];
            $val['result'] = 'fail';
        }
        if ($editedMeanwhile) {
            $val['reasons'][] = ['viewport' => 'desktop', 'code' => 'edited_meanwhile', 'message' => 'The page was edited while the fit check ran, so the check stopped. Run it again.'];
        }

        $status = match (true) {
            $editedMeanwhile => 'needs_review',
            $val['result'] === 'pass' => 'fit',
            in_array($val['result'], ['unavailable', 'exception'], true) => 'not_measured',
            default => 'needs_review',
        };

        return ['status' => $status, 'result' => $val['result'], 'attempts' => $attempts, 'spec_version' => $spec->version, 'reasons' => $val['reasons'], 'copy_refinements' => $copyN,
            'layout_adjustments' => $layoutN, 'font_px' => $val['font_px'], 'screenshots' => $val['screenshots'] ?? []];
    }

    /** The user chose to keep copy that does not fit the layout preference. Recorded, never silent. */
    public function acceptException(AnalysisRun $run, ?User $user): DesignSpec
    {
        $spec = $run->currentDesignSpec();
        $json = $spec->design_spec_json;
        if (empty($json['v16']['hero_content'])) {
            throw new SpecEditException('There is no hero copy to accept.');
        }
        $json['v16']['hero_content']['approval_status'] = 'layout_exception';
        $json['v16']['change_summary'] = 'Hero layout exception accepted by the owner';
        $json['v16']['edit_draft'] = null;
        $new = $this->versioner->create($spec, $json);
        if ($oid = $json['v16']['hero_content']['copy_option_id'] ?? null) {
            HeroCopyApproval::create(['analysis_run_id' => $run->id, 'hero_copy_option_id' => $oid, 'approved_by_user_id' => $user?->id, 'approval_status' => 'layout_exception', 'design_spec_id' => $new->id, 'approved_at' => now()]);
        }

        return $new;
    }

    // ---------------------------------------------------------------- copy

    private function refineCopy(AnalysisRun $run, DesignSpec $spec, array $reasons, ?User $user): ?DesignSpec
    {
        $profile = $run->currentPositioningProfile();
        $hero = collect($spec->design_spec_json['v15']['pages'][0]['sections'])->firstWhere('component_id', 'hero');
        $variant = $hero['variant_id'];
        $copy = $this->currentCopy($spec, $available = $this->destinations->forSpec($spec->design_spec_json));
        $usable = $profile ? $profile->evidence()->get()->filter->isFactual()->map(fn ($c) => ['claim_key' => $c->claim_key, 'field' => $c->field, 'value' => $c->claim_value])->values()->all() : [];

        $input = [
            'current' => ['eyebrow' => $copy->eyebrow, 'headline' => $copy->headline, 'description' => $copy->description],
            'problem' => collect($reasons)->pluck('message')->unique()->values()->all(),
            'layout' => ['variant' => $variant, 'preferred_h1_lines' => HeroLayoutConstraints::preferredH1Lines($variant, 'mobile'), 'copy_rule' => HeroLayoutConstraints::copyRule($variant), 'do_not_change' => ['layout', 'font size']],
            'usable_claims' => $usable, 'current_headline_words' => TextTools::wordCount($copy->headline),
        ];
        $hash = hash('sha256', json_encode(['stage' => 'hero_copy_refine', 'run' => $run->public_id, 'v' => $spec->version, 'h' => $copy->headline]));
        try {
            $resp = $this->model->refineHeroCopy($input, ['run_id' => $run->public_id]);
            $this->logger->logSuccess($run, 'hero_copy_refine', $hash, $resp);
        } catch (MetaApiException $e) {
            $this->logger->logFailure($run, 'hero_copy_refine', $hash, $e->errorCode);
            Log::info('Hero refinement unavailable', ['run' => $run->public_id, 'reason' => $e->getMessage()]);

            return null;
        }
        $d = $resp->data;
        $new = new HeroCopy(
            ! empty($d['eyebrow']) ? TextTools::fit((string) $d['eyebrow'], 60) : $copy->eyebrow, trim((string) ($d['headline'] ?? '')), trim((string) ($d['description'] ?? '')) ?: $copy->description,
            $copy->primaryCta, $copy->secondaryCta, $copy->trustSignals, $copy->evidenceIds, $copy->strategyId,
        );
        // A rewrite must not grow, and must clear the same gates as any generated copy.
        if ($new->headline === '' || TextTools::wordCount($new->headline) > TextTools::wordCount($copy->headline)) {
            return null;
        }
        $v = $profile ? $this->validator->validate($new, $profile, $available, true) : null;
        if ($v && ($v['factual_gate'] === 'fail' || $v['editorial_gate'] === 'fail')) {
            return null;
        }

        $parent = $this->optionId($spec);
        $option = HeroCopyOption::create([
            'analysis_run_id' => $run->id, 'positioning_profile_id' => $profile?->id ?? 0, 'batch' => HeroCopyOption::latestBatch($run->id), 'strategy_id' => $new->strategyId,
            'eyebrow' => $new->eyebrow, 'headline' => $new->headline, 'description' => $new->description, 'primary_cta_json' => $new->primaryCta, 'secondary_cta_json' => $new->secondaryCta,
            'trust_signals_json' => $new->trustSignals, 'evidence_ids_json' => $new->evidenceIds, 'assumptions_json' => [], 'risks_json' => [], 'score_breakdown_json' => [], 'total_score' => 0,
            'recommendation' => 'needs_review', 'hard_gate_status' => $v['factual_gate'] ?? 'pending', 'validation_json' => $v ?? ['issues' => []],
            'rationale' => TextTools::fit((string) ($d['change_summary'] ?? 'Shortened to fit the layout.'), 300), 'status' => 'candidate', 'source' => 'refinement', 'parent_option_id' => $parent, 'created_at' => now(),
        ]);
        try {
            return $this->versions->approve($run, $option, $user)['spec'];
        } catch (SpecEditException $e) {
            $option->update(['status' => 'rejected']);

            return null;
        }
    }

    private function currentCopy(DesignSpec $spec, array $available): HeroCopy
    {
        $json = $spec->design_spec_json;
        $hc = $json['v16']['hero_content'] ?? null;
        $p = collect($json['v15']['pages'][0]['sections'])->firstWhere('component_id', 'hero')['props'];
        $keyFor = fn ($href) => collect($available)->search(fn ($d) => ($d['destination'] ?? null) === $href) ?: null;
        $cta = fn ($c) => ! empty($c['label']) ? ['label' => $c['label'], 'destination_key' => $keyFor($c['href'] ?? ''), 'destination' => $c['href'] ?? null] : null;

        return new HeroCopy($p['eyebrow'] ?? null, (string) $p['headline'], $p['subheadline'] ?? null, $cta($p['primary_cta'] ?? null), $cta($p['secondary_cta'] ?? null), $hc['trust_signals'] ?? [], $hc['evidence_ids'] ?? [], $hc['strategy_id'] ?? 'direct-positioning');
    }

    // ---------------------------------------------------------------- layout

    /** A roomier hero variant, only where the blueprint does not lock the variant and the content still validates. */
    private function adjustLayout(AnalysisRun $run, DesignSpec $spec, array $reasons): ?DesignSpec
    {
        if (! collect($reasons)->contains(fn ($r) => in_array($r['code'], ['h1_lines', 'description_lines', 'cta_hidden', 'overflow', 'clipping'], true))) {
            return null;
        }
        $json = $spec->design_spec_json;
        $v15 = $json['v15'];
        $bp = $this->blueprints->find($v15['design']['blueprint_id']);
        $idx = collect($v15['pages'][0]['sections'])->search(fn ($s) => $s['component_id'] === 'hero');
        if ($idx === false || ! $bp) {
            return null;
        }
        $section = $v15['pages'][0]['sections'][$idx];
        $def = collect($bp['section_sequence'])->firstWhere('instance_id', $section['instance_id']);
        if (! $def || ! empty($def['variant_locked'])) {
            return null;
        }
        $tried = $json['v16']['layout_tried'] ?? [$section['variant_id']];
        $roomier = collect(array_keys(config('hero_layout.variants')))->reject(fn ($v) => in_array($v, $tried, true))->filter(fn ($v) => $this->registry->has('hero', $v))
            ->sortByDesc(fn ($v) => HeroLayoutConstraints::preferredH1Lines($v, 'mobile') + HeroLayoutConstraints::preferredH1Lines($v, 'desktop'))->first();
        if (! $roomier) {
            return null;
        }
        $v15['pages'][0]['sections'][$idx]['variant_id'] = $roomier;
        $result = $this->v15->validate($v15, $run);
        if ($result['errors']) {
            return null;
        }
        $v15['validation'] = $result['flags'];
        $json['v15'] = $v15;
        $json['v16'] = ($json['v16'] ?? ['schema_version' => '1.6']) + [];
        $json['v16']['layout_tried'] = array_merge($tried, [$roomier]);
        $json['v16']['change_summary'] = "Hero layout changed from “{$section['variant_id']}” to “{$roomier}” (it allows more lines); type size unchanged";
        $json['v16']['edit_draft'] = null;

        return $this->versioner->create($spec, $json);
    }

    // ---------------------------------------------------------------- bookkeeping

    private function changedMeanwhile(AnalysisRun $run, DesignSpec $spec): bool
    {
        return $run->currentDesignSpec()?->id !== $spec->id;
    }

    private function optionId(DesignSpec $spec): ?int
    {
        return $spec->design_spec_json['v16']['hero_content']['copy_option_id'] ?? null;
    }

    /** Records measured fit on the spec's hero_content (metadata only — the copy itself is untouched). */
    private function stamp(DesignSpec $spec, array $val): void
    {
        $json = $spec->design_spec_json;
        if (empty($json['v16']['hero_content'])) {
            return;
        }
        $json['v16']['hero_content']['validation'] = array_merge($json['v16']['hero_content']['validation'], HeroLayoutValidationService::statusByViewport($val['result'], $val['reasons']));
        $spec->update(['design_spec_json' => $json]);
    }

    private function summary(int $n, string $action, array $val, DesignSpec $spec): array
    {
        return ['attempt' => $n, 'action' => $action, 'result' => $val['result'], 'spec_version' => $spec->version, 'reasons' => $val['reasons'], 'h1_lines' => collect($val['viewports'] ?? [])->map(fn ($m) => $m['h1_lines'] ?? null)->all(), 'font_px' => $val['font_px']];
    }
}
