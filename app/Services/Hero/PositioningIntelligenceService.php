<?php

namespace App\Services\Hero;

use App\Models\AnalysisRun;
use App\Models\PositioningEvidence;
use App\Models\PositioningProfile;
use App\Models\SitePage;
use App\Models\User;
use App\Services\MetaApi\EvidencePackFormatter;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\MetaModelClient;
use App\Services\Orchestration\ModelCallLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Builds the evidence-grounded positioning profile BEFORE any hero copy is written (PRD v1.6).
 *
 * The one rule that matters: a claim's STATUS is computed here from the evidence it cites — never accepted from
 * the model. A claim is `verified_source` only if its significant words (and every number in it) are actually
 * present in source text; otherwise it is `inferred` and can guide recommendations but never appear as a fact.
 * Cross-page disagreements (founding year, counts) are detected deterministically and the disputed claims are
 * marked `contradicted` and excluded from copy until the user resolves them.
 */
class PositioningIntelligenceService
{
    private const COUNTRIES_FOR_EXTRACTION = ['China', 'Malaysia', 'Thailand', 'Singapore', 'Indonesia', 'Vietnam', 'Philippines', 'India', 'Japan', 'Korea', 'Taiwan', 'Hong Kong', 'Australia', 'New Zealand', 'United States', 'USA', 'Canada', 'Mexico', 'Brazil', 'United Kingdom', 'UK', 'Germany', 'France', 'Italy', 'Spain', 'Netherlands', 'Poland', 'Turkey', 'United Arab Emirates', 'UAE', 'Saudi Arabia', 'Qatar', 'Egypt', 'South Africa', 'Nigeria', 'Kenya', 'Pakistan', 'Bangladesh', 'Sri Lanka', 'Cambodia', 'Myanmar'];

    private const FIELD_FOR_MODEL_KEY = [
        'business_model' => 'business_model', 'target_audiences' => 'audience', 'core_offerings' => 'offering', 'customer_problems' => 'problem',
        'customer_benefits' => 'benefit', 'differentiators' => 'differentiator', 'geographic_scope' => 'geography', 'proof_points' => 'proof',
    ];

    public function __construct(
        private readonly MetaModelClient $model,
        private readonly ModelCallLogger $logger,
    ) {}

