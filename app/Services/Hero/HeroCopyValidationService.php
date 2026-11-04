<?php

namespace App\Services\Hero;

use App\Models\PositioningProfile;

/**
 * Deterministic hard gates for hero copy (PRD v1.6). These run in application code — the prompt asks the model to
 * behave, but nothing it returns is accepted on trust.
 *
 *  FACTUAL gate (any failure rejects the option outright; score can never override it):
 *    - every cited evidence id must resolve to a verified or user-confirmed claim of THIS profile;
 *    - numbers, years, countries, credentials, superlatives/guarantees and business-model words must be supported by
 *      verified claims — a "serving 10,000 customers" with no such claim is a hard reject;
 *    - trust signals must map to a verified claim and not say more than it does;
 *    - CTA destinations must resolve to the page that will actually be built.
 *  EDITORIAL gate (fail / warn / pass): length heuristics, generic openings, stuffed headlines, headline/description
 *    duplication, weak CTAs. Word counts are preflight heuristics; rendered fit is checked separately in the browser.
 */
class HeroCopyValidationService
{
    /**
     * @param  array<string, array{action_type: string, destination: string}>  $availableDestinations  empty = do not check destinations
     * @return array{factual_gate: string, editorial_gate: string, issues: array<int, array{gate: string, code: string, severity: string, field: string, message: string}>}
     */
    public function validate(HeroCopy $c, PositioningProfile $profile, array $availableDestinations = [], bool $requireDestinations = true): array
    {
        $issues = [];
        $add = function (string $gate, string $code, string $severity, string $field, string $message) use (&$issues) {
            $issues[] = ['gate' => $gate, 'code' => $code, 'severity' => $severity, 'field' => $field, 'message' => $message];
        };

        $this->safety($c, $add);

        $claims = $profile->evidence()->get()->keyBy('claim_key');
        $factual = $claims->filter->isFactual();
        $corpus = $factual->map(fn ($x) => $x->claim_value.' '.$x->evidence_summary)->implode(' ');
        $corpusNumbers = TextTools::numbers($corpus);

        $this->evidenceReferences($c, $claims, $add);
        $this->factualTokens($c, $factual, $corpus, $corpusNumbers, $add);
        $this->trustSignals($c, $claims, $add);
        $this->ctas($c, $availableDestinations, $requireDestinations, $add);
        $this->editorial($c, $profile, $factual, $add);

        $factualFail = collect($issues)->contains(fn ($i) => $i['gate'] === 'factual' && $i['severity'] === 'fail');
        $editorial = collect($issues)->where('gate', 'editorial');

        return [
            'factual_gate' => $factualFail ? 'fail' : 'pass',
            'editorial_gate' => $editorial->contains('severity', 'fail') ? 'fail' : ($editorial->contains('severity', 'warn') ? 'warn' : 'pass'),
            'issues' => $issues,
        ];
    }

    // ------------------------------------------------------------------ factual gate

    private function safety(HeroCopy $c, callable $add): void
    {
        if (trim($c->headline) === '') {
            $add('factual', 'missing_headline', 'fail', 'headline', 'A headline is required.');
        }
        foreach (['eyebrow' => 120, 'headline' => 240, 'description' => 600] as $field => $max) {
            $v = (string) $c->$field;
            if (preg_match('/[<>]/', $v) || stripos($v, 'javascript:') !== false) {
                $add('factual', 'unsafe_text', 'fail', $field, 'Markup or script-like text is not allowed in hero copy.');
            }
            if (mb_strlen($v) > $max) {
                $add('factual', 'field_too_long', 'fail', $field, "The {$field} exceeds {$max} characters.");
            }
        }
    }

    private function evidenceReferences(HeroCopy $c, $claims, callable $add): void
    {
        if ($c->evidenceIds === []) {
            $add('factual', 'no_evidence', 'fail', 'evidence_ids', 'The copy cites no evidence from the positioning profile.');
        }
        foreach ($c->evidenceIds as $id) {
            $claim = $claims->get($id);
            if (! $claim) {
                $add('factual', 'evidence_unresolved', 'fail', 'evidence_ids', "Evidence [{$id}] does not exist in this project's positioning profile.");
            } elseif (! $claim->isFactual()) {
                $add('factual', 'evidence_not_factual', 'fail', 'evidence_ids', "Evidence [{$id}] is {$claim->status}, so it cannot be stated as a fact.");
            }
        }
    }

