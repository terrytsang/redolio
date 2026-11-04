<?php

namespace Tests\Feature\Hero\Concerns;

use App\DTO\MetaApi\MetaStageResponse;
use App\Models\AnalysisRun;
use App\Models\EvidenceItem;
use App\Models\SitePage;
use App\Models\SiteSnapshot;
use App\Models\WebsiteDna;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\MetaModelClient;
use Mockery;

/** A small, ANOZ-shaped industrial supplier (illustrative test data only — never used as product defaults). */
trait BuildsPositioningSite
{
    protected function pagesDefault(): array
    {
        return [
            ['url' => 'https://supplier.test/', 'title' => 'Northgate Materials', 'headings' => ['Welcome to Northgate Materials', 'Raw materials for industry'],
                'text' => 'Northgate Materials supplies aluminium plates, stainless steel, copper and engineering plastic to manufacturers. In 2005, Northgate started its first operation as a trading company for aluminium raw material products. We have 9 branches serving customers in China, Malaysia and Thailand. Our manufacturing plant in Suzhou, established 2009, produces 6061-T651 aluminium plates. We hold ISO 9001:2015 certification.',
                'ctas' => ['Home', 'Product', 'Contact Us', 'Our Products']],
            ['url' => 'https://supplier.test/product', 'title' => 'Products', 'headings' => ['Our Products'],
                'text' => 'We supply aluminium in plate, extrusion, sheet and coil forms. We also supply engineering plastic, stainless steel, copper, brass and bronze to industrial customers.', 'ctas' => ['Request a quote']],
        ];
    }

    protected function makeSupplierRun(?array $pages = null, ?array $dnaOverride = null): AnalysisRun
    {
        $pages ??= $this->pagesDefault();
        $run = AnalysisRun::create(['public_id' => 'run_'.uniqid(), 'submitted_url' => 'https://supplier.test', 'normalized_url' => 'https://supplier.test/', 'idempotency_key' => uniqid(), 'status' => 'complete', 'current_stage' => 'ready', 'source_mode' => 'live']);
        $snap = SiteSnapshot::create(['analysis_run_id' => $run->id, 'snapshot_hash' => 'h', 'homepage_url' => $pages[0]['url'], 'page_count' => count($pages), 'snapshot_json' => ['homepage_url' => $pages[0]['url'], 'pages' => [], 'warnings' => []]]);

        foreach ($pages as $i => $p) {
            SitePage::create(['site_snapshot_id' => $snap->id, 'url' => $p['url'], 'http_status' => 200, 'title' => $p['title'], 'meta_description' => null, 'canonical_url' => null,
                'headings_json' => $p['headings'], 'visible_text' => $p['text'], 'links_json' => [], 'ctas_json' => $p['ctas'], 'forms_json' => [], 'image_alt_json' => [], 'fetch_status' => 'success']);
            foreach (\App\Services\Hero\TextTools::sentences($p['text']) as $j => $sentence) {
                EvidenceItem::create(['site_snapshot_id' => $snap->id, 'evidence_key' => "p{$i}-s{$j}", 'page_url' => $p['url'], 'evidence_type' => 'paragraph', 'label' => 'text', 'excerpt' => $sentence, 'locator_json' => [], 'created_at' => now()]);
            }
        }

        WebsiteDna::create(['analysis_run_id' => $run->id, 'version' => 1, 'model_identifier' => 't', 'prompt_version' => 'v1', 'dna_json' => $dnaOverride ?? [
            'business_name' => 'Northgate Materials', 'industry' => 'Industrial raw materials supply', 'business_summary' => 'Supplies metals and plastics to manufacturers.',
            'target_audience' => [['segment' => 'Manufacturers', 'needs' => ['Reliable raw material supply'], 'confidence' => 'medium', 'evidence_ids' => ['p0-s0']]],
            'offerings' => [['name' => 'Aluminium plates', 'description' => 'aluminium plates', 'evidence_ids' => ['p0-s0']], ['name' => 'Stainless steel', 'description' => '', 'evidence_ids' => ['p0-s0']], ['name' => 'Engineering plastic', 'description' => '', 'evidence_ids' => ['p0-s0']]],
            'value_proposition' => ['text' => 'Raw materials for industry', 'evidence_ids' => ['p0-s0'], 'confidence' => 'medium'],
            'tone_of_voice' => ['professional'], 'primary_conversion_goal' => 'inquiry', 'cta_inventory' => [['label' => 'Our Products', 'evidence_ids' => []]],
            'trust_signals' => [['type' => 'credential', 'description' => 'ISO 9001:2015 certification', 'evidence_ids' => ['p0-s4']]],
        ]]);

        return $run;
    }

    /** A model that returns the given claim payload (or fails). */
    protected function modelReturning(?array $profile, bool $fail = false): void
    {
        $model = Mockery::mock(MetaModelClient::class);
        if ($fail) {
            $model->shouldReceive('generatePositioningProfile')->andThrow(new MetaApiException('down', 'provider_down'));
        } else {
            $model->shouldReceive('generatePositioningProfile')->andReturn(new MetaStageResponse(data: $profile, modelIdentifier: 'test-model', inputTokens: 1, outputTokens: 1, latencyMs: 1));
        }
        $this->app->instance(MetaModelClient::class, $model);
    }

    protected function claim(string $value, array $ids = [], string $confidence = 'high'): array
    {
        return ['value' => $value, 'evidence_ids' => $ids, 'confidence' => $confidence];
    }

    protected function emptyModelProfile(array $over = []): array
    {
        return $over + ['business_category' => ['value' => '', 'evidence_ids' => [], 'confidence' => 'low'], 'business_model' => [], 'target_audiences' => [], 'core_offerings' => [], 'customer_problems' => [],
            'customer_benefits' => [], 'differentiators' => [], 'geographic_scope' => [], 'proof_points' => [], 'brand_voice' => 'professional', 'primary_conversion_goal' => 'inquiry', 'unknowns' => [], 'contradictions' => []];
    }
}
