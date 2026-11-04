<?php

namespace App\Services\Hero;

use App\Models\PositioningProfile;

/**
 * Diagnoses hero messaging against the ten PRD checks. Deterministic and traceable: every finding names the claims
 * it relates to and says, in plain language, what is wrong and what to do — including which content belongs in a
 * later section instead of the hero.
 */
class HeroMessagingDiagnosisService
{
    public function __construct(private readonly HeroCopyValidationService $validator) {}

    /** The hero the business has TODAY on its own website. */
    public function siteHero(PositioningProfile $profile): HeroCopy
    {
        $h = $profile->existing_hero_json ?? [];
        $cta = ($h['ctas'][0] ?? null);

        return new HeroCopy(null, (string) ($h['headline'] ?? ''), $h['subheadline'] ?? null, $cta ? ['label' => $cta, 'destination_key' => null] : null);
    }

    /**
     * @return array{findings: array<int, array{diagnosis_id: string, check: string, severity: string, finding: string, evidence_ids: string[], recommended_fix: string}>, relocate: array<int, array{content: string, belongs_in: string}>, summary: array{high: int, medium: int, low: int}}
     */
    public function diagnose(HeroCopy $copy, PositioningProfile $profile, ?string $variantId = null): array
    {
        $claims = $profile->evidence()->get();
        $factual = $claims->filter->isFactual();
        $findings = [];
        $n = 0;
        $add = function (string $check, string $severity, string $finding, array $ids, string $fix) use (&$findings, &$n) {
            $findings[] = ['diagnosis_id' => 'D'.(++$n), 'check' => $check, 'severity' => $severity, 'finding' => $finding, 'evidence_ids' => array_values(array_unique($ids)), 'recommended_fix' => $fix];
        };

        $text = $copy->allText();
        $offerings = $factual->where('field', 'offering');
        $headline = $copy->headline;
        $hw = TextTools::wordCount($headline);

        // 1. unclear-category
        $categoryWords = array_merge(TextTools::significant((string) $profile->business_category), ...$offerings->take(4)->map(fn ($o) => TextTools::significant($o->claim_value))->all());
        if ($categoryWords !== [] && TextTools::coverage(implode(' ', $categoryWords), $text) === 0.0) {
            $add('unclear-category', 'high', 'A visitor cannot tell what kind of business this is from the hero text.', $offerings->take(3)->pluck('claim_key')->all(), 'Name the category or main offering in the headline or description.');
        }

        // 2. unclear-audience
        $audiences = $factual->where('field', 'audience');
        if ($audiences->isNotEmpty() && TextTools::coverage($audiences->pluck('claim_value')->implode(' '), $text) === 0.0) {
            $add('unclear-audience', 'low', 'The intended customer is not apparent in the hero.', $audiences->pluck('claim_key')->all(), 'Mention who the offer is for in the description where it makes the offer more relevant.');
        }

        // 3. feature-dump + 9. message-overload
        $named = $offerings->filter(fn ($o) => TextTools::coverage($o->claim_value, $headline) >= 0.99 && TextTools::significant($o->claim_value) !== []);
        $spec = (bool) preg_match('/\b\d{3,4}[- ]?[A-Z]\d*\b/', $headline);
        $commas = substr_count($headline, ',') + substr_count(mb_strtolower($headline), ' & ');
        if ($named->count() >= 3 || $spec || $commas >= 3 || $hw > config('hero_layout.headline.hard_max_words')) {
            $add('feature-dump', 'high', 'The headline lists products, grades or specifications instead of communicating one proposition.', $named->pluck('claim_key')->all(), 'Keep one customer-facing proposition in the headline and move product lists and technical grades to product sections.');
        }
        $topics = (int) $spec + (int) (bool) preg_match('/\b(19|20)\d{2}\b|\b\d+\s+(branches|countries|offices|years)\b/i', $headline)
            + (int) (bool) preg_match('/\b(china|malaysia|thailand|asia|europe|worldwide|globally)\b/i', $headline) + (int) ($named->count() >= 2);
        if ($topics >= 2 || TextTools::wordCount($text) > 60) {
            $add('message-overload', 'high', 'The hero tries to communicate several messages at once.', [], 'Choose the one thing a first-time visitor most needs to understand and let later sections carry the rest.');
        }

        // 4. generic-claim
        $lower = mb_strtolower($headline);
        $generic = collect(config('hero_layout.generic_openings'))->contains(fn ($g) => str_starts_with($lower, $g)) || collect(config('hero_layout.generic_phrases'))->contains(fn ($g) => str_contains($lower, $g));
        $specific = $claims->contains(fn ($c) => in_array($c->field, ['offering', 'category'], true) && TextTools::coverage($c->claim_value, $headline) >= 0.5);
        if ($generic || ($headline !== '' && ! $specific && $hw >= 3)) {
            $add('generic-claim', 'medium', 'The headline could describe almost any company.', [], 'Use concrete language from the business: its category, offering or the customer outcome it delivers.');
        }

        // 5. unsupported-claim
        $v = $this->validator->validate($copy, $profile, [], false);
        $unsupported = collect($v['issues'])->where('gate', 'factual')->where('severity', 'fail')->reject(fn ($i) => in_array($i['code'], ['no_evidence', 'missing_primary_cta'], true));
        if ($unsupported->isNotEmpty()) {
            $add('unsupported-claim', 'high', 'The copy makes claims without evidence: '.$unsupported->pluck('message')->take(3)->implode(' '), $copy->evidenceIds, 'Remove the claim, or confirm it with a source or in the positioning profile.');
        }

        // 6. duplicate-copy
        if ($copy->description && TextTools::overlap($headline, $copy->description) >= 0.5) {
            $add('duplicate-copy', 'medium', 'The description mostly repeats the headline.', [], 'Use the description to add scope, audience or proof the headline does not already state.');
        }

        // 7. internal-jargon
        $jargon = (bool) preg_match('/\b(synerg\w+|leverag\w+|holistic|paradigm|turnkey|best-of-breed|value[- ]add\w*)\b/i', $text) || (bool) preg_match('/\b[A-Z]{2,}\d*-?[A-Z0-9]+\b/', $headline) || $spec;
        $jargon && $add('internal-jargon', 'low', 'The copy uses codes or jargon customers may not recognise.', [], 'Use plain customer language; keep codes and grades for product pages.');

        // 8. weak-cta
        $label = trim((string) ($copy->primaryCta['label'] ?? ''));
        $w = TextTools::wordCount($label);
        if ($label === '' || in_array(mb_strtolower($label), config('hero_layout.weak_ctas'), true) || $w < 2 || $w > 5) {
            $add('weak-cta', 'medium', $label === '' ? 'There is no clear next action.' : "\"{$label}\" is a vague or mismatched call to action.", [], 'Use a specific action that matches the business goal, such as "Request a Quote" or "Book a Consultation".');
        }

        // 10. layout-mismatch (preflight estimate only)
        $variant = HeroLayoutConstraints::variant($variantId);
        $est = HeroLayoutConstraints::estimateLines($headline, $variant, 'desktop');
        $preferred = HeroLayoutConstraints::preferredH1Lines($variant, 'desktop');
        if ($est > $preferred) {
            $add('layout-mismatch', 'medium', "The headline is likely to wrap to about {$est} lines in the \"{$variant}\" hero (preferred: {$preferred}).", [], 'Shorten the headline; do not shrink the type. Rendered fit is verified in the browser.');
        }

        $sev = collect($findings)->countBy('severity');

        return ['findings' => $findings, 'relocate' => $this->relocate($headline, $profile), 'summary' => ['high' => $sev['high'] ?? 0, 'medium' => $sev['medium'] ?? 0, 'low' => $sev['low'] ?? 0]];
    }

