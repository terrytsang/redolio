<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\Orchestration\GenerateNextRedoRecommendationAction;
use Illuminate\Http\RedirectResponse;

class NextRedoController extends Controller
{
    public function show(AnalysisRun $analysisRun)
    {
        return view('analyses.next-redo', [
            'run' => $analysisRun,
            'designSpec' => $analysisRun->currentDesignSpec(),
            'recommendation' => $analysisRun->currentNextRedoRecommendation(),
            'eventCount' => $analysisRun->interactionEvents()->count(),
        ]);
    }

    public function generate(AnalysisRun $analysisRun, GenerateNextRedoRecommendationAction $action): RedirectResponse
    {
        $designSpec = $analysisRun->currentDesignSpec();

        if (! $designSpec) {
            return redirect()->route('analyses.show', $analysisRun->public_id);
        }

        try {
            $action->execute($analysisRun, $designSpec);
        } catch (MetaApiException) {
            return redirect()->route('analyses.next-redo', $analysisRun->public_id)
                ->with('status', 'Next REDO generation is temporarily unavailable. Please try again shortly.');
        }

        return redirect()->route('analyses.next-redo', $analysisRun->public_id);
    }
}
