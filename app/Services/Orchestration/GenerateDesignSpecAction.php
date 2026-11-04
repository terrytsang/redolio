<?php

namespace App\Services\Orchestration;

use App\Models\AnalysisRun;
use App\Models\DesignDirection;
use App\Models\DesignPattern;
use App\Models\DesignSpec;
use App\Models\DesignSpecAsset;
use App\Models\DiagnosisReport;
use App\Models\EvaluationReport;
use App\Models\PreviewVersion;
use App\Models\WebsiteDna;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\MetaModelClient;
use App\Services\MetaApi\Validation\DesignSpecValidator;
use App\Services\MetaApi\Validation\EvaluationValidator;
use App\Services\Rendering\PreviewQualityEvaluator;

/**
 * Generates a new validated, versioned DesignSpec from the currently
 * approved Website DNA and diagnosis, then runs the evaluator against it.
 * Used both by the initial pipeline and by the manual "Redo" action
 * (AT-20: editing DNA and requesting another REDO creates a new version).
 */
class GenerateDesignSpecAction
{
    public function __construct(
        private readonly MetaModelClient $metaClient,
        private readonly DesignSpecValidator $designSpecValidator,
        private readonly EvaluationValidator $evaluationValidator,
        private readonly ModelCallLogger $modelCallLogger,
        private readonly PreviewQualityEvaluator $qualityEvaluator,
    ) {}

