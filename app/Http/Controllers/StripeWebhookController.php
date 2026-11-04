<?php

namespace App\Http\Controllers;

use App\Services\Billing\Billing;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, Billing $billing): Response
    {
        $payload = $request->getContent();
        if (! $billing->verifySignature($payload, $request->header('Stripe-Signature'))) {
            return response('Invalid signature', 400);
        }
        $event = json_decode($payload, true);
        if (! is_array($event)) {
            return response('Bad payload', 400);
        }
        $billing->handleEvent($event);

        return response('ok', 200);
    }
}
