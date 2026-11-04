<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** "My plan": the current plan, its usage, and every other plan as an upgrade or a downgrade. */
class PlanController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $current = $user->currentPlan();
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();
        $watching = $user->siteWatches()->where('is_enabled', true)->count();

        $options = $plans->map(function (Plan $p) use ($current, $watching) {
            $rel = ! $current ? 'upgrade' : match (true) {
                $p->is($current) => 'current',
                $p->price_monthly_cents > $current->price_monthly_cents => 'upgrade',
                default => 'downgrade',
            };

            return [
                'plan' => $p,
                'relation' => $rel,
                // A downgrade that would leave more watched sites than the smaller plan allows is flagged before it happens.
                'over_limit' => $rel === 'downgrade' && $p->site_limit !== null && $watching > $p->site_limit ? $watching - $p->site_limit : 0,
            ];
        });

        return view('plan.show', ['user' => $user, 'plan' => $current, 'options' => $options, 'watching' => $watching, 'sites' => $user->analysisRuns()->count()]);
    }
}
