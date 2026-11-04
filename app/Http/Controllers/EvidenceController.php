<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EvidenceController extends Controller
{
    public function show(AnalysisRun $analysisRun, string $evidenceId): RedirectResponse|View
    {
        $snapshot = $analysisRun->siteSnapshot;

        $evidence = $snapshot?->evidenceItems()->where('evidence_key', $evidenceId)->first();

        if (! $evidence) {
            abort(404, 'Evidence item not found in the current snapshot.');
        }

        return view('analyses.evidence', [
            'run' => $analysisRun,
            'evidence' => $evidence,
        ]);
    }
}