    public function build(AnalysisRun $run, bool $useModel = true): PositioningProfile
    {
        $dna = $run->currentWebsiteDna()?->dna_json ?? throw new RuntimeException('Website DNA must be ready before a positioning profile can be built.');
        $snapshot = $run->siteSnapshot;
        $pages = $snapshot ? $snapshot->pages()->orderBy('id')->get() : collect();
        $evidence = $snapshot ? $snapshot->evidenceItems()->get() : collect();

        $ctx = new class
        {
            /** @var array<int, array> */
            public array $claims = [];

            public array $unknowns = [];

            public array $notes = [];
        };
        $support = $this->supportTexts($pages, $evidence);

        $this->fromDna($ctx, $dna, $support, $pages, $evidence);
        $this->fromText($ctx, $pages, $evidence);

        $sourceMode = 'dna';
        $modelId = null;
        $brandVoice = isset($dna['tone_of_voice']) ? implode(', ', array_slice((array) $dna['tone_of_voice'], 0, 3)) : null;
        $goal = $dna['primary_conversion_goal'] ?? 'unknown';
        $modelContradictions = [];

        if ($useModel) {
            try {
                [$modelClaims, $meta, $modelId] = $this->callModel($run, $snapshot, $dna, $ctx->claims, $evidence);
                $this->mergeModelClaims($ctx, $modelClaims, $support, $pages, $evidence);
                $brandVoice = $meta['brand_voice'] ?: $brandVoice;
                $goal = ($meta['goal'] && $meta['goal'] !== 'unknown') ? $meta['goal'] : $goal;
                $ctx->unknowns = array_merge($ctx->unknowns, array_map(fn ($u) => ['field' => 'model', 'note' => (string) $u], $meta['unknowns']));
                $modelContradictions = $meta['contradictions'];
                $sourceMode = 'dna+model';
            } catch (MetaApiException $e) {
                $ctx->notes[] = 'Model enrichment was unavailable; this profile uses the Website DNA and direct text extraction only.';
                Log::info('Positioning model enrichment skipped', ['run' => $run->public_id, 'reason' => $e->getMessage()]);
            }
        }

        $contradictions = $this->detectContradictions($ctx);
        foreach ($modelContradictions as $c) {
            $contradictions[] = ['topic' => $c['topic'], 'values' => $c['values'], 'sources' => $c['evidence_ids'], 'origin' => 'model', 'status' => 'open'];
        }

        $existingHero = $this->existingHero($pages);
        $ctx->unknowns = array_merge($ctx->unknowns, $this->missingElements($ctx));

        // Profile and evidence are written together or not at all: a half-built profile must never become the current one.
        $profile = DB::transaction(function () use ($run, $ctx, $contradictions, $existingHero, $brandVoice, $goal, $sourceMode, $modelId) {
            $profile = PositioningProfile::create([
                'analysis_run_id' => $run->id,
                'version' => (int) $run->positioningProfiles()->max('version') + 1,
                'unknowns_json' => $ctx->unknowns,
                'contradictions_json' => $contradictions,
                'existing_hero_json' => $existingHero,
                'brand_voice' => $brandVoice,
                'conversion_goal' => $goal,
                'status' => 'building',
                'source_mode' => $sourceMode,
                'model_identifier' => $modelId,
                'created_at' => now(),
            ]);

            $counters = [];
            foreach ($ctx->claims as $claim) {
                $n = $counters[$claim['field']] = ($counters[$claim['field']] ?? 0) + 1;
                PositioningEvidence::create([
                    'positioning_profile_id' => $profile->id, 'claim_key' => "{$claim['field']}.{$n}", 'field' => $claim['field'],
                    'claim_value' => $claim['value'], 'status' => $claim['status'], 'confidence' => $claim['confidence'],
                    'source_url' => $claim['source_url'], 'source_page_id' => $claim['source_page_id'],
                    'evidence_keys_json' => $claim['evidence_keys'], 'evidence_summary' => $claim['summary'], 'created_at' => now(),
                ]);
            }

            return $profile;
        });

        return $this->refreshSummaries($profile->fresh());
    }

    /**
     * User correction (HERO-WF-01): confirm, edit, reject, add, or resolve a contradiction by choosing a value.
     *
     * @param  'confirm'|'edit'|'reject'|'add'|'resolve'  $action
     */
    public function applyCorrection(PositioningProfile $profile, string $action, ?string $claimKey = null, ?string $value = null, ?string $field = null, ?string $topic = null, ?User $user = null): PositioningProfile
    {
        $value = $value !== null ? TextTools::fit($value, 400) : null;

        switch ($action) {
            case 'confirm':
                $claim = $this->claim($profile, $claimKey);
                $claim->update(['status' => 'user_confirmed', 'confidence' => 'high', 'user_confirmed_at' => now()]);
                break;
            case 'edit':
                $claim = $this->claim($profile, $claimKey);
                $value === null || $value === '' ? throw new RuntimeException('A value is required.') : null;
                $claim->update(['claim_value' => $value, 'status' => 'user_confirmed', 'confidence' => 'high', 'evidence_summary' => 'Provided by the user.', 'user_confirmed_at' => now()]);
                break;
            case 'reject':
                $this->claim($profile, $claimKey)->update(['status' => 'unknown']);
                break;
            case 'add':
                (in_array($field, ['category', 'business_model', 'offering', 'audience', 'benefit', 'problem', 'differentiator', 'geography', 'proof', 'years', 'locations', 'certification', 'metric'], true) && $value) || throw new RuntimeException('A field and value are required.');
                $n = $profile->evidence()->where('field', $field)->count() + 1;
                PositioningEvidence::create(['positioning_profile_id' => $profile->id, 'claim_key' => "{$field}.u{$n}", 'field' => $field, 'claim_value' => $value, 'status' => 'user_confirmed', 'confidence' => 'high', 'evidence_summary' => 'Provided by the user.', 'user_confirmed_at' => now(), 'created_at' => now()]);
                break;
            case 'resolve': // choose the correct value for a disputed claim: it becomes confirmed, its rivals unknown
                $claim = $this->claim($profile, $claimKey);
                $claim->update(['status' => 'user_confirmed', 'confidence' => 'high', 'user_confirmed_at' => now()]);
                $profile->evidence()->where('field', $claim->field)->where('status', 'contradicted')->where('id', '!=', $claim->id)->update(['status' => 'unknown']);
                $remaining = collect($profile->contradictions_json ?? [])->map(function ($c) use ($topic, $claim) {
                    return ($topic !== null && ($c['topic'] ?? null) === $topic) || ($c['field'] ?? null) === $claim->field ? array_merge($c, ['status' => 'resolved']) : $c;
                })->all();
                $profile->update(['contradictions_json' => $remaining]);
                break;
            default:
                throw new RuntimeException("Unknown correction action [{$action}].");
        }

        return $this->refreshSummaries($profile->fresh());
    }

