<?php

namespace App\Services\MetaApi;

use App\DTO\MetaApi\MetaStageResponse;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\Exceptions\MetaApiNotConfiguredException;

/**
 * The only application contract allowed to talk to the Meta Model API
 * (architecture_rule). Every reasoning stage sends the minimum validated
 * context it needs and gets back a normalized, provider-agnostic response.
 * Callers must always validate `data` against the relevant schema — this
 * contract makes no guarantee about what the provider actually returned.
 */
interface MetaModelClient
{
    /**
     * @throws MetaApiNotConfiguredException|MetaApiException
     */
    public function generateWebsiteDna(array $evidencePack, array $context): MetaStageResponse;

    /**
     * @throws MetaApiNotConfiguredException|MetaApiException
     */
    public function generateDiagnosis(array $evidencePack, array $approvedDna, array $context): MetaStageResponse;

    /**
     * Design Trend Intelligence (self-evolving design taste): uses the
     * verified web_search built-in tool to ground pattern synthesis in
     * real current gallery entries rather than only training knowledge.
     *
     * @throws MetaApiNotConfiguredException|MetaApiException
     */
    public function scoutDesignTrends(array $sourceDomains, array $context): MetaStageResponse;

    /**
     * PRD upgrade v1.2 website_intelligence_engine.visual_profile +
     * asset_preservation_intelligence.model_classification_fields, combined
     * into one call since both consume the same evidence/asset context.
     *
     * @throws MetaApiNotConfiguredException|MetaApiException
     */
    public function generateVisualIntelligence(array $evidencePack, array $deterministicVisualClues, array $assetInventory, array $context): MetaStageResponse;

    /**
     * PRD upgrade v1.2 design_intelligence_engine.design_selection_algorithm
     * step "Meta Model API ranks eligible directions". Laravel has already
     * narrowed $eligibleDirections to is_selectable=true candidates.
     *
     * @throws MetaApiNotConfiguredException|MetaApiException
     */
    public function rankDesignDirections(array $dna, array $visualProfile, array $diagnosisReport, array $eligibleDirections, array $context): MetaStageResponse;

    /**
     * REDO v1.6: evidence-grounded positioning profile claims. The application recomputes every claim's status
     * from the cited evidence — the model's own labels are never trusted.
     *
     * @throws MetaApiNotConfiguredException|MetaApiException
     */
    public function generatePositioningProfile(array $evidencePack, array $dna, array $deterministicClaims, array $context): MetaStageResponse;

    /**
     * REDO v1.6: up to three differentiated hero-copy options with per-criterion scores (the application computes
     * the weighted total and enforces the hard gates).
     *
     * @throws MetaApiNotConfiguredException|MetaApiException
     */
    public function generateHeroCopyOptions(array $input, array $context): MetaStageResponse;

    /**
     * REDO v1.6: rewrite one hero copy option to fix a named problem (e.g. too long to fit) without changing its meaning.
     *
     * @throws MetaApiNotConfiguredException|MetaApiException
     */
    public function refineHeroCopy(array $input, array $context): MetaStageResponse;

    /**
     * @throws MetaApiNotConfiguredException|MetaApiException
     */
    public function generateDesignSpec(array $approvedDna, array $diagnosisReport, array $context): MetaStageResponse;

    /**
     * @throws MetaApiNotConfiguredException|MetaApiException
     */
    public function evaluateDesign(array $diagnosisReport, array $validatedDesignSpec, array $context): MetaStageResponse;

    /**
     * @throws MetaApiNotConfiguredException|MetaApiException
     */
    public function generateNextRedoHypothesis(array $signalSummary, array $context): MetaStageResponse;

    /**
     * Admin "Design Intelligence" tool: captures a reusable Design
     * Direction recipe (colors, typography, traits, closest matching
     * template_key) from a reference site's approved Website DNA.
     *
     * @throws MetaApiNotConfiguredException|MetaApiException
     */
    public function generateDesignDirectionRecipe(array $approvedDna, array $context): MetaStageResponse;

    /**
     * Converts a DESIGN.md style reference (colors with roles, typography, spacing, components, layout, do's and don'ts) into the
     * same recipe shape the website-capture produces.
     *
     * @param  array<string, mixed>  $context
     *
     * @throws MetaApiNotConfiguredException|MetaApiException
     */
    public function generateDesignDirectionFromDesignMd(string $designMd, array $context): MetaStageResponse;

    /**
     * Vision-based visual QA (PRD v1.4). Not every provider supports image
     * input — unsupported providers throw MetaApiNotConfiguredException,
     * which callers must treat as "skip this layer", not a hard failure.
     *
     * @param  array<string, string>  $imagesByViewport  viewport label => raw PNG bytes
     *
     * @throws MetaApiNotConfiguredException|MetaApiException
     */
    public function evaluateScreenshots(array $imagesByViewport, array $designSpecSummary, array $context): MetaStageResponse;
}
