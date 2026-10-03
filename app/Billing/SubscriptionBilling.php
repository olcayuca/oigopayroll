<?php

namespace App\Billing;

use App\Enums\AuditEvent;
use App\Enums\ContractFeeType;
use App\Models\BillingCheckout;
use App\Models\Employee;
use App\Models\Firm;
use App\Models\FirmContract;
use App\Models\FirmPaymentCard;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\BillingUpdate;
use App\Notifications\Recipients;
use App\Support\Audit;
use App\Support\FirmSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Abonelik: invoices from the contract (Admin → Sözleşmeler) at the start of every period, automatic charge of
 * the saved card when due (retried, then reported), payment on iyzico's page, card saving.
 *
 * Contract fees are KDV hariç; config/billing.php holds the rate and retry rules.
 */
class SubscriptionBilling
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function configured(): bool
    {
        return $this->gateway->configured();
    }

    /**
     * Issue the current period's invoice of every running contract (scheduler, daily; and when the page is opened).
     */
    public function issueInvoices(?Firm $firm = null): int
    {
        $issued = 0;

        FirmContract::query()->running()->whereNotNull('fee_amount')->where('fee_amount', '>', 0)
            ->when($firm, fn ($query) => $query->where('firm_id', $firm?->id))
            ->with('firm')
            ->each(function (FirmContract $contract) use (&$issued) {
                $issued += $this->issueFor($contract) ? 1 : 0;
            });

        return $issued;
    }

    /**
     * Charge due invoices of firms with automatic payment and a saved card (scheduler, daily).
     */
    public function chargeDue(): int
    {
        $charged = 0;

        Invoice::query()->open()->whereDate('due_on', '<=', today())
            ->where('attempts', '<', (int) config('billing.max_attempts'))
            ->where(fn ($query) => $query->whereNull('next_attempt_on')->orWhereDate('next_attempt_on', '<=', today()))
            ->with('firm')
            ->each(function (Invoice $invoice) use (&$charged) {
                if (FirmSettings::autoPay($invoice->firm) && $this->defaultCard($invoice->firm) !== null) {
                    $charged += $this->charge($invoice) ? 1 : 0;
                }
            });

        return $charged;
    }

    /**
     * Charge the firm's saved card for an open invoice. Failures are recorded and retried later.
     */
    public function charge(Invoice $invoice, string $ip = '127.0.0.1'): bool
    {
        $card = $this->defaultCard($invoice->firm);

        if (! $invoice->isOpen() || $card === null || ! $this->configured()) {
            return false;
        }

        $result = $this->gateway->chargeCard($invoice->firm, $card, 'invoice-'.$invoice->id.'-'.($invoice->attempts + 1),
            (string) $invoice->number, $invoice->description, $this->decimal($invoice->total), $invoice->currency, $ip);

        if ($result->success) {
            $this->markPaid($invoice, 'card_auto', $result->paymentId, $card);

            return true;
        }

        $attempts = $invoice->attempts + 1;
        $final = $attempts >= (int) config('billing.max_attempts');
        $invoice->update([
            'status' => Invoice::FAILED,
            'attempts' => $attempts,
            'last_attempt_at' => now(),
            'next_attempt_on' => $final ? null : today()->addDays((int) config('billing.retry_days')),
            'failure_message' => Str::limit((string) $result->error, 250),
        ]);

        Audit::log(AuditEvent::BillingPaymentFailed, "Otomatik ödeme alınamadı: {$invoice->number} ({$result->error})", $invoice->firm, ['invoice_id' => $invoice->id]);

        $text = "{$invoice->number} · {$invoice->money($invoice->total)} · {$card->label()}: {$result->error}. "
            .($final ? 'Otomatik deneme sona erdi; panelden kartla ödeyin veya kartınızı güncelleyin.' : 'Ödeme '.config('billing.retry_days').' gün sonra yeniden denenecek.');
        Notification::send(Recipients::billing($invoice->firm)->merge(Recipients::superAdmins())->unique('id'),
            new BillingUpdate($invoice->firm, 'Abonelik ödemesi alınamadı', $text, 'danger', critical: true));

        return false;
    }

    /**
     * Open iyzico's payment page: pay an invoice, or (no invoice) save a card with a small charge that is cancelled.
     *
     * @return string the page to redirect to
     */
    public function startCheckout(Firm $firm, User $user, ?Invoice $invoice, string $ip): string
    {
        if (! $this->configured()) {
            throw ValidationException::withMessages(['payment' => 'Kartla ödeme henüz etkin değil.']);
        }

        if ($invoice !== null && (! $invoice->isOpen() || $invoice->firm_id !== $firm->id)) {
            throw ValidationException::withMessages(['payment' => 'Bu fatura ödenemez.']);
        }

        $checkout = BillingCheckout::create([
            'firm_id' => $firm->id, 'invoice_id' => $invoice?->id, 'user_id' => $user->id,
            'purpose' => $invoice ? 'invoice' : 'card', 'conversation_id' => (string) Str::uuid(),
        ]);

        $start = $this->gateway->startCheckout(
            $firm, $user, $checkout->conversation_id,
            $invoice->number ?? 'KART-'.$checkout->id,
            $invoice->description ?? 'Kart doğrulama (hemen iade edilir)',
            $invoice ? $this->decimal($invoice->total) : (string) config('billing.card_verification_amount'),
            $invoice->currency ?? 'TRY',
            route('billing.callback'),
            $this->defaultCard($firm)?->card_user_key,
            $ip,
        );

        if (! $start->success || $start->url === null) {
            $checkout->update(['status' => 'failed', 'message' => $start->error]);

            throw ValidationException::withMessages(['payment' => $start->error ?? 'Ödeme sayfası açılamadı.']);
        }

        $checkout->update(['token' => $start->token]);

        return $start->url;
    }

    /**
     * iyzico posted back: verify the payment, record it, keep the card if the user chose to save it.
     */
    public function completeCheckout(string $token, string $ip): BillingCheckout
    {
        $checkout = BillingCheckout::query()->where('token', $token)->where('status', 'started')->firstOrFail();
        $result = $this->gateway->completeCheckout($token, $checkout->conversation_id);

        if (! $result->success) {
            $checkout->update(['status' => 'failed', 'message' => Str::limit((string) $result->error, 250)]);

            return $checkout;
        }

        $card = $result->savedCard() ? $this->saveCard($checkout->firm, $checkout->user, $result) : null;

        if ($checkout->purpose === 'invoice' && $checkout->invoice !== null) {
            $this->markPaid($checkout->invoice, 'card', $result->paymentId, $card);
            $message = 'Ödemeniz alındı.'.($card ? ' Kartınız sonraki ödemeler için kaydedildi.' : '');
        } else {
            // Card verification charge: give the money back right away.
            if ($result->paymentId !== null) {
                $this->gateway->cancel($result->paymentId, $ip);
            }
            $message = $card ? 'Kartınız kaydedildi; doğrulama tutarı iade edildi.' : 'Kart kaydedilmedi: ödeme sayfasında "Kartımı kaydet" seçeneğini işaretleyin.';
        }

        $checkout->update(['status' => $card || $checkout->purpose === 'invoice' ? 'completed' : 'failed', 'message' => $message]);

        return $checkout;
    }

    public function removeCard(FirmPaymentCard $card, User $user): void
    {
        $this->gateway->deleteCard($card);
        $card->delete();

        Audit::log(AuditEvent::BillingCardRemoved, "Kayıtlı kart kaldırıldı: {$card->label()}", $card->firm, [], $user);
    }

    public function markPaidManually(Invoice $invoice, User $user): void
    {
        if (! $invoice->isOpen()) {
            return;
        }

        $this->markPaid($invoice, 'transfer', null, null, $user);
    }

    public function void(Invoice $invoice, User $user): void
    {
        if ($invoice->status === Invoice::PAID) {
            throw ValidationException::withMessages(['invoice' => 'Ödenmiş fatura iptal edilemez.']);
        }

        $invoice->update(['status' => Invoice::VOID, 'next_attempt_on' => null]);
        Audit::log(AuditEvent::BillingInvoiceVoided, "Fatura iptal edildi: {$invoice->number}", $invoice->firm, ['invoice_id' => $invoice->id], $user);
    }

    public function defaultCard(Firm $firm): ?FirmPaymentCard
    {
        return FirmPaymentCard::query()->where('firm_id', $firm->id)->orderByDesc('is_default')->latest('id')->first();
    }

    /**
     * The period of a contract that contains $date: [start, end|null], or null when nothing is billed now.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable|null}|null
     */
    public static function periodFor(FirmContract $contract, CarbonImmutable $date): ?array
    {
        $start = CarbonImmutable::parse($contract->starts_on);

        if ($date->lt($start)) {
            return null;
        }

        [$periodStart, $periodEnd] = match ($contract->fee_type) {
            ContractFeeType::OneTime => [$start, null],
            ContractFeeType::Yearly => (function () use ($start, $date) {
                $from = $start->addYearsNoOverflow((int) $start->diffInYears($date));

                return [$from, $from->addYearsNoOverflow(1)->subDay()];
            })(),
            default => (function () use ($start, $date) {
                $from = $start->addMonthsNoOverflow((int) $start->diffInMonths($date));

                return [$from, $from->addMonthsNoOverflow(1)->subDay()];
            })(),
        };

        if ($contract->ends_on !== null && $periodEnd !== null && $periodEnd->gt($contract->ends_on)) {
            $periodEnd = CarbonImmutable::parse($contract->ends_on);
        }

        return [$periodStart, $periodEnd];
    }

    private function issueFor(FirmContract $contract): bool
    {
        $period = self::periodFor($contract, CarbonImmutable::today());

        if ($period === null) {
            return false;
        }

        [$start, $end] = $period;

        if (Invoice::query()->where('firm_contract_id', $contract->id)->whereDate('period_start', $start)->exists()
            || ($contract->fee_type === ContractFeeType::OneTime && Invoice::query()->where('firm_contract_id', $contract->id)->exists())) {
            return false;
        }

        $quantity = $contract->fee_type === ContractFeeType::MonthlyPerEmployee
            ? Employee::query()->where('firm_id', $contract->firm_id)->where('status', Employee::ACTIVE)->count()
            : 1;

        if ($quantity === 0) {
            return false;
        }

        $unit = (float) $contract->fee_amount;
        $amount = round($unit * $quantity, 2);
        $rate = (float) config('billing.vat_rate');
        $vat = round($amount * $rate / 100, 2);

        $invoice = DB::transaction(function () use ($contract, $start, $end, $quantity, $unit, $amount, $rate, $vat) {
            $invoice = Invoice::create([
                'firm_id' => $contract->firm_id, 'firm_contract_id' => $contract->id,
                'period_start' => $start, 'period_end' => $end,
                'description' => "{$contract->title} · {$contract->fee_type->label()}".($quantity > 1 ? " ({$quantity} personel)" : ''),
                'quantity' => $quantity, 'unit_price' => $unit, 'amount' => $amount, 'vat_rate' => $rate, 'vat_amount' => $vat,
                'total' => round($amount + $vat, 2), 'currency' => $contract->currency ?: 'TRY',
                'status' => Invoice::PENDING, 'due_on' => $start->isPast() ? today() : $start,
            ]);
            $invoice->update(['number' => config('billing.invoice_prefix').'-'.$start->year.'-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT)]);

            return $invoice;
        });

        $autoPay = FirmSettings::autoPay($contract->firm) && $this->defaultCard($contract->firm) !== null && $this->configured();
        Notification::send(Recipients::billing($contract->firm), new BillingUpdate($contract->firm, "Yeni fatura: {$invoice->number}",
            "{$invoice->description} · {$invoice->periodLabel()} · {$invoice->money($invoice->total)} (KDV dahil). "
            .($autoPay ? 'Kayıtlı kartınızdan otomatik tahsil edilecek.' : 'Panelden kartla ödeyebilir veya havale ile ödeyebilirsiniz.')));

        return true;
    }

    private function markPaid(Invoice $invoice, string $method, ?string $paymentId, ?FirmPaymentCard $card, ?User $user = null): void
    {
        $invoice->update([
            'status' => Invoice::PAID, 'paid_at' => now(), 'payment_method' => $method,
            'provider_payment_id' => $paymentId, 'firm_payment_card_id' => $card?->id, 'next_attempt_on' => null, 'failure_message' => null,
        ]);

        Audit::log(AuditEvent::BillingInvoicePaid, "Fatura ödendi: {$invoice->number} · {$invoice->money($invoice->total)} · ".Invoice::METHODS[$method],
            $invoice->firm, ['invoice_id' => $invoice->id], $user);

        Notification::send(Recipients::billing($invoice->firm), new BillingUpdate($invoice->firm, "Ödeme alındı: {$invoice->number}",
            "{$invoice->money($invoice->total)} · ".Invoice::METHODS[$method].($card ? " · {$card->label()}" : '').'. Teşekkür ederiz.', 'success'));
    }

    private function saveCard(Firm $firm, ?User $user, GatewayResult $result): FirmPaymentCard
    {
        return DB::transaction(function () use ($firm, $user, $result) {
            FirmPaymentCard::query()->where('firm_id', $firm->id)->update(['is_default' => false]);

            $card = FirmPaymentCard::create([
                'firm_id' => $firm->id, 'provider' => 'iyzico',
                'card_user_key' => (string) $result->cardUserKey, 'card_token' => (string) $result->cardToken,
                'last_four' => $result->lastFour, 'bin_number' => $result->binNumber,
                'card_association' => $result->cardAssociation, 'card_family' => $result->cardFamily,
                'is_default' => true, 'added_by' => $user?->id,
            ]);

            Audit::log(AuditEvent::BillingCardSaved, "Kart kaydedildi: {$card->label()}", $firm, [], $user);

            return $card;
        });
    }

    private function decimal(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
