<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesRunOwner;
use App\Models\AnalysisRun;
use App\Models\Plan;
use App\Services\Billing\Billing;
use Illuminate\Http\Request;

/** The page a person lands on when they try to approve, publish or download on the Free plan. */
class UpgradeController extends Controller
{
    use AuthorizesRunOwner;

    public function show(Request $request, AnalysisRun $analysisRun, Billing $billing)
    {
        $this->own($request, $analysisRun);
        $user = $request->user();
        if ($user->canPublish() || $user->canExportStatic()) {
            return redirect()->route('analyses.launch', $analysisRun->public_id);
        }

        return view('analyses.upgrade', [
            'run' => $analysisRun, 'user' => $user, 'intent' => $request->query('intent') === 'approve' ? 'approve' : 'launch',
            'plans' => Plan::where('is_active', true)->where('plan_key', '!=', 'free')->orderBy('sort_order')->get(),
            'current' => $user->currentPlan(), 'billingLive' => $billing->enabled(), 'exportPass' => $billing->exportPassPricing(), 'cancelled' => $request->boolean('cancelled'),
            'host' => parse_url($analysisRun->normalized_url, PHP_URL_HOST),
        ]);
    }
}
