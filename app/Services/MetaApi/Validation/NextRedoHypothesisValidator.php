<?php

namespace App\Services\MetaApi\Validation;

/**
 * Validates the Next REDO hypothesis output (PRD schemas.next_redo_hypothesis,
 * stage next_redo_optional). Per the claims_policy, this is always a
 * hypothesis, never a proven-impact claim, regardless of what the model
 * returns in `status`.
 */
class NextRedoHypothesisValidator
{
    public function validate(array $hypothesis, array $signalSummary): array
    {
        return [
            'hypothesis' => (string) ($hypothesis['hypothesis'] ?? ''),
            'reason' => (string) ($hypothesis['reason'] ?? ''),
            'signal_summary' => $signalSummary,
            'suggested_change' => (string) ($hypothesis['suggested_change'] ?? ''),
            'success_measure' => (string) ($hypothesis['success_measure'] ?? ''),
            'status' => 'hypothesis_not_yet_tested',
        ];
    }
}
