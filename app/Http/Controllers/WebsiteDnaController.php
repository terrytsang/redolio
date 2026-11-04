<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Models\WebsiteDna;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WebsiteDnaController extends Controller
{
    public function show(AnalysisRun $analysisRun)
    {
        return view('analyses.website-dna', [
            'run' => $analysisRun,
            'dna' => $analysisRun->currentWebsiteDna(),
            'diagnosis' => $analysisRun->currentDiagnosisReport(),
        ]);
    }

    public function update(Request $request, AnalysisRun $analysisRun): RedirectResponse
    {
        $current = $analysisRun->currentWebsiteDna();

        if (! $current) {
            return redirect()->route('analyses.show', $analysisRun->public_id);
        }

        $validated = $request->validate([
            'business_name' => ['nullable', 'string', 'max:255'],
            'business_summary' => ['nullable', 'string', 'max:2000'],
            'value_proposition_text' => ['nullable', 'string', 'max:500'],
            'primary_conversion_goal' => ['nullable', 'in:lead,booking,purchase,inquiry,awareness,unknown'],
            'tone_of_voice' => ['nullable', 'string', 'max:255'],
        ]);

        $dnaJson = $current->dna_json;
        $dnaJson['business_name'] = $validated['business_name'] ?? $dnaJson['business_name'];
        $dnaJson['business_summary'] = $validated['business_summary'] ?? $dnaJson['business_summary'];
        $dnaJson['value_proposition']['text'] = $validated['value_proposition_text'] ?? ($dnaJson['value_proposition']['text'] ?? null);
        $dnaJson['primary_conversion_goal'] = $validated['primary_conversion_goal'] ?? $dnaJson['primary_conversion_goal'];

        if (! empty($validated['tone_of_voice'])) {
            $dnaJson['tone_of_voice'] = array_map('trim', explode(',', $validated['tone_of_voice']));
        }

        WebsiteDna::create([
            'analysis_run_id' => $analysisRun->id,
            'version' => $current->version + 1,
            'dna_json' => $dnaJson,
            'model_identifier' => $current->model_identifier,
            'prompt_version' => $current->prompt_version,
            'edited_by_user' => true,
        ]);

        return redirect()->route('analyses.website-dna', $analysisRun->public_id)
            ->with('status', 'Website DNA updated. Generate a new REDO to apply these edits.');
    }
}
