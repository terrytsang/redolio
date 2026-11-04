<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\OneTimePurchase;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Stripe billing for the paid plans, through Stripe's REST API (Checkout for new subscriptions, subscription updates for
 * plan changes, the Billing Portal for cards and invoices, webhooks for the source of truth).
 *
 * Configure with STRIPE_SECRET, STRIPE_WEBHOOK_SECRET and the price ids STRIPE_REDOLIO_STARTER_PRICE_ID, STRIPE_REDOLIO_AGENCY_PRICE_ID
 * and STRIPE_REDOLIO_EXPORT_PRICE_ID (the one-time Export Pass).
 * Without them nothing is charged and paid plans cannot be activated outside local development.
 */
class Billing
{
    private const API = 'https://api.stripe.com/v1';

    public function enabled(): bool
    {
        return (string) config('services.stripe.secret') !== '';
    }

    public function priceFor(Plan $plan): ?string
    {
        return config('services.stripe.prices.'.$plan->plan_key) ?: null;
    }

    public function configuredFor(Plan $plan): bool
    {
        return $this->enabled() && $this->priceFor($plan) !== null;
    }

    /** Local development only: switch plans without Stripe. Never in production. */
    public function devSwitchAllowed(): bool
    {
        return ! $this->enabled() && app()->environment('local', 'testing');
    }

    public function planForPrice(?string $priceId): ?Plan
    {
        foreach ((array) config('services.stripe.prices') as $key => $price) {
            if ($price && $price === $priceId) {
                return Plan::where('plan_key', $key)->first();
            }
        }

        return null;
    }

    // ---------------------------------------------------------------- checkout

    /** @param array<string, string> $meta */
    public function checkoutUrl(User $user, Plan $plan, string $successUrl, string $cancelUrl, array $meta = []): string
    {
        $price = $this->priceFor($plan) ?? throw new RuntimeException('This plan has no Stripe price yet.');
        $r = $this->stripe()->post('/checkout/sessions', array_filter([
            'mode' => 'subscription',
            'line_items' => [['price' => $price, 'quantity' => 1]],
            'customer' => $this->customerId($user),
            'client_reference_id' => (string) $user->id,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'allow_promotion_codes' => 'true',
            'metadata' => ['user_id' => (string) $user->id, 'plan_key' => $plan->plan_key] + $meta,
            'subscription_data' => ['metadata' => ['user_id' => (string) $user->id, 'plan_key' => $plan->plan_key]],
        ]));
        if (! $r->successful() || ! $r->json('url')) {
            Log::warning('Stripe checkout session failed', ['status' => $r->status(), 'error' => $r->json('error.message')]);
            throw new RuntimeException('We couldn’t start the payment page. Please try again in a moment.');
        }

        return $r->json('url');
    }

    /** The success redirect: confirm with Stripe (never trust the URL alone) and activate the plan. */
    public function completeCheckout(User $user, string $sessionId): Subscription
    {
        $r = $this->stripe()->get('/checkout/sessions/'.rawurlencode($sessionId), ['expand' => ['subscription']]);
        if (! $r->successful() || (string) $r->json('client_reference_id') !== (string) $user->id) {
            throw new RuntimeException('We couldn’t confirm that payment.');
        }
        if ($r->json('status') !== 'complete' || ! in_array($r->json('payment_status'), ['paid', 'no_payment_required'], true)) {
            throw new RuntimeException('The payment isn’t complete yet.');
        }

        return $this->activateFromSession($user, $r->json());
    }

    // ---------------------------------------------------------------- Export Pass (one-time)

    public function exportPassConfigured(): bool
    {
        return $this->enabled() && $this->exportPassPriceId() !== '';
    }

    /** The launch-offer price when one is set up in Stripe, otherwise the regular Export Pass price. */
    public function exportPassPriceId(): string
    {
        return (string) (config('services.stripe.export_offer_price') ?: config('services.stripe.export_price'));
    }

