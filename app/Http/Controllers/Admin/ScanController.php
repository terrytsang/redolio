<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalysisRun;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function index(Request $request): View
    {
        $runs = AnalysisRun::query()
            ->where('purpose', 'redesign')
            ->with(['user:id,name,email', 'designSelections' => fn ($q) => $q->latest('selected_at')->limit(1)])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(fn ($q) => $q->where('submitted_url', 'like', $term)->orWhere('normalized_url', 'like', $term));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.scans.index', [
            'runs' => $runs,
            'q' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
        ]);
    }

    public function show(AnalysisRun $analysisRun): View
    {
        $analysisRun->load(['user:id,name,email']);

        return view('admin.scans.show', [
            'run' => $analysisRun,
            'dna' => $analysisRun->currentWebsiteDna(),
            'diagnosis' => $analysisRun->currentDiagnosisReport(),
            'designSelection' => $analysisRun->currentDesignSelection(),
            'designSpec' => $analysisRun->currentDesignSpec(),
        ]);
    }
}