    /**
     * @param  array{preserve_logo?: bool, preserve_images?: bool, preserve_wording?: bool}  $preferences
     *
     * @throws MetaApiException
     */
    public function execute(AnalysisRun $run, WebsiteDna $dna, DiagnosisReport $diagnosis, ?string $directionKey = null, array $preferences = [], ?DesignSpec $refineFrom = null, ?array $visualQaIssues = null): DesignSpec
    {
        // Two sequential Meta API calls (design spec + evaluation) inside
        // one HTTP request can exceed PHP's default 30s max_execution_time
        // when this is invoked directly (the "Redo"/design-direction-select
        // actions), not just from inside AnalyzeWebsiteAction.
        set_time_limit(180);

        $diagnosisIssueIds = array_map(fn ($i) => $i['issue_id'], $diagnosis->issues_json['issues']);
        $evidenceIds = empty($diagnosis->issues_json['issues']) ? [] : array_unique(array_merge(
            ...array_map(fn ($i) => $i['evidence_ids'], $diagnosis->issues_json['issues'])
        ));

        // 'needs_user_review' is included: there is no actual review step
        // in this product today, so excluding it would permanently discard
        // real images (most real-world photos/banners don't match the
        // narrow deterministic heuristics for an auto 'preserve'/'reuse')
        // instead of ever offering them to the model. The model is already
        // instructed to only reference an asset when it genuinely fits.
        $assets = $run->websiteAssets()->whereIn('recommended_action', ['preserve', 'reuse', 'needs_user_review'])->get();
        $validAssetKeys = $assets->pluck('asset_key')->all();
        $direction = $directionKey ? DesignDirection::where('direction_key', $directionKey)->first() : null;
        $allowedLayoutCompositions = $direction?->recipe_json['layout_compositions'] ?? [];

        // A direction's recipe always renders through one of the real
        // allowlisted preview templates (PreviewRenderer::TEMPLATE_VIEWS).
        // For the original seeded directions direction_key IS the template
        // key; admin-captured directions (Design Intelligence) store the
        // closest-matching template separately in recipe_json, since their
        // own direction_key is never a valid, renderable template_key.
        $resolvedTemplateKey = $direction?->recipe_json['template_key'] ?? $directionKey;

        $context = ['run_id' => $run->public_id];
        if ($directionKey) {
            $context['requested_template_key'] = $resolvedTemplateKey;
            $context['preservation_preferences'] = $preferences;
        }
        if ($refineFrom) {
            $context['refine_previous_spec'] = $this->summarizeForRefinement($refineFrom->design_spec_json);
        }
        if (! empty($visualQaIssues)) {
            $context['visual_qa_issues'] = $visualQaIssues;
        }
        // The business's own words, read from its real site: the redesign must build on them, not replace them.
        $homeHeadings = (array) ($run->siteSnapshot?->pages()->orderBy('id')->first()?->headings_json ?? []);
        $context['original_messaging'] = [
            'positioning_statement' => $dna->dna_json['value_proposition']['text'] ?? null,
            'homepage_headings' => array_slice(array_values(array_filter($homeHeadings, 'is_string')), 0, 6),
            'existing_calls_to_action' => array_values(array_filter(array_column((array) ($dna->dna_json['cta_inventory'] ?? []), 'label'))),
        ];
        $context['available_assets'] = $assets->map(fn ($a) => [
            'asset_id' => $a->asset_key,
            'asset_type' => $a->asset_type,
            'asset_origin' => $a->asset_origin,
            'detected_role' => $a->detected_role,
            'preservation_priority' => $a->preservation_priority,
        ])->all();

        // Self-evolving design taste: feed the most recently scouted
        // patterns relevant to this direction (or general ones) in as
        // optional inspiration — never a mandate, the model still grounds
        // everything in this business's own DNA/diagnosis/assets.
        $patternQuery = DesignPattern::query();
        if ($directionKey) {
            $patternQuery->forDirection($directionKey);
        }
        $context['trend_inspiration'] = $patternQuery->orderByDesc('scouted_at')->limit(8)->get()
            ->map(fn (DesignPattern $p) => [
                'pattern_type' => $p->pattern_type,
                'title' => $p->title,
                'description' => $p->description,
            ])->all();

        [$validated, $modelIdentifier] = $this->generateAndValidate($run, $dna, $diagnosis, $context, $diagnosisIssueIds, $evidenceIds, $validAssetKeys, $allowedLayoutCompositions);

        // One bounded automatic revision if the result fails a critical
        // check (preview_quality_gate.retry_policy) — never more than one,
        // and the user still gets a result either way.
        $quality = $this->qualityEvaluator->evaluate($validated, $validAssetKeys);
        if ($quality['status'] === 'needs_attention' && ! empty($quality['critical'])) {
            $context['previous_attempt_issues'] = $quality['findings'];
            [$retried, $retriedModelIdentifier] = $this->generateAndValidate($run, $dna, $diagnosis, $context, $diagnosisIssueIds, $evidenceIds, $validAssetKeys, $allowedLayoutCompositions);
            $retryQuality = $this->qualityEvaluator->evaluate($retried, $validAssetKeys);

            if (empty($retryQuality['critical'])) {
                $validated = $retried;
                $modelIdentifier = $retriedModelIdentifier;
                $quality = $retryQuality;
            } else {
                $quality = $retryQuality; // Keep the original spec but report the latest (still critical) findings.
            }
        }

        if ($directionKey) {
            // The user's selected direction always takes precedence over
            // whatever template_key the model chose (PRD must_follow rule).
            $validated['template_key'] = $resolvedTemplateKey;
        }

        $validated = $this->keepOriginalPositioning($validated, $dna->dna_json);
        $validated = $this->attachDeterministicFields($validated, $run, $direction);

        $nextVersion = (int) $run->designSpecs()->max('version') + 1;

        $designSpec = DesignSpec::create([
            'analysis_run_id' => $run->id,
            'version' => $nextVersion,
            'template_key' => $validated['template_key'],
            'design_spec_json' => $validated,
            'status' => 'valid',
            'model_identifier' => $modelIdentifier,
            'prompt_version' => config('meta_api.prompt_version'),
        ]);

        foreach ($validated['asset_references'] as $ref) {
            DesignSpecAsset::create([
                'design_spec_id' => $designSpec->id,
                'asset_key' => $ref['asset_id'],
                'component_role' => $ref['role'],
                'usage_json' => ['reason' => $ref['reason']],
            ]);
        }

        PreviewVersion::create([
            'analysis_run_id' => $run->id,
            'design_spec_id' => $designSpec->id,
            'version_number' => $nextVersion,
            'render_status' => $quality['status'],
            'quality_report_json' => $quality,
            'created_at' => now(),
        ]);

        $this->generateEvaluation($run, $diagnosis, $designSpec, $quality);

        return $designSpec;
    }

    /**
     * Condensed enough to anchor a refinement pass (hero, section headings/
     * bodies, rationale) without re-sending the full spec verbatim and
     * inviting the model to just copy it back unchanged.
     */
    private function summarizeForRefinement(array $spec): array
    {
        return [
            'hero' => $spec['hero'] ?? null,
            'sections' => array_map(fn ($s) => [
                'section_key' => $s['section_key'] ?? null,
                'heading' => $s['heading'] ?? null,
                'body' => $s['body'] ?? null,
                'item_count' => count($s['items'] ?? []),
            ], $spec['sections'] ?? []),
            'design_personality' => $spec['design_personality'] ?? null,
            'previous_rationale' => array_map(fn ($r) => $r['decision'] ?? null, $spec['rationale'] ?? []),
        ];
    }