    /** Keeps the profile's summary columns and status in step with its evidence ledger. */
    public function refreshSummaries(PositioningProfile $profile): PositioningProfile
    {
        $claims = $profile->evidence()->get();
        $list = fn (string $f) => $claims->where('field', $f)->map(fn ($c) => ['claim_key' => $c->claim_key, 'value' => $c->claim_value, 'status' => $c->status])->values()->all();
        $best = fn (string $f) => $claims->where('field', $f)->sortBy(fn ($c) => array_search($c->status, ['user_confirmed', 'verified_source', 'inferred', 'unknown', 'contradicted'], true))->first();

        $models = $claims->where('field', 'business_model')->filter->isFactual()->pluck('claim_value')->unique()->values()->all();
        $openContradictions = collect($profile->contradictions_json ?? [])->where('status', 'open')->count();
        $category = $best('category');

        $profile->update([
            'business_category' => $category && $category->status !== 'unknown' ? TextTools::fit($category->claim_value, 250) : null, // column is varchar(255)
            'business_model' => in_array('manufacturer', $models, true) && (in_array('supplier', $models, true))
                ? 'supplier_and_manufacturer' : (isset($models[0]) ? TextTools::fit($models[0], 60) : null), // column is varchar(64); the full wording stays on the claim
            'target_audiences_json' => $list('audience'), 'core_offerings_json' => $list('offering'),
            'customer_problems_json' => $list('problem'), 'customer_benefits_json' => $list('benefit'),
            'differentiators_json' => $list('differentiator'),
            'geographic_scope_json' => $list('geography'),
            'proof_points_json' => array_merge($list('proof'), $list('certification'), $list('years'), $list('locations'), $list('metric')),
            'status' => ($openContradictions > 0 || ! $category || ! $category->isFactual()) ? 'needs_confirmation' : 'ready',
        ]);

        return $profile->fresh();
    }

    // ------------------------------------------------------------------ claim construction

    /** @return array<string, string> evidence_key => text (plus page-level text under "page:{id}") */
    private function supportTexts(Collection $pages, Collection $evidence): array
    {
        $out = [];
        foreach ($evidence as $e) {
            $out[$e->evidence_key] = trim(($e->label ? $e->label.'. ' : '').$e->excerpt);
        }
        foreach ($pages as $p) {
            $out['page:'.$p->id] = trim(implode('. ', (array) ($p->headings_json ?? [])).'. '.$p->title.'. '.$p->meta_description.'. '.$p->visible_text);
        }

        return $out;
    }

