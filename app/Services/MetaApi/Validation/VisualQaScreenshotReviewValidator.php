<?php

namespace App\Services\MetaApi\Validation;

/**
 * Validates the visual_qa_screenshot_review response — never trust a
 * schema-constrained response as authoritative (PRD structured_output
 * policy). Drops any finding with an invalid viewport/severity/status.
 */
class VisualQaScreenshotReviewValidator
{
    /**
     * @return array{findings: array, overall_assessment: string}
     */
    public function validate(array $data): array
    {
        $viewports = ['desktop', 'mobile'];
        $severities = ['critical', 'high', 'medium', 'low'];
        $statuses = ['passed', 'failed'];

        $findings = [];
        foreach (($data['findings'] ?? []) as $entry) {
            if (
                ! is_array($entry)
                || ! in_array($entry['viewport'] ?? null, $viewports, true)
                || ! in_array($entry['severity'] ?? null, $severities, true)
                || ! in_array($entry['status'] ?? null, $statuses, true)
                || empty($entry['check'])
            ) {
                continue;
            }

            $findings[] = [
                'check' => trim(strip_tags((string) $entry['check'])),
                'viewport' => $entry['viewport'],
                'severity' => $entry['severity'],
                'status' => $entry['status'],
                'evidence' => trim(strip_tags((string) ($entry['evidence'] ?? ''))),
                'source' => 'ai',
            ];
        }

        return [
            'findings' => $findings,
            'overall_assessment' => trim(strip_tags((string) ($data['overall_assessment'] ?? ''))),
        ];
    }
}
