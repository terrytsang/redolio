<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Models\Deployment;
use App\Services\Publishing\PublishDeploymentAction;
use App\Services\Publishing\SlugGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublishController extends Controller
{
    public function show(Request $request, AnalysisRun $analysisRun, SlugGenerator $slugGenerator): View|RedirectResponse
    {
        if (! $request->user()->canPublish()) {
            return redirect()->route('pricing')
                ->with('status', 'Publishing is available on the Starter and Agency plans.');
        }

        $spec = $analysisRun->currentDesignSpec();
        if (! $spec) {
            return redirect()->route('analyses.show', $analysisRun->public_id);
        }

        return view('analyses.publish', [
            'run' => $analysisRun,
            'suggestedSlug' => $analysisRun->published_slug ?? $slugGenerator->suggestFrom($spec->design_spec_json['business_name'] ?? $analysisRun->normalized_url),
            'deployments' => $analysisRun->deployments()->orderByDesc('deployment_number')->get(),
        ]);
    }

    public function store(Request $request, AnalysisRun $analysisRun, SlugGenerator $slugGenerator, PublishDeploymentAction $publishAction): RedirectResponse
    {
        if (! $request->user()->canPublish()) {
            return redirect()->route('pricing')
                ->with('status', 'Publishing is available on the Starter and Agency plans.');
        }

        $spec = $analysisRun->currentDesignSpec();
        if (! $spec) {
            return redirect()->route('analyses.show', $analysisRun->public_id);
        }

        $slug = $analysisRun->published_slug;

        if (! $slug) {
            $validated = $request->validate(['slug' => ['required', 'string', 'max:60']]);
            $check = $slugGenerator->validate($validated['slug']);

            if (! $check['valid']) {
                return back()->withErrors(['slug' => $check['error']])->withInput();
            }

            $slug = $validated['slug'];
        }

        $deployment = $publishAction->execute($analysisRun, $spec, $slug);

        if (! $deployment->isLive()) {
            return redirect()->route('analyses.publish', $analysisRun->public_id)
                ->with('status', 'Publishing failed validation: '.implode(' ', $deployment->validation_json['errors'] ?? []));
        }

        return redirect()->route('analyses.publish', $analysisRun->public_id)
            ->with('status', 'Your website is live.');
    }

    public function rollback(Request $request, AnalysisRun $analysisRun, Deployment $deployment, PublishDeploymentAction $publishAction): RedirectResponse
    {
        abort_unless($deployment->analysis_run_id === $analysisRun->id, 404);
        if (! $request->user()->canPublish()) {
            return redirect()->route('pricing');
        }

        $ok = $publishAction->rollback($analysisRun, $deployment);

        return redirect()->route('analyses.publish', $analysisRun->public_id)
            ->with('status', $ok ? "Rolled back to deployment #{$deployment->deployment_number}." : 'Rollback failed.');
    }
}
