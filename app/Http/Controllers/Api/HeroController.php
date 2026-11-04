<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesRunOwner;
use App\Http\Controllers\Controller;
use App\Jobs\RunHeroGenerationJob;
use App\Models\AnalysisRun;
use App\Models\DesignSpec;
use App\Models\HeroCopyOption;
use App\Models\HeroGeneration;
use App\Models\HeroRenderValidation;
use App\Services\Editing\SpecEditException;
use App\Services\Hero\HeroCopyRefinementService;
use App\Services\Hero\HeroCopyVersionService;
use App\Services\Hero\HeroMessagingDiagnosisService;
use App\Services\Hero\PositioningIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * v1.6 JSON surface (PRD api_contracts). Signed-in owners only; expensive/model steps are queued and return a job id
 * to poll, and are idempotent per Idempotency-Key (or while one is already running).
 */
class HeroController extends Controller
{
    use AuthorizesRunOwner;

    public function analysePositioning(Request $request, AnalysisRun $analysisRun): JsonResponse
    {
        $this->own($request, $analysisRun);
        if (! $analysisRun->currentWebsiteDna()) {
            return response()->json(['message' => 'Website DNA must finish before positioning can be analysed.'], 409);
        }

        return $this->queue($request, $analysisRun, 'positioning');
    }

    public function positioning(Request $request, AnalysisRun $analysisRun): JsonResponse
    {
        $this->own($request, $analysisRun);
        $p = $analysisRun->currentPositioningProfile();

        return $p ? response()->json($this->profileJson($p)) : response()->json(['message' => 'No positioning profile yet.'], 404);
    }

    public function correct(Request $request, AnalysisRun $analysisRun, PositioningIntelligenceService $svc): JsonResponse
    {
        $this->own($request, $analysisRun);
        $p = $analysisRun->currentPositioningProfile() ?? abort(404, 'No positioning profile yet.');
        $d = $request->validate([
            'action' => ['required', 'in:confirm,edit,reject,add,resolve'], 'claim_key' => ['nullable', 'string', 'max:80'],
            'value' => ['nullable', 'string', 'max:400'], 'field' => ['nullable', 'string', 'max:40'], 'topic' => ['nullable', 'string', 'max:120'],
        ]);
        try {
            $p = $svc->applyCorrection($p, $d['action'], $d['claim_key'] ?? null, $d['value'] ?? null, $d['field'] ?? null, $d['topic'] ?? null, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->profileJson($p));
    }

    public function diagnose(Request $request, AnalysisRun $analysisRun, HeroMessagingDiagnosisService $svc): JsonResponse
    {
        $this->own($request, $analysisRun);
        $p = $analysisRun->currentPositioningProfile() ?? abort(409, 'Analyse positioning first.');
        $variant = collect($analysisRun->currentDesignSpec()?->design_spec_json['v15']['pages'][0]['sections'] ?? [])->firstWhere('component_id', 'hero')['variant_id'] ?? null;

        return response()->json($svc->diagnose($svc->siteHero($p), $p, $variant) + ['current_hero' => $p->existing_hero_json]);
    }

    public function generateOptions(Request $request, AnalysisRun $analysisRun): JsonResponse
    {
        $this->own($request, $analysisRun);
        $p = $analysisRun->currentPositioningProfile();
        if (! $p || ! in_array($p->status, ['ready', 'needs_confirmation'], true)) {
            return response()->json(['message' => 'The positioning profile must be ready first.'], 409);
        }

        return $this->queue($request, $analysisRun, 'options');
    }

    public function job(Request $request, AnalysisRun $analysisRun, HeroGeneration $generation): JsonResponse
    {
        $this->own($request, $analysisRun);
        abort_unless($generation->analysis_run_id === $analysisRun->id, 404);

        return response()->json($this->jobJson($generation));
    }

    public function options(Request $request, AnalysisRun $analysisRun): JsonResponse
    {
        $this->own($request, $analysisRun);
        $options = HeroCopyOption::where('analysis_run_id', $analysisRun->id)->whereIn('status', ['candidate', 'approved', 'rejected'])
            ->orderByRaw("CASE recommendation WHEN 'recommended' THEN 0 WHEN 'needs_review' THEN 1 WHEN 'rewrite' THEN 2 ELSE 3 END")->orderByDesc('total_score')->get()
            ->groupBy(fn ($o) => (int) $o->batch)->sortKeysDesc()->first() ?? collect(); // numeric: batch is a string column
        $job = HeroGeneration::where('analysis_run_id', $analysisRun->id)->where('kind', 'options')->latest('id')->first();

        return response()->json(['options' => $options->map(fn ($o) => $this->optionJson($o))->values(), 'job' => $job ? $this->jobJson($job) : null]);
    }

    public function approve(Request $request, AnalysisRun $analysisRun, HeroCopyOption $option, HeroCopyVersionService $svc): JsonResponse
    {
        $this->own($request, $analysisRun);
        abort_unless($option->analysis_run_id === $analysisRun->id, 404);
        $edits = $request->validate([
            'eyebrow' => ['nullable', 'string', 'max:80'], 'headline' => ['nullable', 'string', 'max:140'], 'description' => ['nullable', 'string', 'max:800'],
            'primary_cta.label' => ['nullable', 'string', 'max:50'], 'secondary_cta.label' => ['nullable', 'string', 'max:50'],
        ]);
        try {
            $r = $svc->approve($analysisRun, $option, $request->user(), $edits);
        } catch (SpecEditException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors], 422);
        }