    /**
     * The status computation. Only text that actually supports the claim can make it verified.
     *
     * @return array{status: string, confidence: string, keys: string[], summary: ?string, url: ?string, page_id: ?int}
     */
    private function ground(string $value, array $citedKeys, array $support, Collection $pages, Collection $evidence): array
    {
        $candidates = [];
        foreach ($citedKeys as $k) {
            if (isset($support[$k])) {
                $candidates[$k] = $support[$k];
            }
        }
        $joinedCited = implode(' ', $candidates);
        $coverage = $joinedCited !== '' ? TextTools::coverage($value, $joinedCited) : 0.0;
        $best = $coverage >= 0.6 ? array_keys($candidates) : [];
        $bestText = $joinedCited;

        if ($coverage < 0.6) { // look for the best single supporting excerpt anywhere on the site
            foreach ($support as $k => $text) {
                $c = TextTools::coverage($value, $text);
                if ($c > $coverage) {
                    [$coverage, $best, $bestText] = [$c, [$k], $text];
                }
            }
        }

        $numbersOk = array_diff(TextTools::numbers($value), TextTools::numbers($bestText)) === [];
        $status = ($coverage >= 0.6 && $numbersOk) ? 'verified_source' : 'inferred';
        $confidence = $status === 'verified_source' ? ($coverage >= 0.85 ? 'high' : 'medium') : ($coverage >= 0.3 ? 'low' : 'low');

        $evidenceKeys = array_values(array_filter($best, fn ($k) => ! str_starts_with($k, 'page:')));
        $pageId = null;
        foreach ($best as $k) {
            if (str_starts_with($k, 'page:')) {
                $pageId = (int) substr($k, 5);
            }
        }
        $first = $evidenceKeys[0] ?? null;
        $url = $first ? $evidence->firstWhere('evidence_key', $first)?->page_url : ($pageId ? $pages->firstWhere('id', $pageId)?->url : null);
        $summary = $status === 'verified_source' || $coverage >= 0.3 ? $this->excerptAround($bestText, $value) : null;

        return ['status' => $status, 'confidence' => $confidence, 'keys' => $evidenceKeys, 'summary' => $summary, 'url' => $url, 'page_id' => $pageId];
    }

    /** The sentence of $text that best matches $value. */
    private function excerptAround(string $text, string $value): ?string
    {
        $best = null;
        $bestScore = 0.0;
        foreach (TextTools::sentences($text) as $s) {
            $c = TextTools::coverage($value, $s);
            if ($c > $bestScore) {
                [$bestScore, $best] = [$c, $s];
            }
        }

        return $best ? TextTools::fit($best, 240) : null;
    }

    private function addClaim(object $ctx, string $field, string $value, array $grounded, ?string $meta = null): void
    {
        $value = TextTools::fit($value, 400);
        if ($value === '') {
            return;
        }
        foreach ($ctx->claims as $i => $existing) { // de-duplicate: keep the better-supported version
            if ($existing['field'] === $field && TextTools::overlap($existing['value'], $value) >= 0.7) {
                $rank = ['user_confirmed' => 4, 'verified_source' => 3, 'inferred' => 2, 'unknown' => 1, 'contradicted' => 0];
                if (($rank[$grounded['status']] ?? 0) > ($rank[$existing['status']] ?? 0)) {
                    $ctx->claims[$i] = $this->row($field, $value, $grounded, $meta);
                }

                return;
            }
        }
        $ctx->claims[] = $this->row($field, $value, $grounded, $meta);
    }

    private function row(string $field, string $value, array $g, ?string $meta = null): array
    {
        return ['meta' => $meta, 'field' => $field, 'value' => $value, 'status' => $g['status'], 'confidence' => $g['confidence'], 'evidence_keys' => $g['keys'],
            'summary' => $g['summary'], 'source_url' => $g['url'], 'source_page_id' => $g['page_id']];
    }

    // ------------------------------------------------------------------ sources of claims

    private function fromDna(object $ctx, array $dna, array $support, Collection $pages, Collection $evidence): void
    {
        $g = fn (string $v, array $ids) => $this->ground($v, $ids, $support, $pages, $evidence);

        if (! empty($dna['industry'])) {
            $this->addClaim($ctx, 'category', (string) $dna['industry'], $g((string) $dna['industry'], []));
        }
        foreach ((array) ($dna['offerings'] ?? []) as $o) {
            $name = trim((string) ($o['name'] ?? ''));
            $name !== '' && $this->addClaim($ctx, 'offering', $name, $g($name.' '.($o['description'] ?? ''), (array) ($o['evidence_ids'] ?? [])));
        }
        foreach ((array) ($dna['target_audience'] ?? []) as $a) {
            $seg = trim((string) ($a['segment'] ?? ''));
            $seg !== '' && $this->addClaim($ctx, 'audience', $seg, $g($seg, (array) ($a['evidence_ids'] ?? [])));
            foreach ((array) ($a['needs'] ?? []) as $need) {
                is_string($need) && $this->addClaim($ctx, 'problem', $need, $g($need, (array) ($a['evidence_ids'] ?? [])));
            }
        }
        $vp = $dna['value_proposition']['text'] ?? null;
        $vp && $this->addClaim($ctx, 'benefit', (string) $vp, $g((string) $vp, (array) ($dna['value_proposition']['evidence_ids'] ?? [])));
        foreach ((array) ($dna['trust_signals'] ?? []) as $t) {
            $d = trim((string) ($t['description'] ?? ''));
            $d !== '' && $this->addClaim($ctx, ($t['type'] ?? '') === 'credential' ? 'certification' : 'proof', $d, $g($d, (array) ($t['evidence_ids'] ?? [])));
        }
    }

