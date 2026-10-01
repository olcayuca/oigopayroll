<?php

namespace App\Models;

use App\Enums\ContractFeeType;
use App\Enums\ContractStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * HRD service contract with a client firm.
 *
 * @property int $id
 * @property int $firm_id
 * @property string $contract_no
 * @property string $title
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on null = open-ended
 * @property bool $auto_renew
 * @property int $notice_days fesih bildirim süresi
 * @property ContractFeeType $fee_type
 * @property string|null $fee_amount
 * @property string $currency
 * @property int|null $document_id
 * @property string|null $notes
 * @property Carbon|null $terminated_on
 * @property string|null $termination_reason
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Firm $firm
 * @property-read FirmDocument|null $document
 */
#[Fillable([
    'firm_id', 'contract_no', 'title', 'starts_on', 'ends_on', 'auto_renew', 'notice_days', 'fee_type', 'fee_amount',
    'currency', 'document_id', 'notes', 'terminated_on', 'termination_reason', 'created_by',
])]
class FirmContract extends Model
{
    /** Extra days before the notice deadline when an ending contract is flagged. */
    public const WARNING_DAYS = 30;

    /**
     * @return BelongsTo<Firm, $this>
     */
    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    /**
     * @return BelongsTo<FirmDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(FirmDocument::class);
    }

    public function status(): ContractStatus
    {
        $today = today();

        return match (true) {
            $this->terminated_on !== null && $this->terminated_on->lte($today) => ContractStatus::Terminated,
            $this->starts_on->gt($today) => ContractStatus::Upcoming,
            $this->ends_on !== null && $this->ends_on->lt($today) => ContractStatus::Expired,
            $this->ends_on !== null && $this->ends_on->lte($this->warningDate()) => ContractStatus::Ending,
            default => ContractStatus::Active,
        };
    }

    /**
     * Last day to give notice of non-renewal / termination.
     */
    public function noticeDeadline(): ?CarbonInterface
    {
        return $this->ends_on?->copy()->subDays($this->notice_days);
    }

    /**
     * Contracts ending within notice period + WARNING_DAYS, still running.
     *
     * @param  Builder<FirmContract>  $query
     */
    public function scopeEndingSoon(Builder $query): void
    {
        $query->whereNull('terminated_on')
            ->whereNotNull('ends_on')
            ->whereDate('ends_on', '>=', today())
            ->whereRaw(self::endsWithinExpression(), [today()->toDateString()]);
    }

    /**
     * Running contracts: started, not ended, not terminated.
     *
     * @param  Builder<FirmContract>  $query
     */
    public function scopeRunning(Builder $query): void
    {
        $query->where(fn ($inner) => $inner->whereNull('terminated_on')->orWhereDate('terminated_on', '>', today()))
            ->whereDate('starts_on', '<=', today())
            ->where(fn ($inner) => $inner->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()));
    }

    private function warningDate(): CarbonInterface
    {
        return today()->addDays($this->notice_days + self::WARNING_DAYS);
    }

    /**
     * ends_on <= today + notice_days + WARNING_DAYS, portable across MySQL and SQLite.
     *
     * @return literal-string
     */
    private static function endsWithinExpression(): string
    {
        $days = 'notice_days + '.self::WARNING_DAYS;

        return DB::connection()->getDriverName() === 'sqlite'
            ? "date(ends_on) <= date(?, '+' || ({$days}) || ' days')"
            : "ends_on <= DATE_ADD(?, INTERVAL ({$days}) DAY)";
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'terminated_on' => 'date',
            'auto_renew' => 'boolean',
            'notice_days' => 'integer',
            'fee_type' => ContractFeeType::class,
            'fee_amount' => 'decimal:2',
        ];
    }
}
