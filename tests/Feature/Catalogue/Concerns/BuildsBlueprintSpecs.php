<?php

namespace Tests\Feature\Catalogue\Concerns;

use App\Models\AnalysisRun;
use App\Models\DesignSelection;
use App\Models\DesignSpec;
use App\Models\WebsiteAsset;
use App\Models\WebsiteDna;
use App\Services\Blueprint\DesignSpecV15;

/** Builds a project with Website DNA, assets and a validated blueprint (v1.5) DesignSpec. */
trait BuildsBlueprintSpecs
{
    protected function dna(array $extra = []): array
    {
        return array_merge([
            'industry' => 'Dental practice', 'business_summary' => 'A family dental practice offering checkups and whitening.',
            'value_proposition' => ['text' => 'Gentle family dentistry.', 'evidence_ids' => ['ev1'], 'confidence' => 'high'],
            'tone_of_voice' => ['warm'], 'offerings' => [['name' => 'Checkups', 'description' => 'Routine exams']],
            'trust_signals' => [['type' => 'testimonial', 'description' => 'Great care', 'evidence_ids' => ['ev2']]],
            'primary_conversion_goal' => 'booking',
        ], $extra);
    }

    protected function legacySpec(array $over = []): array
    {
        $item = fn ($t, $d, $extra = []) => array_merge(['title' => $t, 'description' => $d, 'label' => null, 'icon' => null, 'asset_key' => null], $extra);

        return array_replace_recursive([
            'template_key' => 'modern_corporate', 'business_name' => 'Brightleaf Dental', 'primary_goal' => 'booking',
            'brand' => ['color_tokens' => ['primary' => 'ink', 'secondary' => 'slate', 'accent' => 'amber', 'background' => 'white', 'text' => 'ink']],
            'hero' => ['eyebrow' => 'Family dentistry', 'headline' => 'Gentle care for the whole family', 'subheadline' => 'Checkups, whitening and emergency care.', 'primary_cta_label' => 'Book an appointment', 'primary_cta_action' => 'booking', 'secondary_cta_label' => 'Our services', 'image_asset_key' => null],
            'sections' => [
                ['section_key' => 'offerings', 'heading' => 'Our services', 'body' => 'Everything your smile needs.', 'items' => [$item('Checkups', 'Routine exams and cleaning.', ['label' => 'Preventive']), $item('Whitening', 'Brighter smiles, safely.'), $item('Emergency care', 'Same-day appointments.')], 'source_evidence_ids' => ['ev1'], 'addresses_issue_ids' => []],
                ['section_key' => 'value_props', 'heading' => 'Why families choose us', 'body' => null, 'items' => [$item('Gentle', 'Anxiety-friendly visits.', ['icon' => 'heart']), $item('Local', 'Part of the community.', ['icon' => 'map-pin']), $item('Flexible', 'Evening appointments.', ['icon' => 'clock'])], 'source_evidence_ids' => ['ev1'], 'addresses_issue_ids' => []],
                ['section_key' => 'testimonials', 'heading' => 'Patient stories', 'body' => null, 'items' => [$item('A. Patient', 'Great care and a friendly team.', ['label' => 'Patient'])], 'source_evidence_ids' => ['ev2'], 'addresses_issue_ids' => []],
                ['section_key' => 'contact', 'heading' => 'Book your visit', 'body' => 'We would love to meet you.', 'items' => [], 'source_evidence_ids' => [], 'addresses_issue_ids' => []],
            ],
            'footer' => ['summary' => 'Serving the neighbourhood since 2009.', 'contact_text' => 'hello@brightleaf.example · +1 555 010 2030', 'cta_label' => 'Book now'],
        ], $over);
    }

    protected function makeRun(array $dna = null, array $assets = []): AnalysisRun
    {
        $run = AnalysisRun::create(['public_id' => 'run_'.uniqid(), 'submitted_url' => 'https://brightleaf.example', 'normalized_url' => 'https://brightleaf.example', 'idempotency_key' => uniqid(), 'status' => 'completed', 'current_stage' => 'done', 'source_mode' => 'live']);
        WebsiteDna::create(['analysis_run_id' => $run->id, 'version' => 1, 'dna_json' => $dna ?? $this->dna(), 'model_identifier' => 't', 'prompt_version' => 'v1']);
        foreach ($assets as $key => $type) {
            WebsiteAsset::create(['analysis_run_id' => $run->id, 'asset_key' => $key, 'source_page_url' => 'https://brightleaf.example', 'original_url' => "https://brightleaf.example/{$key}.jpg", 'stored_path' => "assets/{$key}.jpg", 'asset_type' => $type, 'relevance_score' => 0.9, 'created_at' => now()]);
        }

        return $run;
    }

    protected function selection(AnalysisRun $run, string $direction = 'trusted-expertise', string $blueprint = 'local-services-01'): DesignSelection
    {
        return DesignSelection::create(['analysis_run_id' => $run->id, 'direction_key' => $direction, 'direction_version' => 1, 'blueprint_id' => $blueprint, 'blueprint_version' => 1, 'category_id' => 'services', 'preferences_json' => ['preserve_logo' => true], 'selected_at' => now(), 'status' => 'generating']);
    }

    protected function specFor(AnalysisRun $run, array $json): DesignSpec
    {
        return DesignSpec::create(['analysis_run_id' => $run->id, 'version' => 1, 'template_key' => 'modern_corporate', 'design_spec_json' => $json, 'status' => 'valid']);
    }

    protected function attach(AnalysisRun $run, array $json, ?DesignSelection $sel = null): array
    {
        $spec = $this->specFor($run, $json);

        return [app(DesignSpecV15::class)->attach($spec, $sel ?? $this->selection($run)), $spec->fresh()];
    }
}
