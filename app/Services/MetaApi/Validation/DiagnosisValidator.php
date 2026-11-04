<?php

namespace App\Services\MetaApi\Validation;

/**
 * Validates a model-produced diagnosis report (AI-03). Any issue with no
 * valid evidence ID is rejected outright and never shown as a verified
 * finding (AT-17). Caps at five issues per PRD.
 */
class DiagnosisValidator
{
    private const CATEGORIES = [
        'positioning', 'audience', 'messaging', 'information_architecture', 'cta',
        'trust', 'content', 'mobile', 'accessibility', 'technical', 'other',
    ];

    private const SEVERITIES = ['high', 'medium', 'low'];

    private const CONFIDENCES = ['low', 'medium', 'high'];

    private const VERIFICATION_TYPES = ['observed', 'heuristic', 'model_inference'];

    /**
     * @param  string[]  $validEvidenceIds
     */
    public function validate(array $report, array $validEvidenceIds): array
    {
        $validSet = array_flip($validEvidenceIds);
        $issues = [];

        foreach (($report['issues'] ?? []) as $issue) {
            if (! is_array($issue) || empty($issue['title']) || empty($issue['problem'])) {
                continue;
            }

            $evidenceIds = array_values(array_filter(
                (array) ($issue['evidence_ids'] ?? []),
                fn ($id) => is_string($id) && isset($validSet[$id])
            ));

            if ($evidenceIds === []) {
                continue; // AT-17: no valid evidence => not a verified diagnosis.
            }

            $issues[] = [
                'issue_id' => (string) ($issue['issue_id'] ?? ('issue-'.count($issues))),
                'title' => (string) $issue['title'],
                'category' => $this->enum($issue['category'] ?? 'other', self::CATEGORIES, 'other'),
                'severity' => $this->enum($issue['severity'] ?? 'medium', self::SEVERITIES, 'medium'),
                'problem' => (string) $issue['problem'],
                'why_it_matters' => (string) ($issue['why_it_matters'] ?? ''),
                'recommended_solution' => (string) ($issue['recommended_solution'] ?? ''),
                'evidence_ids' => $evidenceIds,
                'confidence' => $this->enum($issue['confidence'] ?? 'low', self::CONFIDENCES, 'low'),
                'verification_type' => $this->enum($issue['verification_type'] ?? 'model_inference', self::VERIFICATION_TYPES, 'model_inference'),
            ];

            if (count($issues) >= 5) {
                break;
            }
        }

        return [
            'summary' => (string) ($report['summary'] ?? ''),
            'issues' => $issues,
            'overall_confidence' => $this->enum($report['overall_confidence'] ?? 'low', self::CONFIDENCES, 'low'),
            'limitations' => array_values(array_filter((array) ($report['limitations'] ?? []), 'is_string')),
        ];
    }

    private function enum(mixed $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
