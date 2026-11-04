<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Models\ApprovedDesign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LaunchController extends Controller
{
    public function show(AnalysisRun $analysisRun): View|RedirectResponse
    {
        $spec = $analysisRun->currentDesignSpec();

        if (! $spec) {
            return redirect()->route('analyses.show', $analysisRun->public_id)
                ->with('status', 'Generate a REDO preview before launching.');
        }

        $report = $spec->visualQaReports()->where('status', 'complete')->latest('id')->first();
        $pending = $report ? null : $spec->visualQaReports()->whereIn('status', ['pending', 'running'])->latest('id')->first();

        return view('analyses.launch', [
            'shots' => $report?->report_json['screenshot_urls'] ?? [],
            'qaPending' => $pending,
            'run' => $analysisRun,
            'spec' => $spec,
            'approved' => $analysisRun->currentApprovedDesign(),
            'user' => auth()->user(),
            'pass' => app(\App\Services\Billing\ExportPassAllowance::class)->capFor(auth()->user(), $analysisRun),
            'canExportThis' => app(\App\Services\Billing\ExportPassAllowance::class)->canExport(auth()->user(), $analysisRun),
        ]);
    }

    public function approve(Request $request, AnalysisRun $analysisRun): RedirectResponse
    {
        $spec = $analysisRun->currentDesignSpec();

        if (! $spec) {
            return redirect()->route('analyses.show', $analysisRun->public_id)
                ->with('status', 'Generate a REDO preview before approving.');
        }

        // Approving is the step that leads to publishing or download, so it's where the Free plan meets its limit.
        if (! $request->user()->canPublish() && ! $request->user()->canExportStatic()) {
            return redirect()->route('analyses.upgrade', ['analysisRun' => $analysisRun->public_id, 'intent' => 'approve']);
        }

        ApprovedDesign::create([
            'analysis_run_id' => $analysisRun->id,
            'design_spec_id' => $spec->id,
            'approved_by_user_id' => $request->user()?->id,
            'approved_at' => now(),
        ]);

        return redirect()->route('analyses.launch', $analysisRun->public_id)
            ->with('status', 'Design approved. Choose what to do next.');
    }
}
