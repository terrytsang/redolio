<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDesignSpecJob;
use App\Jobs\RunWebsiteAnalysis;
use App\Models\AnalysisRun;
use App\Models\DesignSelection;
use App\Services\Account\AnalysisDeleter;
use App\Services\Account\AnalysisStarter;
use App\Services\Orchestration\AnalysisProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AnalysisController extends Controller
{
    /** The homepage form. A signed-in person starts the scan at once; a visitor is asked to sign up first (one short step), then it starts. */
    public function store(Request $request, AnalysisStarter $starter): RedirectResponse
    {
        $request->validate(['url' => ['required', 'string', 'max:2048']]);
        $normalized = $starter->normalize($request->input('url'));

        if (! $request->user()) {
            $request->session()->put('pending_analysis', ['submitted' => mb_substr($request->input('url'), 0, 2048), 'normalized' => $normalized]);

            return redirect()->route('register')->with('status', 'Create your free account to start — it takes a few seconds.');
        }

        $run = $starter->start($request->input('url'), $normalized, $request->user(), $request->input('idempotency_key'));

        return redirect()->route('analyses.show', $run->public_id);
    }

    /** Delete one of your website analyses to free up a place on your plan. */
    public function destroy(Request $request, AnalysisRun $analysisRun, AnalysisDeleter $deleter): RedirectResponse
    {
        abort_unless($analysisRun->user_id === $request->user()->id, 403);
        $host = parse_url($analysisRun->normalized_url, PHP_URL_HOST) ?: $analysisRun->normalized_url;
        $live = $analysisRun->published_slug !== null;
        $deleter->delete($analysisRun);

        return redirect()->route('dashboard')->with('status', "Deleted {$host}".($live ? ' and took its published copy offline' : '').'. That place on your plan is free again.');
    }

    public function show(AnalysisRun $analysisRun)
    {
        $analysisRun->load([
            'siteSnapshot',
            'stageLogs' => fn ($q) => $q->orderBy('id'),
        ]);

        return view('analyses.show', [
            'run' => $analysisRun,
            'progress' => app(AnalysisProgress::class)->snapshot($analysisRun),
            'dna' => $analysisRun->currentWebsiteDna(),
            'diagnosis' => $analysisRun->currentDiagnosisReport(),
            'designSpec' => $analysisRun->currentDesignSpec(),
            'designSelection' => $analysisRun->currentDesignSelection(),
        ]);
    }

    /** Polled by the scan page: percent, time left, per-stage state and (when busy) queue position. */
    public function progress(AnalysisRun $analysisRun, AnalysisProgress $progress)
    {
        return response()->json($progress->snapshot($analysisRun))->header('Cache-Control', 'no-store');
    }

    /** Opt in to a single "your REDO is ready" email (offered when the system is busy). */
    public function notify(Request $request, AnalysisRun $analysisRun)
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        // Only while the scan is still running (a finished one needs no email), and the first address wins: the public link must not let
        // anyone redirect another visitor's notification or make the app mail an arbitrary address on demand.
        if (in_array($analysisRun->status, ['complete', 'partial', 'failed'], true)) {
            return $request->expectsJson() ? response()->json(['ok' => true, 'ready' => true]) : back();
        }
        if ($analysisRun->notify_email === null) {
            $analysisRun->update(['notify_email' => $data['email']]);
        }

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('status', "We'll email once your REDO is ready.");
    }

    public function retry(AnalysisRun $analysisRun): RedirectResponse
    {
        if ($analysisRun->status !== 'failed') {
            return redirect()->route('analyses.show', $analysisRun->public_id);
        }

        $analysisRun->siteSnapshot?->delete();
        $analysisRun->websiteDnaVersions()->delete();
        $analysisRun->diagnosisReports()->delete();
        $analysisRun->designSpecs()->delete();

        $analysisRun->update([
            'status' => 'pending',
            'current_stage' => 'validating_url',
            'error_code' => null,
            'safe_error_message' => null,
        ]);

        RunWebsiteAnalysis::dispatch($analysisRun->id);

        return redirect()->route('analyses.show', $analysisRun->public_id);
    }

    public function redo(Request $request, AnalysisRun $analysisRun): RedirectResponse
    {
        $dna = $analysisRun->currentWebsiteDna();
        $diagnosis = $analysisRun->currentDiagnosisReport();

        if (! $dna || ! $diagnosis) {
            return redirect()->route('analyses.show', $analysisRun->public_id)
                ->with('status', 'Website DNA and diagnosis must be ready before generating a new REDO.');
        }

        $fromVersion = $request->integer('from_version');
        $fromSpec = $fromVersion
            ? $analysisRun->designSpecs()->where('version', $fromVersion)->first()
            : $analysisRun->currentDesignSpec();

        if (! $fromSpec) {
            return redirect()->route('analyses.show', $analysisRun->public_id)
                ->with('status', 'Nothing to refine yet — generate a REDO preview first.');
        }

        // Refine always continues in the same direction the user is looking
        // at — never silently falls back to the generic default template.
        $priorSelection = DesignSelection::where('design_spec_id', $fromSpec->id)->latest('selected_at')->first();
        $directionKey = $priorSelection->direction_key ?? $fromSpec->template_key;
        $preferences = $priorSelection->preferences_json ?? ['preserve_logo' => true, 'preserve_images' => true, 'preserve_wording' => true];

        $selection = DesignSelection::create([
            'analysis_run_id' => $analysisRun->id,
            'direction_key' => $directionKey,
            'direction_version' => $priorSelection->direction_version ?? 1,
            'preferences_json' => $preferences,
            'selected_at' => now(),
            'status' => 'generating',
            'refine_from_design_spec_id' => $fromSpec->id,
        ]);

        GenerateDesignSpecJob::dispatch($selection->id);

        return redirect()->route('analyses.design-directions.generating', [$analysisRun->public_id, $selection->id]);
    }
}