    public function exportPassOfferActive(): bool
    {
        return (string) config('services.stripe.export_offer_price') !== '';
    }

    /** @return array{list: float, price: float, offer: bool} dollars to show: the regular price, what is charged, and whether the launch offer applies */
    public function exportPassPricing(): array
    {
        $list = (int) config('redo.export_pass.list_cents', 2500) / 100;
        $offer = $this->exportPassOfferActive();

        return ['list' => $list, 'price' => $offer ? (int) config('redo.export_pass.offer_cents', 1000) / 100 : $list, 'offer' => $offer];
    }

    public function exportPassCheckoutUrl(User $user, string $successUrl, string $cancelUrl, ?string $runPublicId = null): string
    {
        $price = $this->exportPassPriceId() ?: throw new RuntimeException('The Export Pass has no Stripe price yet.');
        $meta = array_filter(['type' => 'export_pass', 'user_id' => (string) $user->id, 'run' => $runPublicId]);
        $r = $this->stripe()->post('/checkout/sessions', [
            'mode' => 'payment',
            'line_items' => [['price' => $price, 'quantity' => 1]],
            'customer' => $this->customerId($user),
            'client_reference_id' => (string) $user->id,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $meta,
            'payment_intent_data' => ['metadata' => $meta],
        ]);
        if (! $r->successful() || ! $r->json('url')) {
            Log::warning('Stripe export-pass checkout failed', ['status' => $r->status(), 'error' => $r->json('error.message')]);
            throw new RuntimeException('We couldn’t start the payment page. Please try again in a moment.');
        }

        return $r->json('url');
    }

    /** The success redirect for an Export Pass: confirm with Stripe, then add the credit once. */
    public function completeExportPass(User $user, string $sessionId): OneTimePurchase
    {
        $r = $this->stripe()->get('/checkout/sessions/'.rawurlencode($sessionId));
        if (! $r->successful() || (string) $r->json('client_reference_id') !== (string) $user->id) {
            throw new RuntimeException('We couldn’t confirm that payment.');
        }
        if ($r->json('status') !== 'complete' || $r->json('payment_status') !== 'paid') {
            throw new RuntimeException('The payment isn’t complete yet.');
        }

        $this->lastExportRun = $r->json('metadata.run');

        return $this->fulfillExportPass($user, $r->json());
    }

    private ?string $lastExportRun = null;

    /** The analysis (public id) an Export Pass checkout was started from, after completeExportPass(). */
    public function exportPassSessionRun(string $sessionId): ?string
    {
        return $this->lastExportRun;
    }

    /** Idempotent: the redirect and the webhook both arrive, and only one pass is ever created per Stripe session. */
    public function fulfillExportPass(User $user, array $session): OneTimePurchase
    {
        if (($session['metadata']['type'] ?? '') !== 'export_pass') {
            throw new RuntimeException('That payment isn’t an Export Pass.');
        }

        return DB::transaction(function () use ($user, $session) {
            $id = (string) $session['id'];
            if ($existing = OneTimePurchase::where('stripe_session_id', $id)->first()) {
                return $existing;
            }
            $amount = (int) ($session['amount_total'] ?? 0);
            $runId = ($public = $session['metadata']['run'] ?? null) ? \App\Models\AnalysisRun::where('public_id', $public)->where('user_id', $user->id)->value('id') : null;
            $purchase = OneTimePurchase::create(['user_id' => $user->id, 'analysis_run_id' => $runId, 'purchase_type' => 'export_pass', 'status' => 'paid', 'stripe_session_id' => $id, 'amount_cents' => $amount,
                'exports_remaining' => (int) config('redo.export_pass.exports_included', 3), 'saves_remaining' => (int) config('redo.export_pass.edits_included', 2)]);
            Transaction::firstOrCreate(['stripe_session_id' => $id], [
                'user_id' => $user->id, 'type' => 'export_pass', 'description' => 'Export Pass (one-time)', 'amount_cents' => $amount,
                'currency' => (string) ($session['currency'] ?? 'usd'), 'status' => 'paid',
                'stripe_payment_intent' => is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : null, 'paid_at' => now(),
            ]);

            return $purchase;
        });
    }