        return response()->json(['spec_version' => $r['spec']->version, 'option' => $this->optionJson($r['option']), 'warnings' => $r['warnings']]);
    }

    public function reject(Request $request, AnalysisRun $analysisRun, HeroCopyOption $option, HeroCopyVersionService $svc): JsonResponse
    {
        $this->own($request, $analysisRun);
        abort_unless($option->analysis_run_id === $analysisRun->id, 404);

        return response()->json(['option' => $this->optionJson($svc->reject($option, $request->user()))]);
    }

    /** Queue the browser measurement + bounded fit loop for the current hero. */
    public function validateRender(Request $request, AnalysisRun $analysisRun): JsonResponse
    {
        $this->own($request, $analysisRun);
        $spec = $analysisRun->currentDesignSpec();
        if (! $spec || empty($spec->design_spec_json['v16']['hero_content'])) {
            return response()->json(['message' => 'Approve or edit the hero copy first, then check how it fits.'], 409);
        }

        return $this->queue($request, $analysisRun, 'render');
    }

    /** The owner keeps copy that exceeds the layout's line preference. Recorded as an explicit exception. */
    public function acceptLayoutException(Request $request, AnalysisRun $analysisRun, HeroCopyRefinementService $svc): JsonResponse
    {
        $this->own($request, $analysisRun);
        try {
            $spec = $svc->acceptException($analysisRun, $request->user());
        } catch (SpecEditException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['spec_version' => $spec->version, 'approval_status' => 'layout_exception']);
    }

    /** Latest measured fit for the current hero (what the spec says, plus the last stored measurement). */
    public function renderStatus(Request $request, AnalysisRun $analysisRun): JsonResponse
    {
        $this->own($request, $analysisRun);
        $spec = $analysisRun->currentDesignSpec();
        $last = HeroRenderValidation::where('analysis_run_id', $analysisRun->id)->latest('id')->first();
        $job = HeroGeneration::where('analysis_run_id', $analysisRun->id)->where('kind', 'render')->latest('id')->first();

        return response()->json([
            'validation' => $spec?->design_spec_json['v16']['hero_content']['validation'] ?? null, 'approval_status' => $spec?->design_spec_json['v16']['hero_content']['approval_status'] ?? null,
            'last' => $last ? ['result' => $last->validation_result, 'reasons' => $last->details_json['reasons'] ?? [], 'screenshots' => $last->details_json['screenshots'] ?? [], 'h1_lines' => $last->headline_line_count, 'font_px' => $last->details_json['font_px'] ?? null, 'spec_version' => DesignSpec::whereKey($last->design_spec_id)->value('version')] : null,
            'job' => $job ? $this->jobJson($job) : null,
        ]);
    }

    public function versions(Request $request, AnalysisRun $analysisRun, HeroCopyVersionService $svc): JsonResponse
    {
        $this->own($request, $analysisRun);

        return response()->json([
            'hero_history' => $svc->history($analysisRun),
            'versions' => $analysisRun->designSpecs()->orderByDesc('version')->get()->map(fn ($s) => [
                'version' => $s->version, 'created_at' => $s->created_at?->toIso8601String(), 'summary' => $s->design_spec_json['v16']['change_summary'] ?? null,
                'hero_headline' => $s->design_spec_json['v16']['hero_content']['headline'] ?? ($s->design_spec_json['hero']['headline'] ?? null),
                'approval_status' => $s->design_spec_json['v16']['hero_content']['approval_status'] ?? null,
            ])->values(),
        ]);
    }

    // ---------------------------------------------------------------- helpers

    private function queue(Request $request, AnalysisRun $run, string $kind): JsonResponse
    {
        $key = $request->header('Idempotency-Key');
        // A job whose worker died would otherwise block this kind forever: anything unfinished past its timeout is failed.
        HeroGeneration::where('analysis_run_id', $run->id)->whereIn('status', ['pending', 'running'])->where('created_at', '<', now()->subSeconds(RunHeroGenerationJob::STALE_AFTER))
            ->update(['status' => 'failed', 'error_message' => 'This step took too long and was stopped. You can try again.', 'completed_at' => now()]);
        $existing = HeroGeneration::where('analysis_run_id', $run->id)->where('kind', $kind)
            ->where(fn ($q) => $q->whereIn('status', ['pending', 'running'])->when($key, fn ($q) => $q->orWhere('idempotency_key', $key)))->latest('id')->first();
        if ($existing) {
            return response()->json($this->jobJson($existing), 202);
        }
        $g = HeroGeneration::create(['analysis_run_id' => $run->id, 'kind' => $kind, 'status' => 'pending', 'idempotency_key' => $key ? mb_substr($key, 0, 80) : null, 'created_at' => now()]);
        RunHeroGenerationJob::dispatch($g->id);

        return response()->json($this->jobJson($g), 202);
    }

    private function jobJson(HeroGeneration $g): array
    {
        return ['job_id' => $g->id, 'kind' => $g->kind, 'status' => $g->status, 'error' => $g->error_message, 'result' => $g->result_json];
    }

    private function optionJson(HeroCopyOption $o): array
    {
        return [
            'option_id' => $o->id, 'strategy_id' => $o->strategy_id, 'eyebrow' => $o->eyebrow, 'headline' => $o->headline, 'description' => $o->description,
            'primary_cta' => $o->primary_cta_json ? ['label' => $o->primary_cta_json['label'] ?? null, 'destination_key' => $o->primary_cta_json['destination_key'] ?? null] : null,
            'secondary_cta' => $o->secondary_cta_json ? ['label' => $o->secondary_cta_json['label'] ?? null, 'destination_key' => $o->secondary_cta_json['destination_key'] ?? null] : null,
            'trust_signals' => $o->trust_signals_json, 'evidence_ids' => $o->evidence_ids_json, 'assumptions' => $o->assumptions_json, 'risks' => $o->risks_json,
            'score' => ['total' => $o->total_score, 'breakdown' => $o->score_breakdown_json, 'note' => 'An editorial estimate, not a measured conversion prediction.'],
            'recommendation' => $o->recommendation, 'hard_gate_status' => $o->hard_gate_status, 'issues' => $o->validation_json['issues'] ?? [],
            'relocate' => $o->validation_json['relocate'] ?? [], 'rationale' => $o->rationale, 'status' => $o->status, 'source' => $o->source, 'batch' => (int) $o->batch,
        ];
    }

    private function profileJson($p): array
    {
        return [
            'profile_id' => $p->id, 'version' => $p->version, 'status' => $p->status, 'source_mode' => $p->source_mode, 'business_category' => $p->business_category, 'business_model' => $p->business_model,
            'brand_voice' => $p->brand_voice, 'conversion_goal' => $p->conversion_goal, 'unknowns' => $p->unknowns_json, 'contradictions' => $p->contradictions_json, 'current_hero' => $p->existing_hero_json,
            'evidence' => $p->evidence()->orderBy('field')->orderBy('id')->get()->map(fn ($e) => [
                'claim_key' => $e->claim_key, 'field' => $e->field, 'value' => $e->claim_value, 'status' => $e->status, 'confidence' => $e->confidence,
                'source_url' => $e->source_url, 'evidence_ids' => $e->evidence_keys_json, 'summary' => $e->evidence_summary,
            ])->values(),
        ];
    }
}
