<?php

namespace App\Services\Fixtures;

use App\Models\AnalysisRun;
use App\Models\DemoFixture;
use App\Models\DesignSpec;
use App\Models\DiagnosisReport;
use App\Models\EvaluationReport;
use App\Models\EvidenceItem;
use App\Models\SitePage;
use App\Models\SiteSnapshot;
use App\Models\WebsiteDna;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Materializes a prepared demo fixture into a real AnalysisRun using the
 * exact same tables, schemas and renderer as a live run (FR-011), with
 * source_mode=fixture so every view can label it clearly.
 */
class FixtureMaterializer
{
    public function materialize(DemoFixture $fixture): AnalysisRun
    {
        return DB::transaction(function () use ($fixture) {
            $payload = $fixture->fixture_payload_json;

            $run = AnalysisRun::create([
                'submitted_url' => $payload['submitted_url'],
                'normalized_url' => $payload['homepage_url'],
                'status' => 'complete',
                'current_stage' => 'ready',
                'source_mode' => 'fixture',
                'started_at' => now(),
                'completed_at' => now(),
            ]);

            $snapshotJson = $payload['snapshot'];

            $snapshot = SiteSnapshot::create([
                'analysis_run_id' => $run->id,
                'snapshot_hash' => $snapshotJson['snapshot_hash'],
                'homepage_url' => $snapshotJson['homepage_url'],
                'page_count' => count($snapshotJson['pages']),
                'snapshot_json' => $snapshotJson,
                'warnings_json' => $snapshotJson['warnings'] ?? [],
                // Demo fixtures have no real site to capture: use the illustrative original, if rendered.
                'screenshot_path' => Storage::disk('public')->exists("fixture-originals/{$fixture->fixture_key}.png")
                    ? Storage::disk('public')->url("fixture-originals/{$fixture->fixture_key}.png") : null,
            ]);

            foreach ($snapshotJson['pages'] as $page) {
                SitePage::create([
                    'site_snapshot_id' => $snapshot->id,
                    'url' => $page['page_url'],
                    'http_status' => 200,
                    'title' => $page['title'],
                    'meta_description' => $page['meta_description'],
                    'canonical_url' => $page['canonical_url'],
                    'headings_json' => $page['headings'],
                    'visible_text' => $page['visible_text'],
                    'links_json' => $page['links'],
                    'ctas_json' => $page['ctas'],
                    'forms_json' => $page['forms'],
                    'image_alt_json' => $page['image_alt'],
                    'fetch_status' => $page['status'],
                ]);
            }

            foreach ($payload['evidence_items'] as $item) {
                EvidenceItem::create([
                    'site_snapshot_id' => $snapshot->id,
                    'evidence_key' => $item['evidence_id'],
                    'page_url' => $item['page_url'],
                    'evidence_type' => $item['evidence_type'],
                    'label' => $item['label'],
                    'excerpt' => $item['excerpt'],
                    'locator_json' => $item['locator'] ?? null,
                    'created_at' => now(),
                ]);
            }

            WebsiteDna::create([
                'analysis_run_id' => $run->id,
                'version' => 1,
                'dna_json' => $payload['website_dna'],
                'model_identifier' => 'fixture',
                'prompt_version' => 'fixture',
                'edited_by_user' => false,
            ]);

            $diagnosis = DiagnosisReport::create([
                'analysis_run_id' => $run->id,
                'version' => 1,
                'summary' => $payload['diagnosis_report']['summary'],
                'issues_json' => $payload['diagnosis_report'],
                'model_identifier' => 'fixture',
                'prompt_version' => 'fixture',
                'created_at' => now(),
            ]);

            $designSpec = DesignSpec::create([
                'analysis_run_id' => $run->id,
                'version' => 1,
                'template_key' => $payload['design_spec']['template_key'],
                'design_spec_json' => $payload['design_spec'],
                'status' => 'valid',
                'model_identifier' => 'fixture',
                'prompt_version' => 'fixture',
            ]);

            EvaluationReport::create([
                'design_spec_id' => $designSpec->id,
                'evaluation_json' => $payload['evaluation_report'],
                'model_identifier' => 'fixture',
                'prompt_version' => 'fixture',
                'created_at' => now(),
            ]);

            return $run;
        });
    }
}