    // ---------------------------------------------------------------- changes

    public function changePlan(User $user, Plan $plan): void
    {
        $sub = $this->activeStripeSubscription($user) ?? throw new RuntimeException('No active subscription to change.');
        $price = $this->priceFor($plan) ?? throw new RuntimeException('This plan has no Stripe price yet.');
        $live = $this->stripe()->get('/subscriptions/'.$sub->stripe_subscription_id);
        $item = $live->json('items.data.0.id');
        if (! $live->successful() || ! $item) {
            throw new RuntimeException('We couldn’t read your current subscription.');
        }
        $r = $this->stripe()->post('/subscriptions/'.$sub->stripe_subscription_id, ['items' => [['id' => $item, 'price' => $price]], 'proration_behavior' => 'create_prorations', 'cancel_at_period_end' => 'false']);
        if (! $r->successful()) {
            throw new RuntimeException('Stripe couldn’t change your plan: '.($r->json('error.message') ?? 'please try again.'));
        }
        $this->activate($user, $plan, $sub->stripe_subscription_id, $this->ts($r->json('items.data.0.current_period_end') ?? $r->json('current_period_end')), false);
    }

    /** Going back to Free: keep what's paid for until the period ends. */
    public function cancelAtPeriodEnd(User $user): ?Carbon
    {
        $sub = $this->activeStripeSubscription($user);
        if (! $sub) {
            return null;
        }
        $r = $this->stripe()->post('/subscriptions/'.$sub->stripe_subscription_id, ['cancel_at_period_end' => 'true']);
        if (! $r->successful()) {
            throw new RuntimeException('Stripe couldn’t cancel the subscription: '.($r->json('error.message') ?? 'please try again.'));
        }
        $end = $this->ts($r->json('items.data.0.current_period_end') ?? $r->json('current_period_end'));
        $sub->update(['cancel_at_period_end' => true, 'current_period_end' => $end ?? $sub->current_period_end]);

        return $end;
    }

    public function portalUrl(User $user, string $returnUrl): string
    {
        $r = $this->stripe()->post('/billing_portal/sessions', ['customer' => $this->customerId($user), 'return_url' => $returnUrl]);
        if (! $r->successful() || ! $r->json('url')) {
            throw new RuntimeException('We couldn’t open the billing page. Please try again in a moment.');
        }

        return $r->json('url');
    }

    public function activeStripeSubscription(User $user): ?Subscription
    {
        return $user->subscriptions()->where('status', 'active')->whereNotNull('stripe_subscription_id')->latest('started_at')->first();
    }

    // ---------------------------------------------------------------- webhooks

