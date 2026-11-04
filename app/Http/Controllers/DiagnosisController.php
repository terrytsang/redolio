<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;

class DiagnosisController extends Controller
{
    public function show(AnalysisRun $analysisRun)
    {
        return view('analyses.diagnosis', [
            'run' => $analysisRun,
            'diagnosis' => $analysisRun->currentDiagnosisReport(),
            'dna' => $analysisRun->currentWebsiteDna(),
        ]);
    }
}
