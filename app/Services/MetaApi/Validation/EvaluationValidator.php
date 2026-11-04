<?php

namespace App\Services\MetaApi\Validation;

/**
 * Validates the evaluator output (FR-009/AI-06). Every diagnosis issue
 * must appear in the evaluation; any issue the model omitted is filled
 * in deterministically as "not_addressed" so coverage is always complete.
 */
class EvaluationValidator
{
    private const STATUSES = ['addressed', 'partially_addressed', 'not_addressed'];

    /**
     * @param  array<int, array{issue_id: string}>  $diagnosisIssues
     */
    public function validate(array $evaluation, array $diagnosisIssues): array
    {
        $byIssueId = [];

        foreach (($evaluation['items'] ?? []) as $item) {
            if (! is_array($item) || empty($item['issue_id'])) {
                continue;
            }

            $byIssueId[$item['issue_id']] = [
                'issue_id' => (string) $item['issue_id'],
                'status' => in_array($item['status'] ?? null, self::STATUSES, true) ? $item['status'] : 'not_addressed',
                'rationale' => (string) ($item['rationale'] ?? ''),
                'checks' => array_values(array_filter((array) ($item['checks'] ?? []), 'is_string')),
            ];
        }

        $items = [];
        foreach ($diagnosisIssues as $issue) {
            $issueId = $issue['issue_id'];
            $items[] = $byIssueId[$issueId] ?? [
                'issue_id' => $issueId,
                'status' => 'not_addressed',
                'rationale' => 'No evaluation was returned for this issue; defaulted to not addressed.',
                'checks' => [],
            ];
        }

        return [
            'items' => $items,
            'overall_note' => 'Coverage assessment only; not a conversion or business outcome measurement.',
        ];
    }
}
