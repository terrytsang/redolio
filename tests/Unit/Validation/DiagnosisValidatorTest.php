<?php

namespace Tests\Unit\Validation;

use App\Services\MetaApi\Validation\DiagnosisValidator;
use PHPUnit\Framework\TestCase;

class DiagnosisValidatorTest extends TestCase
{
    public function test_rejects_issue_with_no_valid_evidence_ids(): void
    {
        $validator = new DiagnosisValidator;

        $report = [
            'summary' => 'Test',
            'issues' => [
                ['title' => 'Bad issue', 'problem' => 'x', 'evidence_ids' => ['does-not-exist']],
                ['title' => 'Good issue', 'problem' => 'y', 'evidence_ids' => ['p0-title']],
            ],
        ];

        $result = $validator->validate($report, ['p0-title']);

        $this->assertCount(1, $result['issues']);
        $this->assertSame('Good issue', $result['issues'][0]['title']);
    }

    public function test_caps_issues_at_five(): void
    {
        $validator = new DiagnosisValidator;

        $issues = [];
        for ($i = 0; $i < 10; $i++) {
            $issues[] = ['title' => "Issue {$i}", 'problem' => 'x', 'evidence_ids' => ['p0-title']];
        }

        $result = $validator->validate(['summary' => '', 'issues' => $issues], ['p0-title']);

        $this->assertCount(5, $result['issues']);
    }

    public function test_defaults_invalid_enum_values(): void
    {
        $validator = new DiagnosisValidator;

        $result = $validator->validate([
            'summary' => '',
            'issues' => [
                ['title' => 'Issue', 'problem' => 'x', 'severity' => 'catastrophic', 'evidence_ids' => ['p0-title']],
            ],
        ], ['p0-title']);

        $this->assertSame('medium', $result['issues'][0]['severity']);
    }
}
