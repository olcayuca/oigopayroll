<?php

namespace App\Billing;

final readonly class CheckoutStart
{
    public function __construct(
        public bool $success,
        public ?string $token = null,
        public ?string $url = null,
        public ?string $error = null,
    ) {}
}
