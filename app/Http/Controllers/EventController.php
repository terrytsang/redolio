<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Models\InteractionEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * Records a permitted, first-party preview interaction event (FR-101).
     * Aggregation into a Next REDO hypothesis happens deterministically
     * elsewhere; this endpoint only stores the raw, allowed event.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'analysis_public_id' => ['required', 'string'],
            'design_spec_id' => ['nullable', 'integer'],
            'event_name' => ['required', 'string', 'in:cta_click,section_view,preview_opened'],
            'element_key' => ['nullable', 'string', 'max:255'],
        ]);

        $run = AnalysisRun::where('public_id', $validated['analysis_public_id'])->first();

        if (! $run) {
            return response()->json(['status' => 'ignored'], 202);
        }

        InteractionEvent::create([
            'analysis_run_id' => $run->id,
            'design_spec_id' => $validated['design_spec_id'] ?? null,
            'event_name' => $validated['event_name'],
            'element_key' => $validated['element_key'] ?? null,
            'session_token_hash' => hash('sha256', $request->session()->getId()),
            'occurred_at' => now(),
        ]);

        return response()->json(['status' => 'recorded']);
    }
}