    /** Facts that can be read straight out of page text with high precision: years, counts, certifications, geography, business model. */
    private function fromText(object $ctx, Collection $pages, Collection $evidence): void
    {
        foreach ($pages as $page) {
            /** @var SitePage $page */
            $text = (string) $page->visible_text;
            foreach (TextTools::sentences($text) as $s) {
                $this->extractYear($ctx, $s, $page, $evidence);
                $this->extractCounts($ctx, $s, $page, $evidence);
                $this->extractCertification($ctx, $s, $page, $evidence);
                $this->extractGeography($ctx, $s, $page, $evidence);
                $this->extractBusinessModel($ctx, $s, $page, $evidence);
            }
        }
    }

    private function direct(object $ctx, string $field, string $value, string $sentence, SitePage $page, Collection $evidence, string $meta = ''): void
    {
        $key = $evidence->first(fn ($e) => str_contains((string) $e->excerpt, mb_substr($sentence, 0, 60)))?->evidence_key;
        $this->addClaim($ctx, $field, $value, [
            'status' => 'verified_source', 'confidence' => 'high', 'keys' => $key ? [$key] : [], 'summary' => TextTools::fit($sentence, 240),
            'url' => $page->url, 'page_id' => $page->id,
        ], $meta !== '' ? $meta : null);
    }

    private function extractYear(object $ctx, string $s, SitePage $page, Collection $evidence): void
    {
        if (! preg_match('/\b(19[5-9]\d|20[0-3]\d)\b/', $s, $m) || ! preg_match('/\b(since|established|founded|started|began|incorporated|operations?)\b/i', $s)) {
            return;
        }
        if (preg_match('/\b(plant|factory|branch(es)?|offices?|subsidiary|division|facility|warehouse|certif\w*|iso)\b/i', $s)) {
            return; // not the company's own founding
        }
        $this->direct($ctx, 'years', "Operating since {$m[1]}", $s, $page, $evidence, "year:{$m[1]}");
    }

    private function extractCounts(object $ctx, string $s, SitePage $page, Collection $evidence): void
    {
        if (! preg_match_all('/\b(\d{1,3}(?:,\d{3})*)\s+(?:\w+\s+)?(branches|offices|locations|stores|countries|employees|staff|customers|clients|projects|factories|plants)\b/i', $s, $mm, PREG_SET_ORDER)) {
            return;
        }
        foreach ($mm as $m) {
            $unit = mb_strtolower($m[2]);
            $field = in_array($unit, ['branches', 'offices', 'locations', 'stores', 'factories', 'plants'], true) ? 'locations' : ($unit === 'countries' ? 'geography' : 'metric');
            $this->direct($ctx, $field, "{$m[1]} {$unit}", $s, $page, $evidence, 'count:'.($field === 'geography' ? 'countries' : $unit).':'.str_replace(',', '', $m[1]));
        }
    }

    private function extractCertification(object $ctx, string $s, SitePage $page, Collection $evidence): void
    {
        if (preg_match('/\bISO\s*\d{4,5}(?::\d{4})?\b/i', $s, $m)) {
            $this->direct($ctx, 'certification', strtoupper(preg_replace('/\s+/', ' ', $m[0])), $s, $page, $evidence);
        }
    }

