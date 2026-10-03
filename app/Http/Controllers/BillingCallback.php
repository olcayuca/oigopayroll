<?php

namespace App\Http\Controllers;

use App\Billing\SubscriptionBilling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * iyzico's hosted payment page posts the token here. The result is fetched from iyzico (signed), never
 * taken from the request; then the user goes back to Abonelik with the outcome.
 */
class BillingCallback extends Controller
{
    public function __invoke(Request $request, SubscriptionBilling $billing): RedirectResponse
    {
        $token = $request->string('token')->toString();
        abort_if($token === '', 400);

        $checkout = $billing->completeCheckout($token, (string) $request->ip());

        return redirect()->route('billing.index', ['odeme' => $checkout->status === 'completed' ? 'tamam' : 'hata', 'islem' => $checkout->id]);
    }
}
