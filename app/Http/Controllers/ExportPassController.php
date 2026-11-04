<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Models\OneTimePurchase;
use App\Models\Transaction;
use App\Services\Billing\Billing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** The one-time Export Pass: pay once through Stripe, then download the HTML/CSS/JS zip (never published to REDOLIO). */
class ExportPassController extends Controller
{
    public function store(Request $request, Billing $billing): RedirectResponse
    {
        $user = $request->user();
        $run = ($id = $request->input('run')) ? AnalysisRun::where('public_id', $id)->where('user_id', $user->id)->first() : null;

        if ($billing->exportPassConfigured()) {
            try {
                $success = route('export-pass.success').'?session_id={CHECKOUT_SESSION_ID}';

                return redirect()->away($billing->exportPassCheckoutUrl($user, $success, $run ? route('analyses.launch', $run->public_id) : route('pricing'), $run?->public_id));
            } catch (RuntimeException $e) {
                return back()->with('status', $e->getMessage());
            }
        }

        if (! $billing->devSwitchAllowed()) {
            return back()->with('status', 'Payments aren’t available just yet — please check back soon.');
        }

        // Local development without Stripe keys only: grant a pass so the flow can be tried. Never in production.
        OneTimePurchase::create(['user_id' => $user->id, 'analysis_run_id' => $run?->id, 'purchase_type' => 'export_pass', 'status' => 'paid', 'amount_cents' => 0,
            'exports_remaining' => (int) config('redo.export_pass.exports_included', 3), 'saves_remaining' => (int) config('redo.export_pass.edits_included', 2)]);
        Transaction::create(['user_id' => $user->id, 'type' => 'export_pass', 'description' => 'Export Pass (development — no payment)', 'amount_cents' => 0, 'status' => 'paid', 'paid_at' => now()]);

        return $this->done($run, 'Export Pass added (development mode — no payment is taken).');
    }

    /** Stripe sends people here after paying. The payment is confirmed with Stripe before the pass is added. */
    public function success(Request $request, Billing $billing): RedirectResponse
    {
        $id = (string) $request->query('session_id');
        if ($id === '' || ! preg_match('/^cs_[A-Za-z0-9_]+$/', $id)) {
            return redirect()->route('plan.show');
        }
        try {
            $purchase = $billing->completeExportPass($request->user(), $id);
        } catch (RuntimeException $e) {
            return redirect()->route('plan.show')->with('status', $e->getMessage());
        }
        $session = $billing->exportPassSessionRun($id);
        $run = $session ? AnalysisRun::where('public_id', $session)->where('user_id', $request->user()->id)->first() : null;

        return $this->done($run, 'Thank you! Your Export Pass is ready — download your website files (HTML, CSS and JS) below.');
    }

    private function done(?AnalysisRun $run, string $message): RedirectResponse
    {
        return $run
            ? redirect()->route('analyses.launch', $run->public_id)->with('status', $message)
            : redirect()->route('plan.show')->with('status', $message);
    }
}