    private function factualTokens(HeroCopy $c, $factual, string $corpus, array $corpusNumbers, callable $add): void
    {
        $text = implode(' ', array_filter([$c->eyebrow, $c->headline, $c->description, implode(' ', array_column($c->trustSignals, 'text'))]));

        foreach (array_diff(TextTools::numbers($text), $corpusNumbers) as $n) {
            $add('factual', 'unsupported_number', 'fail', 'text', "The number \"{$n}\" is not supported by any verified claim.");
        }

        foreach (config('hero_layout.hard_claim_words') as $w) {
            $re = '/(?<![\p{L}\p{N}])'.preg_quote($w, '/').'(?![\p{L}\p{N}])/iu';
            if (preg_match($re, $text) && ! preg_match($re, $corpus)) {
                $add('factual', 'unsupported_claim', 'fail', 'text', "\"{$w}\" is a claim (ranking, guarantee, credential or exclusivity) with no evidence behind it.");
            }
        }

        foreach (TextTools::COUNTRIES as $place) {
            $re = '/(?<![\p{L}])'.preg_quote($place, '/').'(?![\p{L}])/u';
            if (preg_match($re, $text) && ! preg_match($re, $corpus)) {
                $add('factual', 'unsupported_geography', 'fail', 'text', "\"{$place}\" is not supported as a place the business operates, serves or is based in.");
            }
        }

        $models = $factual->where('field', 'business_model')->pluck('claim_value')->map(fn ($v) => mb_strtolower($v))->all();
        $modelTerms = [
            'manufacturer' => '/\b(manufactur\w*|factory|we make|made in-house|in-house production)\b/iu',
            'retailer' => '/\b(retailer|online store|our shop|our store)\b/iu',
        ];
        foreach ($modelTerms as $model => $re) {
            if (preg_match($re, $text) && ! in_array($model, $models, true)) {
                $add('factual', 'unsupported_business_model', 'fail', 'text', "The copy implies the business is a {$model}, which the source evidence does not establish.");
            }
        }

        foreach (config('hero_layout.soft_claim_words') as $w) {
            $re = '/(?<![\p{L}])'.preg_quote($w, '/').'(?![\p{L}])/iu';
            if (preg_match($re, $text) && ! preg_match($re, $corpus)) {
                $add('factual', 'unconfirmed_soft_claim', 'warn', 'text', "\"{$w}\" is not backed by the source evidence; confirm it or use neutral wording.");
            }
        }
    }

    private function trustSignals(HeroCopy $c, $claims, callable $add): void
    {
        foreach ($c->trustSignals as $t) {
            $claim = $claims->get($t['claim_key'] ?? '');
            if (! $claim || ! $claim->isFactual()) {
                $add('factual', 'unsupported_trust_signal', 'fail', 'trust_signals', 'A trust signal does not map to a verified or user-confirmed claim.');

                continue;
            }
            $source = $claim->claim_value.' '.$claim->evidence_summary;
            if (TextTools::coverage($t['text'], $source) < 0.7 || array_diff(TextTools::numbers($t['text']), TextTools::numbers($source)) !== []) {
                $add('factual', 'overstated_trust_signal', 'fail', 'trust_signals', "The trust signal \"{$t['text']}\" says more than its source claim does.");
            }
        }
    }

    private function ctas(HeroCopy $c, array $available, bool $require, callable $add): void
    {
        if (! $c->primaryCta || trim((string) ($c->primaryCta['label'] ?? '')) === '') {
            $add('factual', 'missing_primary_cta', 'fail', 'primary_cta', 'A primary call to action is required.');

            return;
        }
        if (! $require || $available === []) {
            return;
        }
        foreach (['primary_cta' => $c->primaryCta, 'secondary_cta' => $c->secondaryCta] as $field => $cta) {
            // Resolved = one of this page's real destinations, or a link the owner already put on the page (https, validated when it was saved).
            $resolved = isset($available[$cta['destination_key'] ?? '']) || (! empty($cta['destination']) && (collect($available)->contains('destination', $cta['destination']) || preg_match('#^https://#i', (string) $cta['destination'])));
            if ($cta && ! $resolved) {
                $add('factual', 'cta_destination_unresolved', 'fail', $field, 'This call to action does not point at a section or contact method that exists on the page.');
            }
        }
    }

    // ------------------------------------------------------------------ editorial gate

