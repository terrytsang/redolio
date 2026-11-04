<?php

namespace App\Http\Controllers;

use App\Models\DesignDirection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A public gallery of every selectable design direction, open to any
 * signed-up user regardless of plan. "Remix" always applies the chosen
 * direction's STYLE (its recipe) to the viewer's own site — it never
 * copies another user's business content, copy, or images onto anyone
 * else's site (see skills/redo-skill/SKILL.md).
 */
class DirectionGalleryController extends Controller
{
    public function index(): View
    {
        return view('directions.index', [
            'directions' => DesignDirection::selectable()->orderBy('name')->get()->filter->hasRenderableTemplate()->values(),
        ]);
    }

    public function remix(Request $request, DesignDirection $direction): RedirectResponse
    {
        if (! $direction->is_selectable || ! $direction->hasRenderableTemplate()) {
            abort(404);
        }

        if (! $request->user()) {
            return redirect()->route('register', ['remix' => $direction->direction_key]);
        }

        $request->session()->put('remix_direction', $direction->direction_key);

        $eligibleRun = $request->user()->analysisRuns()
            ->whereHas('websiteDnaVersions')
            ->latest()
            ->first();

        if ($eligibleRun) {
            return redirect()->route('analyses.design-directions', $eligibleRun->public_id);
        }

        return redirect()->route('dashboard')
            ->with('status', "Redo a website first, then you'll be able to apply the {$direction->name} direction to it.");
    }
}