    /**
     * @return array{0: array, 1: ?string} [validated spec, model identifier]
     */
    private function generateAndValidate(
        AnalysisRun $run,
        WebsiteDna $dna,
        DiagnosisReport $diagnosis,
        array $context,
        array $diagnosisIssueIds,
        array $evidenceIds,
        array $validAssetKeys,
        array $allowedLayoutCompositions,
    ): array {
        $requestHash = hash('sha256', (string) json_encode(['stage' => 'redesign', 'diagnosis_id' => $diagnosis->id, 'dna_id' => $dna->id, 'context' => $context]));

        try {
            $response = $this->metaClient->generateDesignSpec($dna->dna_json, $diagnosis->issues_json, $context);
            $this->modelCallLogger->logSuccess($run, 'redesign_recommendations', $requestHash, $response);
        } catch (MetaApiException $e) {
            $this->modelCallLogger->logFailure($run, 'redesign_recommendations', $requestHash, $e->errorCode);

            throw $e;
        }

        $validated = $this->designSpecValidator->validate($response->data, $diagnosisIssueIds, $evidenceIds, $validAssetKeys, $allowedLayoutCompositions);

        return [$validated, $response->modelIdentifier];
    }

    /**
     * Fills the PRD's remaining required DesignSpec top-level fields that
     * are pure derivations of data already validated/known server-side —
     * these are never requested from the model, so they can never drift
     * from what was actually approved (design_direction_id/version,
     * color_system, typography_system, section_composition,
     * responsive_rules, component_instances, design_rationale).
     */
    private function attachDeterministicFields(array $validated, AnalysisRun $run, ?DesignDirection $direction): array
    {
        $validated['design_direction_id'] = $validated['template_key'];
        $validated['design_direction_version'] = $direction?->version;
        $validated['design_rationale'] = $validated['rationale'];
        $validated['color_system'] = $validated['brand']['color_tokens'];
        $validated['typography_system'] = ['typography' => $validated['brand']['typography'], 'spacing_scale' => $validated['brand']['spacing_scale']];
        $validated['section_composition'] = array_map(fn ($s) => $s['section_key'], $validated['sections']);
        $validated['responsive_rules'] = $direction?->recipe_json['responsive_rules'] ?? 'Single-column stacked layout below the tablet breakpoint.';
        $validated['component_instances'] = array_merge(['hero'], $validated['section_composition'], ['footer']);

        return $validated;
    }

    /**
     * The business's evidence-backed positioning statement must survive the redesign. If the model's hero
     * no longer carries it (fewer than half of its significant words appear), the original statement is
     * placed verbatim as the hero eyebrow — a verifiable claim the business already makes, never invented.
     */
    private function keepOriginalPositioning(array $validated, array $dna): array
    {
        $statement = trim((string) ($dna['value_proposition']['text'] ?? ''));
        if ($statement === '' || mb_strlen($statement) > 140) {
            return $validated;
        }

        $words = \App\Services\Recommendation\ProjectSignals::words($statement);
        $hero = mb_strtolower(implode(' ', [$validated['hero']['headline'] ?? '', $validated['hero']['subheadline'] ?? '', $validated['hero']['eyebrow'] ?? '']));
        $kept = count(array_filter($words, fn ($w) => str_contains($hero, $w)));

        if (count($words) >= 2 && $kept / count($words) < 0.5) {
            $validated['hero']['eyebrow'] = mb_substr($statement, 0, 80);
        }

        return $validated;
    }

    private function generateEvaluation(AnalysisRun $run, DiagnosisReport $diagnosis, DesignSpec $designSpec, array $quality): void
    {
        $requestHash = hash('sha256', (string) json_encode(['stage' => 'evaluation', 'design_spec_id' => $designSpec->id]));

        try {
            $response = $this->metaClient->evaluateDesign($diagnosis->issues_json, $designSpec->design_spec_json, ['run_id' => $run->public_id]);
            $this->modelCallLogger->logSuccess($run, 'evaluation', $requestHash, $response);

            $validated = $this->evaluationValidator->validate($response->data, $diagnosis->issues_json['issues']);
            $validated['quality_gate'] = $quality;

            EvaluationReport::create([
                'design_spec_id' => $designSpec->id,
                'evaluation_json' => $validated,
                'model_identifier' => $response->modelIdentifier,
                'prompt_version' => config('meta_api.prompt_version'),
                'created_at' => now(),
            ]);
        } catch (MetaApiException $e) {
            $this->modelCallLogger->logFailure($run, 'evaluation', $requestHash, $e->errorCode);
            // Evaluation is best-effort; the design spec remains valid without it.
        }
    }
}
