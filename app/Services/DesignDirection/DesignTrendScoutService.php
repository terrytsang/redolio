<?php

namespace App\Services\DesignDirection;

use App\Models\DesignPattern;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\MetaModelClient;
use App\Services\MetaApi\Validation\DesignTrendValidator;
use App\Services\Orchestration\ModelCallLogger;
use Illuminate\Support\Str;

/**
 * Self-evolving design taste: uses the Meta API's live web_search tool to
 * ground pattern synthesis in real current gallery entries (siteinspire,
 * details.so, dribbble, aura.build), never scraping them directly — those
 * sites block plain HTTP scraping and this project does not add browser
 * automation to work around that (see docs/meta-api-verified.md). Every
 * stored pattern is a generalized, transformed description with
 * domain-level attribution only, never a reproduction of one specific
 * design (enforced by DesignTrendValidator).
 */
class DesignTrendScoutService
{
    public function __construct(
        private readonly MetaModelClient $metaClient,
        private readonly DesignTrendValidator $validator,
        private readonly ModelCallLogger $modelCallLogger,
    ) {}

    /**
     * @return array{batch_id: string, pattern_count: int, sources_reachable: string[]}
     */
    public function scout(): array
    {
        $sourceDomains = config('design_allowlist.pattern_source_domains');
        $requestHash = hash('sha256', (string) json_encode(['stage' => 'design_trend_scouting', 'at' => now()->toDateString()]));

        try {
            $response = $this->metaClient->scoutDesignTrends($sourceDomains, ['purpose' => 'self_evolving_design_taste']);
            $this->modelCallLogger->logSuccess(null, 'design_trend_scouting', $requestHash, $response);
        } catch (MetaApiException $e) {
            $this->modelCallLogger->logFailure(null, 'design_trend_scouting', $requestHash, $e->errorCode);

            throw $e;
        }

        $validated = $this->validator->validate($response->data);
        $batchId = (string) Str::uuid();
        $scoutedAt = now();

        foreach ($validated['patterns'] as $pattern) {
            DesignPattern::create([
                'pattern_type' => $pattern['pattern_type'],
                'title' => $pattern['title'],
                'description' => $pattern['description'],
                'applicable_directions_json' => $pattern['applicable_directions'],
                'source_domains_json' => $pattern['source_domains'],
                'confidence' => $pattern['confidence'],
                'scout_batch_id' => $batchId,
                'scouted_at' => $scoutedAt,
            ]);
        }

        return [
            'batch_id' => $batchId,
            'pattern_count' => count($validated['patterns']),
            'sources_reachable' => $validated['sources_reachable'],
        ];
    }
}
