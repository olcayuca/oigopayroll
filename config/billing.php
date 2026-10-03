<?php

/*
 * Abonelik / lisans faturalama (App\Billing). Amounts come from the firm's contract (Admin → Sözleşmeler);
 * the contract fee is taken as KDV hariç.
 */
return [
    'vat_rate' => (float) env('BILLING_VAT_RATE', 20),

    'invoice_prefix' => env('BILLING_INVOICE_PREFIX', 'HRD'),

    // Failed automatic charges are retried every `retry_days`, at most `max_attempts` times in total.
    'retry_days' => (int) env('BILLING_RETRY_DAYS', 3),
    'max_attempts' => (int) env('BILLING_MAX_ATTEMPTS', 3),

    // Saving a card without an open invoice: this amount is charged on iyzico's page and cancelled right away.
    'card_verification_amount' => env('BILLING_CARD_VERIFICATION_AMOUNT', '1.00'),

    // iyzico needs a city and an 11-digit identity number for the buyer; used when the firm has none.
    'default_city' => env('BILLING_DEFAULT_CITY', 'Istanbul'),
    'default_identity_number' => env('BILLING_DEFAULT_IDENTITY_NUMBER', '11111111111'),
];