    public function verifySignature(string $payload, ?string $header, int $tolerance = 300): bool
    {
        $secret = (string) config('services.stripe.webhook_secret');
        if ($secret === '' || ! $header) {
            return false;
        }
        $parts = [];
        foreach (explode(',', $header) as $kv) {
            [$k, $v] = array_pad(explode('=', trim($kv), 2), 2, '');
            $parts[$k][] = $v;
        }
        $t = (int) ($parts['t'][0] ?? 0);
        if ($t === 0 || abs(time() - $t) > $tolerance) {
            return false;
        }
        $expected = hash_hmac('sha256', $t.'.'.$payload, $secret);
        foreach ($parts['v1'] ?? [] as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }

    public function handleEvent(array $event): void
    {
        $obj = $event['data']['object'] ?? [];
        switch ($event['type'] ?? '') {
            case 'checkout.session.completed':
                $user = User::find((int) ($obj['client_reference_id'] ?? $obj['metadata']['user_id'] ?? 0));
                if ($user && ($obj['mode'] ?? '') === 'payment' && ($obj['payment_status'] ?? '') === 'paid' && ($obj['metadata']['type'] ?? '') === 'export_pass') {
                    $this->fulfillExportPass($user, $obj);
                }
                if ($user && ($obj['mode'] ?? '') === 'subscription' && in_array($obj['payment_status'] ?? '', ['paid', 'no_payment_required'], true)) {
                    $this->activateFromSession($user, $obj);
                }
                break;
            case 'customer.subscription.updated':
                $sub = Subscription::where('stripe_subscription_id', $obj['id'] ?? '')->first();
                if (! $sub) {
                    break;
                }
                if (in_array($obj['status'] ?? '', ['canceled', 'unpaid', 'incomplete_expired'], true)) {
                    $this->revertToFree($sub->user);
                    break;
                }
                $plan = $this->planForPrice($obj['items']['data'][0]['price']['id'] ?? null);
                $sub->update(['cancel_at_period_end' => (bool) ($obj['cancel_at_period_end'] ?? false), 'current_period_end' => $this->ts($obj['items']['data'][0]['current_period_end'] ?? $obj['current_period_end'] ?? null) ?? $sub->current_period_end] + ($plan ? ['plan_id' => $plan->id] : []));
                break;
            case 'customer.subscription.deleted':
                $sub = Subscription::where('stripe_subscription_id', $obj['id'] ?? '')->first();
                $sub && $this->revertToFree($sub->user);
                break;
            case 'invoice.paid':
                $this->recordInvoice($obj);
                break;
            case 'invoice.payment_failed':
                Log::warning('Stripe payment failed', ['customer' => $obj['customer'] ?? null]);
                break;
        }
    }

    // ---------------------------------------------------------------- internals

    private function activateFromSession(User $user, array $session): Subscription
    {
        $plan = Plan::where('plan_key', $session['metadata']['plan_key'] ?? '')->first() ?? throw new RuntimeException('Unknown plan on that payment.');
        $subId = is_array($session['subscription'] ?? null) ? ($session['subscription']['id'] ?? null) : ($session['subscription'] ?? null);
        $end = is_array($session['subscription'] ?? null) ? $this->ts($session['subscription']['items']['data'][0]['current_period_end'] ?? $session['subscription']['current_period_end'] ?? null) : null;
        if (! empty($session['customer']) && ! $user->stripe_customer_id) {
            $user->forceFill(['stripe_customer_id' => $session['customer']])->save();
        }

        $sub = $this->activate($user, $plan, $subId, $end, false);
        $this->recordSessionPayment($user, $plan, $session, $subId);

        return $sub;
    }

    /** The first payment of a subscription, saved once (the success redirect and the webhook both get here). */
    private function recordSessionPayment(User $user, Plan $plan, array $session, ?string $subId): void
    {
        if (empty($session['id'])) {
            return;
        }
        Transaction::firstOrCreate(['stripe_session_id' => $session['id']], [
            'user_id' => $user->id, 'type' => 'subscription', 'plan_id' => $plan->id, 'description' => $plan->name.' plan — monthly',
            'amount_cents' => (int) ($session['amount_total'] ?? $plan->price_monthly_cents), 'currency' => (string) ($session['currency'] ?? 'usd'),
            'status' => 'paid', 'stripe_invoice_id' => is_string($session['invoice'] ?? null) ? $session['invoice'] : null,
            'stripe_subscription_id' => $subId, 'paid_at' => now(),
        ]);
    }

    /** Renewals and plan-change charges arrive as paid invoices. The first invoice is already saved from its checkout session. */
    private function recordInvoice(array $inv): void
    {
        $id = (string) ($inv['id'] ?? '');
        $subId = (string) ($inv['subscription'] ?? $inv['parent']['subscription_details']['subscription'] ?? '');
        if ($id === '' || Transaction::where('stripe_invoice_id', $id)->exists()) {
            return;
        }
        $sub = $subId !== '' ? Subscription::where('stripe_subscription_id', $subId)->first() : null;
        if ($sub && Transaction::where('stripe_subscription_id', $subId)->whereNull('stripe_invoice_id')->where('created_at', '>', now()->subMinutes(15))->exists()) {
            Transaction::where('stripe_subscription_id', $subId)->whereNull('stripe_invoice_id')->update(['stripe_invoice_id' => $id]); // the first payment, saved from its checkout session
            return;
        }
        if (! $sub) {
            return; // the checkout redirect/webhook saves the first payment and creates the subscription
        }
        Transaction::create([
            'user_id' => $sub->user_id, 'type' => 'subscription', 'plan_id' => $sub->plan_id, 'description' => $sub->plan->name.' plan — monthly',
            'amount_cents' => (int) ($inv['amount_paid'] ?? 0), 'currency' => (string) ($inv['currency'] ?? 'usd'), 'status' => 'paid',
            'stripe_invoice_id' => $id, 'stripe_subscription_id' => $subId, 'stripe_payment_intent' => is_string($inv['payment_intent'] ?? null) ? $inv['payment_intent'] : null, 'paid_at' => now(),
        ]);
    }

    private function activate(User $user, Plan $plan, ?string $stripeSubId, ?Carbon $periodEnd, bool $cancelAtEnd): Subscription
    {
        return DB::transaction(function () use ($user, $plan, $stripeSubId, $periodEnd, $cancelAtEnd) {
            if ($stripeSubId && ($existing = Subscription::where('stripe_subscription_id', $stripeSubId)->first())) { // webhook and redirect both arrive: once
                $existing->update(['plan_id' => $plan->id, 'status' => 'active', 'canceled_at' => null, 'current_period_end' => $periodEnd ?? $existing->current_period_end, 'cancel_at_period_end' => $cancelAtEnd]);
                $user->subscriptions()->where('status', 'active')->where('id', '!=', $existing->id)->update(['status' => 'canceled', 'canceled_at' => now()]);

                return $existing;
            }
            $user->subscriptions()->where('status', 'active')->update(['status' => 'canceled', 'canceled_at' => now()]);

            return Subscription::create(['user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active', 'started_at' => now(), 'stripe_subscription_id' => $stripeSubId, 'current_period_end' => $periodEnd, 'cancel_at_period_end' => $cancelAtEnd]);
        });
    }

    public function revertToFree(User $user): void
    {
        $free = Plan::where('plan_key', 'free')->first();
        if (! $free) {
            return;
        }
        DB::transaction(function () use ($user, $free) {
            $user->subscriptions()->where('status', 'active')->update(['status' => 'canceled', 'canceled_at' => now()]);
            Subscription::create(['user_id' => $user->id, 'plan_id' => $free->id, 'status' => 'active', 'started_at' => now()]);
        });
    }

    private function customerId(User $user): string
    {
        if ($user->stripe_customer_id) {
            return $user->stripe_customer_id;
        }
        $r = $this->stripe()->post('/customers', ['email' => $user->email, 'name' => $user->name, 'metadata' => ['user_id' => (string) $user->id]]);
        if (! $r->successful() || ! $r->json('id')) {
            throw new RuntimeException('We couldn’t set up billing for your account. Please try again.');
        }
        $user->forceFill(['stripe_customer_id' => $r->json('id')])->save();

        return $r->json('id');
    }

    private function ts(mixed $unix): ?Carbon
    {
        return is_numeric($unix) ? Carbon::createFromTimestamp((int) $unix) : null;
    }

    private function stripe(): PendingRequest
    {
        return Http::baseUrl(self::API)->withBasicAuth((string) config('services.stripe.secret'), '')->asForm()->timeout(20);
    }
}
