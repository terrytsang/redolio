<?php

namespace App\Services\MetaApi\Validation;

/**
 * Validates model-produced Website DNA (AI-02). Any claim whose evidence
 * IDs don't exist in the current snapshot is downgraded to an inference
 * or dropped entirely (AT-18) rather than shown as an observed fact.
 */
class WebsiteDnaValidator
{
    /**
     * @param  string[]  $validEvidenceIds
     */
    public function validate(array $dna, array $validEvidenceIds): array
    {
        $validSet = array_flip($validEvidenceIds);

        return [
            'business_name' => $this->nullableString($dna['business_name'] ?? null),
            'industry' => $this->nullableString($dna['industry'] ?? null),
            'business_summary' => (string) ($dna['business_summary'] ?? ''),
            'target_audience' => $this->validateAudience($dna['target_audience'] ?? [], $validSet),
            'offerings' => $this->validateOfferings($dna['offerings'] ?? [], $validSet),
            'value_proposition' => $this->validateValueProposition($dna['value_proposition'] ?? null, $validSet),
            'tone_of_voice' => array_values(array_filter((array) ($dna['tone_of_voice'] ?? []), 'is_string')),
            'primary_conversion_goal' => $this->enum(
                $dna['primary_conversion_goal'] ?? 'unknown',
                ['lead', 'booking', 'purchase', 'inquiry', 'awareness', 'unknown'],
                'unknown'
            ),
            'cta_inventory' => $this->validateCtaInventory($dna['cta_inventory'] ?? [], $validSet),
            'trust_signals' => $this->validateTrustSignals($dna['trust_signals'] ?? [], $validSet),
            'observed_facts' => $this->validateClaims($dna['observed_facts'] ?? [], $validSet, downgrade: true),
            'inferences' => $this->validateInferences($dna['inferences'] ?? [], $validSet),
            'uncertainties' => array_values(array_filter((array) ($dna['uncertainties'] ?? []), 'is_string')),
        ];
    }

    private function validateAudience(array $items, array $validSet): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item) || empty($item['segment'])) {
                continue;
            }
            $out[] = [
                'segment' => (string) $item['segment'],
                'needs' => array_values(array_filter((array) ($item['needs'] ?? []), 'is_string')),
                'confidence' => $this->confidence($item['confidence'] ?? 'low'),
                'evidence_ids' => $this->filterEvidenceIds($item['evidence_ids'] ?? [], $validSet),
            ];
        }

        return $out;
    }

    private function validateOfferings(array $items, array $validSet): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item) || empty($item['name'])) {
                continue;
            }
            $out[] = [
                'name' => (string) $item['name'],
                'description' => (string) ($item['description'] ?? ''),
                'evidence_ids' => $this->filterEvidenceIds($item['evidence_ids'] ?? [], $validSet),
            ];
        }

        return $out;
    }

    private function validateValueProposition(?array $vp, array $validSet): array
    {
        if (! is_array($vp)) {
            return ['text' => null, 'evidence_ids' => [], 'confidence' => 'low'];
        }

        $evidenceIds = $this->filterEvidenceIds($vp['evidence_ids'] ?? [], $validSet);

        return [
            'text' => $evidenceIds !== [] ? $this->nullableString($vp['text'] ?? null) : null,
            'evidence_ids' => $evidenceIds,
            'confidence' => $this->confidence($vp['confidence'] ?? 'low'),
        ];
    }

    private function validateCtaInventory(array $items, array $validSet): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item) || empty($item['label'])) {
                continue;
            }
            $evidenceIds = $this->filterEvidenceIds($item['evidence_ids'] ?? [], $validSet);
            if ($evidenceIds === []) {
                continue; // CTA claims must be evidence-backed.
            }
            $out[] = [
                'label' => (string) $item['label'],
                'page_url' => (string) ($item['page_url'] ?? ''),
                'evidence_ids' => $evidenceIds,
            ];
        }

        return $out;
    }

    private function validateTrustSignals(array $items, array $validSet): array
    {
        $allowedTypes = ['testimonial', 'credential', 'case_study', 'client_logo', 'guarantee', 'contact_detail', 'other'];
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item) || empty($item['description'])) {
                continue;
            }
            $evidenceIds = $this->filterEvidenceIds($item['evidence_ids'] ?? [], $validSet);
            if ($evidenceIds === []) {
                continue;
            }
            $out[] = [
                'type' => $this->enum($item['type'] ?? 'other', $allowedTypes, 'other'),
                'description' => (string) $item['description'],
                'evidence_ids' => $evidenceIds,
            ];
        }

        return $out;
    }

    private function validateClaims(array $items, array $validSet, bool $downgrade = false): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item) || empty($item['claim'])) {
                continue;
            }
            $evidenceIds = $this->filterEvidenceIds($item['evidence_ids'] ?? [], $validSet);
            if ($evidenceIds === []) {
                continue; // Unsupported "observed fact" claims are dropped, never shown as observed.
            }
            $out[] = ['claim' => (string) $item['claim'], 'evidence_ids' => $evidenceIds];
        }

        return $out;
    }

    private function validateInferences(array $items, array $validSet): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item) || empty($item['claim'])) {
                continue;
            }
            $out[] = [
                'claim' => (string) $item['claim'],
                'confidence' => $this->confidence($item['confidence'] ?? 'low'),
                // Inferences may have zero evidence IDs (they are explicitly labelled inference).
                'evidence_ids' => $this->filterEvidenceIds($item['evidence_ids'] ?? [], $validSet),
            ];
        }

        return $out;
    }

    private function filterEvidenceIds(mixed $ids, array $validSet): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_filter($ids, fn ($id) => is_string($id) && isset($validSet[$id])));
    }

    private function confidence(mixed $value): string
    {
        return $this->enum($value, ['low', 'medium', 'high'], 'low');
    }

    private function enum(mixed $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