    /** What the PRD's message-separation rules say does NOT belong in the hero. */
    private function relocate(string $headline, PositioningProfile $profile): array
    {
        $out = [];
        if (preg_match('/\b\d{3,4}[- ]?[A-Z]\d*\b/', $headline, $m)) {
            $out[] = ['content' => $m[0], 'belongs_in' => 'Product details'];
        }
        if (preg_match('/\b(since\s+)?(19|20)\d{2}\b|\b\d+\s+(branches|offices|countries|years)\b/i', $headline, $m)) {
            $out[] = ['content' => $m[0], 'belongs_in' => 'Trust strip (only if verified)'];
        }
        if (preg_match('/\b(china|malaysia|thailand|singapore|indonesia|asia|europe|worldwide|globally)\b/i', $headline, $m)) {
            $out[] = ['content' => $m[0], 'belongs_in' => 'Description, only if coverage matters to the customer and is verified'];
        }
        if (preg_match('/\b(started|founded|established|history|story)\b/i', $headline, $m)) {
            $out[] = ['content' => $m[0], 'belongs_in' => 'About section'];
        }
        $listed = $profile->evidence()->where('field', 'offering')->get()->filter(fn ($o) => TextTools::coverage($o->claim_value, $headline) >= 0.99 && TextTools::significant($o->claim_value) !== []);
        if ($listed->count() >= 2) {
            $out[] = ['content' => $listed->pluck('claim_value')->implode(', '), 'belongs_in' => 'Products / services section'];
        }

        return $out;
    }
}
