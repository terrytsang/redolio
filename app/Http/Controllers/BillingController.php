<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Models\ApprovedDesign;
use App\Services\Billing\Billing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BillingController extends Controller
{
    /** Stripe sends people here after paying. The payment is confirmed with Stripe before anything is unlocked. */
    public function success(Request $request, Billing $billing): RedirectResponse
    {
        $id = (string) $request->query('session_id');
        if ($id === '' || ! preg_match('/^cs_[A-Za-z0-9_]+$/', $id)) {
            return redirect()->route('plan.show');
        }
        try {
            $sub = $billing->completeCheckout($request->user(), $id);
        } catch (RuntimeException $e) {
            return redirect()->route('plan.show')->with('status', $e->getMessage());
        }
        $ctx = $request->session()->pull('upgrade_ctx');
        $message = 'Thank you! You’re now on the '.$sub->plan->name.' plan.';
        $run = $ctx ? AnalysisRun::where('public_id', $ctx['run'])->where('user_id', $request->user()->id)->first() : null;

        return $run ? $this->returnToWork($request, $run, $message, ($ctx['intent'] ?? '') === 'approve') : redirect()->route('plan.show')->with('status', $message);
    }

    /** Back to what they were doing: their approved design, ready to publish or download. */
    public function returnToWork(Request $request, AnalysisRun $run, string $message, ?bool $approve = null): RedirectResponse
    {
        $ctx = $request->session()->pull('upgrade_ctx');
        $approve ??= ($ctx['intent'] ?? '') === 'approve';
        $spec = $run->currentDesignSpec();
        if ($spec && ($approve || ! $run->currentApprovedDesign())) {
            ApprovedDesign::create(['analysis_run_id' => $run->id, 'design_spec_id' => $spec->id, 'approved_by_user_id' => $request->user()->id, 'approved_at' => now()]);
            $message .= ' Your design is approved — publish it or download the files below.';
        }

        return redirect()->route('analyses.launch', $run->public_id)->with('status', $message);
    }

    public function portal(Request $request, Billing $billing): RedirectResponse
    {
        try {
            return redirect()->away($billing->portalUrl($request->user(), route('plan.show')));
        } catch (RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }
    }
}