    private function editorial(HeroCopy $c, PositioningProfile $profile, $factual, callable $add): void
    {
        $cfg = config('hero_layout');
        $h = $cfg['headline'];
        $hw = TextTools::wordCount($c->headline);
        $hc = mb_strlen($c->headline);

        if ($hw < $h['min_words']) {
            $add('editorial', 'headline_short', 'warn', 'headline', "The headline has {$hw} words; under {$h['min_words']} usually reads as vague.");
        }
        if ($hw > $h['hard_max_words']) {
            $add('editorial', 'headline_too_long', 'fail', 'headline', "The headline has {$hw} words (hard limit {$h['hard_max_words']}); it cannot be understood at a glance.");
        } elseif ($hw > $h['soft_max_words'] || $hc > $h['soft_max_chars']) {
            $add('editorial', 'headline_long', 'warn', 'headline', "The headline is long ({$hw} words, {$hc} characters); keep one idea and move detail to the description.");
        }

        $lower = mb_strtolower($c->headline);
        foreach ($cfg['generic_openings'] as $g) {
            if (str_starts_with($lower, $g)) {
                $add('editorial', 'generic_opening', 'fail', 'headline', "Opening with \"{$g}\" says nothing about the business.");
            }
        }
        foreach ($cfg['generic_phrases'] as $g) {
            if (str_contains(mb_strtolower($c->allText()), $g)) {
                $add('editorial', 'generic_phrase', 'warn', 'text', "\"{$g}\" is filler that could describe thousands of businesses.");
            }
        }

        // A stuffed headline: several offerings, a spec/grade code, or a long comma list.
        $offerings = $factual->where('field', 'offering')->pluck('claim_value');
        $named = $offerings->filter(fn ($o) => TextTools::coverage($o, $c->headline) >= 0.99 && TextTools::significant($o) !== [])->count();
        $spec = (bool) preg_match('/\b\d{3,4}[- ]?[A-Z]\d*\b/', $c->headline);
        $commas = substr_count($c->headline, ',') + substr_count(mb_strtolower($c->headline), ' & ');
        if ($named >= 3 || $spec || $commas >= 3) {
            $add('editorial', 'feature_dump', 'fail', 'headline', 'The headline lists products, grades or specifications instead of one proposition; move them to product sections.');
        }

        $dup = TextTools::overlap($c->headline, (string) $c->description);
        if ($c->description && $dup >= 0.6) {
            $add('editorial', 'duplicate_copy', 'fail', 'description', 'The description mostly repeats the headline instead of adding information.');
        } elseif ($c->description && $dup >= 0.4) {
            $add('editorial', 'repetitive_copy', 'warn', 'description', 'The description overlaps heavily with the headline.');
        }

        if ($c->description) {
            $d = $cfg['description'];
            $dw = TextTools::wordCount($c->description);
            $dw < $d['min_words'] && $add('editorial', 'description_short', 'warn', 'description', "The description has {$dw} words; add the most useful supporting context.");
            ($dw > $d['soft_max_words'] || mb_strlen($c->description) > $d['soft_max_chars']) && $add('editorial', 'description_long', 'warn', 'description', "The description is long ({$dw} words); keep one or two sentences.");
            count(TextTools::sentences($c->description)) > $d['max_sentences'] && $add('editorial', 'description_sentences', 'warn', 'description', 'Use one or two sentences at most.');
            substr_count($c->description, ',') >= 5 && $add('editorial', 'catalogue_description', 'warn', 'description', 'The description reads like a product catalogue.');
        } else {
            $add('editorial', 'description_missing', 'warn', 'description', 'Without a description the headline has to carry the whole message.');
        }

        if ($c->eyebrow) {
            $ew = TextTools::wordCount($c->eyebrow);
            ($ew < $cfg['eyebrow']['min_words'] || $ew > $cfg['eyebrow']['max_words']) && $add('editorial', 'eyebrow_length', 'warn', 'eyebrow', "An eyebrow works best at {$cfg['eyebrow']['min_words']}–{$cfg['eyebrow']['max_words']} words.");
            preg_match('/^(welcome|we are|we\'re)\b/i', $c->eyebrow) && $add('editorial', 'eyebrow_generic', 'fail', 'eyebrow', 'A generic eyebrow adds nothing.');
            TextTools::overlap($c->eyebrow, $c->headline) >= 0.6 && $add('editorial', 'eyebrow_repeats_headline', 'warn', 'eyebrow', 'The eyebrow repeats the headline.');
        }

        foreach (['primary_cta' => $c->primaryCta, 'secondary_cta' => $c->secondaryCta] as $field => $cta) {
            if (! $cta) {
                continue;
            }
            $label = trim((string) ($cta['label'] ?? ''));
            $w = TextTools::wordCount($label);
            ($w < $cfg['cta']['min_words'] || $w > $cfg['cta']['max_words']) && $add('editorial', 'cta_length', 'warn', $field, "A call to action works best at {$cfg['cta']['min_words']}–{$cfg['cta']['max_words']} words.");
            in_array(mb_strtolower($label), $cfg['weak_ctas'], true) && $add('editorial', 'weak_cta', 'warn', $field, "\"{$label}\" is vague; name the actual action.");
        }
        if ($c->primaryCta && $c->secondaryCta && mb_strtolower(trim($c->primaryCta['label'] ?? '')) === mb_strtolower(trim($c->secondaryCta['label'] ?? ''))) {
            $add('editorial', 'ctas_identical', 'warn', 'secondary_cta', 'The two calls to action are identical.');
        }
    }
}