    private function extractGeography(object $ctx, string $s, SitePage $page, Collection $evidence): void
    {
        if (! preg_match('/\b(branch(es)?|operations?|offices?|serv(e|ing|es)|presence|markets?|based in|across|customers? in|countries)\b/i', $s)) {
            return;
        }
        $found = array_values(array_filter(self::COUNTRIES_FOR_EXTRACTION, fn ($c) => preg_match('/\b'.preg_quote($c, '/').'\b/', $s)));
        if ($found) {
            $this->direct($ctx, 'geography', implode(', ', $found), $s, $page, $evidence);
        }
    }

    private function extractBusinessModel(object $ctx, string $s, SitePage $page, Collection $evidence): void
    {
        $map = [
            'manufacturer' => '/\b(manufactur(e|es|ing|er)|production (plant|facility)|factory|we produce)\b/i',
            'supplier' => '/\b(trading company|supply of|we supply|supplying|supplier|supplies|distribut(e|or|ion)|wholesale)\b/i',
            'retailer' => '/\b(add to cart|buy online|our shop|our store|online store)\b/i',
            'service_provider' => '/\b(consulting|consultancy|advisory|we provide services|our services include)\b/i',
        ];
        foreach ($map as $model => $re) {
            if (preg_match($re, $s) && ! preg_match('/\b(not|no longer|never)\s+(a\s+)?(manufactur|supplier|distribut)/i', $s)) {
                $this->direct($ctx, 'business_model', $model, $s, $page, $evidence);
            }
        }
    }

    // ------------------------------------------------------------------ model enrichment

    /** @return array{0: array<string, array>, 1: array, 2: ?string} */
    private function callModel(AnalysisRun $run, $snapshot, array $dna, array $claims, Collection $evidence): array
    {
        $pack = app(EvidencePackFormatter::class)->build($snapshot?->snapshot_json ?? [], $evidence->map(fn ($e) => [
            'evidence_key' => $e->evidence_key, 'page_url' => $e->page_url, 'evidence_type' => $e->evidence_type, 'label' => $e->label, 'excerpt' => $e->excerpt,
        ])->all());
        $compact = array_map(fn ($c) => ['field' => $c['field'], 'value' => $c['value'], 'status' => $c['status']], $claims);
        $hash = hash('sha256', (string) json_encode(['stage' => 'positioning_profile', 'run' => $run->public_id, 'claims' => $compact]));

        try {
            $response = $this->model->generatePositioningProfile($pack, $dna, $compact, ['run_id' => $run->public_id]);
            $this->logger->logSuccess($run, 'positioning_profile', $hash, $response);
        } catch (MetaApiException $e) {
            $this->logger->logFailure($run, 'positioning_profile', $hash, $e->errorCode);

            throw $e;
        }

        $d = $response->data;
        $out = [];
        if (is_array($d['business_category'] ?? null) && ! empty($d['business_category']['value'])) {
            $out['category'] = [$d['business_category']];
        }
        foreach (self::FIELD_FOR_MODEL_KEY as $key => $field) {
            $out[$field] = array_values(array_filter((array) ($d[$key] ?? []), fn ($c) => is_array($c) && ! empty($c['value'])));
        }

        return [$out, [
            'brand_voice' => is_string($d['brand_voice'] ?? null) ? TextTools::fit($d['brand_voice'], 120) : null,
            'goal' => in_array($d['primary_conversion_goal'] ?? null, ['lead', 'booking', 'purchase', 'inquiry', 'awareness', 'unknown'], true) ? $d['primary_conversion_goal'] : 'unknown',
            'unknowns' => array_values(array_filter((array) ($d['unknowns'] ?? []), 'is_string')),
            'contradictions' => array_values(array_filter(array_map(fn ($c) => is_array($c) && ! empty($c['topic']) ? ['topic' => TextTools::fit((string) $c['topic'], 120), 'values' => array_map(fn ($v) => TextTools::fit((string) $v, 120), (array) ($c['values'] ?? [])), 'evidence_ids' => array_values(array_filter((array) ($c['evidence_ids'] ?? []), 'is_string'))] : null, (array) ($d['contradictions'] ?? [])))),
        ], $response->modelIdentifier];
    }

