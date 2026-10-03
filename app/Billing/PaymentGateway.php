<?php

namespace App\Billing;

use App\Models\Firm;
use App\Models\FirmPaymentCard;
use App\Models\User;

/**
 * Card payments for subscriptions. Card numbers never pass through the system: cards are entered on the
 * provider's hosted page (startCheckout) and charged later with the stored tokens (chargeCard).
 */
interface PaymentGateway
{
    public function configured(): bool;

    /**
     * Open the hosted payment page for an amount; the provider posts back to $callbackUrl with a token.
     */
    public function startCheckout(Firm $firm, User $user, string $conversationId, string $basketId, string $description,
        string $amount, string $currency, string $callbackUrl, ?string $cardUserKey, string $ip): CheckoutStart;

    public function completeCheckout(string $token, string $conversationId): GatewayResult;

    /**
     * Charge a stored card (recurring, no 3-D Secure).
     */
    public function chargeCard(Firm $firm, FirmPaymentCard $card, string $conversationId, string $basketId, string $description,
        string $amount, string $currency, string $ip): GatewayResult;

    public function cancel(string $paymentId, string $ip): bool;

    public function deleteCard(FirmPaymentCard $card): void;
}
