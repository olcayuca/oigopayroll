<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/**
 * Abonelik faturası: one contract period (or a one-time fee) of a firm.
 *
 * @property int $id
 * @property int $firm_id
 * @property int|null $firm_contract_id
 * @property string|null $number
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property string $description
 * @property int $quantity
 * @property string $unit_price
 * @property string $amount
 * @property string $vat_rate
 * @property string $vat_amount
 * @property string $total
 * @property string $currency
 * @property string $status pending | paid | failed | void
 * @property Carbon $due_on
 * @property Carbon|null $paid_at
 * @property string|null $payment_method card_auto | card | transfer
 * @property int|null $firm_payment_card_id
 * @property string|null $provider_payment_id
 * @property int $attempts
 * @property Carbon|null $last_attempt_at
 * @property Carbon|null $next_attempt_on
 * @property string|null $failure_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Firm $firm
 * @property-read FirmContract|null $contract
 * @property-read FirmPaymentCard|null $card
 */
#[Fillable([
    'firm_id', 'firm_contract_id', 'number', 'period_start', 'period_end', 'description', 'quantity', 'unit_price', 'amount',
    'vat_rate', 'vat_amount', 'total', 'currency', 'status', 'due_on', 'paid_at', 'payment_method', 'firm_payment_card_id',
    'provider_payment_id', 'attempts', 'last_attempt_at', 'next_attempt_on', 'failure_message',
])]
class Invoice extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const VOID = 'void';

    public const STATUSES = [self::PENDING => 'Ödeme bekliyor', self::PAID => 'Ödendi', self::FAILED => 'Ödeme alınamadı', self::VOID => 'İptal'];

    public const STATUS_COLORS = [self::PENDING => 'amber', self::PAID => 'green', self::FAILED => 'red', self::VOID => 'zinc'];

    public const METHODS = ['card_auto' => 'Otomatik kart çekimi', 'card' => 'Kartla ödeme', 'transfer' => 'Havale / EFT'];

    /**
     * @return BelongsTo<Firm, $this>
     */
    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    /**
     * @return BelongsTo<FirmContract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(FirmContract::class, 'firm_contract_id');
    }

    /**
     * @return BelongsTo<FirmPaymentCard, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(FirmPaymentCard::class, 'firm_payment_card_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::PENDING, self::FAILED], true);
    }

    public function money(string $value): string
    {
        return Number::currency((float) $value, $this->currency, 'tr') ?: number_format((float) $value, 2, ',', '.').' '.$this->currency;
    }

    public function periodLabel(): string
    {
        return $this->period_start
            ? $this->period_start->format('d.m.Y').($this->period_end ? ' – '.$this->period_end->format('d.m.Y') : '')
            : $this->due_on->format('d.m.Y');
    }

    /**
     * Unpaid and due (pending or failed).
     *
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [self::PENDING, self::FAILED]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_on' => 'date',
            'paid_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'next_attempt_on' => 'date',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'quantity' => 'integer',
            'attempts' => 'integer',
        ];
    }
}
