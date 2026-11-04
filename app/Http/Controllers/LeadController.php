<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function store(Request $request, AnalysisRun $analysisRun): RedirectResponse
    {
        if (! $analysisRun->user?->hasLeadGenAccess()) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'request_type' => ['required', 'in:quote,booking,message'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        Lead::create([
            'analysis_run_id' => $analysisRun->id,
            'user_id' => $analysisRun->user_id,
            ...$validated,
        ]);

        return back()->with('status', 'Thanks — your request has been sent.');
    }
}