    private function mergeModelClaims(object $ctx, array $byField, array $support, Collection $pages, Collection $evidence): void
    {
        foreach ($byField as $field => $claims) {
            foreach ($claims as $c) {
                // The model's confidence is advisory; the status comes from grounding against the cited evidence.
                $g = $this->ground((string) $c['value'], array_values(array_filter((array) ($c['evidence_ids'] ?? []), 'is_string')), $support, $pages, $evidence);
                $this->addClaim($ctx, $field, (string) $c['value'], $g);
            }
        }
    }

    // ------------------------------------------------------------------ contradictions, unknowns, existing hero

    /** Disagreeing founding years / counts across DIFFERENT pages. Disputed claims become `contradicted`. */
    private function detectContradictions(object $ctx): array
    {
        $groups = [];
        foreach ($ctx->claims as $i => $c) {
            if (! empty($c['meta']) && $c['status'] === 'verified_source') {
                $meta = explode(':', $c['meta']);
                $topic = $meta[0] === 'year' ? 'year' : "count:{$meta[1]}";
                $groups[$topic][$meta[0] === 'year' ? $meta[1] : $meta[2]][] = $i;
            }
        }
        $out = [];
        foreach ($groups as $topic => $values) {
            $pagesPerValue = array_map(fn ($idxs) => array_unique(array_map(fn ($i) => $ctx->claims[$i]['source_page_id'], $idxs)), $values);
            if (count($values) < 2) {
                continue;
            }
            // Different values on the SAME page are different facts (e.g. 9 branches and 12 countries); only cross-page disagreement is a conflict.
            $allPages = array_unique(array_merge(...array_values($pagesPerValue)));
            if (count($allPages) < 2) {
                continue;
            }
            $field = $ctx->claims[array_values($values)[0][0]]['field'];
            foreach ($values as $idxs) {
                foreach ($idxs as $i) {
                    $ctx->claims[$i]['status'] = 'contradicted';
                }
            }
            $out[] = ['topic' => $topic === 'year' ? 'Year established' : 'Number of '.substr($topic, 6), 'field' => $field, 'values' => array_keys($values),
                'sources' => array_values(array_unique(array_map(fn ($idxs) => $ctx->claims[$idxs[0]]['source_url'], $values))), 'origin' => 'deterministic', 'status' => 'open'];
        }

        return $out;
    }

    /** Elements the PRD requires that nothing in the sources established (never filled with invented claims). */
    private function missingElements(object $ctx): array
    {
        $have = fn (string $f) => collect($ctx->claims)->contains(fn ($c) => $c['field'] === $f && in_array($c['status'], ['verified_source', 'user_confirmed'], true));
        $out = [];
        foreach (['audience' => 'The intended customer is not stated on the site.', 'benefit' => 'No clear customer benefit is stated on the site.', 'category' => 'The business category could not be confirmed from the site text.'] as $field => $note) {
            $have($field) || $out[] = ['field' => $field, 'note' => $note];
        }

        return $out;
    }

    /** @return array{headline: ?string, subheadline: ?string, ctas: string[], source_url: ?string} */
    private function existingHero(Collection $pages): array
    {
        $home = $pages->first();
        if (! $home) {
            return ['headline' => null, 'subheadline' => null, 'ctas' => [], 'source_url' => null];
        }
        $headings = array_values(array_filter((array) $home->headings_json, 'is_string'));
        $paras = TextTools::sentences((string) $home->visible_text);
        $ctas = array_values(array_unique(array_filter(array_map('trim', (array) $home->ctas_json), fn ($c) => $c !== '' && mb_strlen($c) <= 40)));

        return ['headline' => $headings[0] ?? $home->title, 'subheadline' => $headings[1] ?? ($paras[0] ?? null), 'ctas' => array_slice($ctas, 0, 6), 'source_url' => $home->url];
    }

    private function claim(PositioningProfile $profile, ?string $key): PositioningEvidence
    {
        return $profile->evidence()->where('claim_key', (string) $key)->firstOr(fn () => throw new RuntimeException("Unknown claim [{$key}]."));
    }
}
