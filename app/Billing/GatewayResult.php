<?php

namespace App\Billing;

/**
 * Outcome of a payment. Card data is only present when the card was saved at the provider.
 */
final readonly class GatewayResult
{
    public function __construct(
        public bool $success,
        public ?string $paymentId = null,
        public ?string $paidPrice = null,
        public ?string $error = null,
        public ?string $cardUserKey = null,
        public ?string $cardToken = null,
        public ?string $lastFour = null,
        public ?string $binNumber = null,
        public ?string $cardAssociation = null,
        public ?string $cardFamily = null,
    ) {}

    public function savedCard(): bool
    {
        return filled($this->cardUserKey) && filled($this->cardToken);
    }
}
