<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\Billing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Choose a plan: new paid plans go to Stripe Checkout; changes between plans use the existing Stripe subscription. */
class PlanSelectionController extends Controller
{
    public function store(Request $request, Plan $plan, Billing $billing): RedirectResponse
    {
        $user = $request->user();
        $run = ($id = $request->input('run')) ? AnalysisRun::where('public_id', $id)->where('user_id', $user->id)->first() : null;
        $back = $run ? route('analyses.upgrade', $run->public_id) : route('plan.show');
        // Where a person was heading when they hit the plan wall, so payment returns them straight to it.
        $request->session()->put('upgrade_ctx', $run ? ['run' => $run->public_id, 'intent' => $request->input('intent') === 'approve' ? 'approve' : 'launch'] : null);

        if ($user->currentPlan()?->is($plan)) {
            return $this->after($run, "You're already on the {$plan->name} plan.", $back);
        }

        try {
            if ($plan->plan_key === 'free') {
                if ($billing->activeStripeSubscription($user)) {
                    $end = $billing->cancelAtPeriodEnd($user);

                    return redirect()->route('plan.show')->with('status', 'Your plan stays active'.($end ? ' until '.$end->format('j M Y') : ' until the end of the billing period').', then moves to Free.');
                }

                return $this->switchLocally($user, $plan, null);
            }

            if (! $billing->configuredFor($plan)) {
                return $billing->devSwitchAllowed()
                    ? $this->switchLocally($user, $plan, $run)
                    : back()->with('status', 'Payments aren’t available just yet — please check back soon.');
            }

            if ($billing->activeStripeSubscription($user)) {
                $billing->changePlan($user, $plan);

                return $this->after($run, "You're now on the {$plan->name} plan. Stripe adjusts the bill for the change.", route('plan.show'), true);
            }

            return redirect()->away($billing->checkoutUrl($user, $plan, route('billing.success').'?session_id={CHECKOUT_SESSION_ID}', $back.'?cancelled=1', array_filter(['run' => $run?->public_id])));
        } catch (RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }
    }

    private function switchLocally($user, Plan $plan, ?AnalysisRun $run): RedirectResponse
    {
        $user->subscriptions()->where('status', 'active')->update(['status' => 'canceled', 'canceled_at' => now()]);
        Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active', 'started_at' => now()]);

        return $this->after($run, "Switched to the {$plan->name} plan (development mode — no payment is taken).", route('plan.show'), true);
    }

    private function after(?AnalysisRun $run, string $message, string $fallback, bool $upgraded = false): RedirectResponse
    {
        if ($run && $upgraded) {
            return app(BillingController::class)->returnToWork(request(), $run, $message);
        }

        return redirect($fallback)->with('status', $message);
    }
}
