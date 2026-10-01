<?php

namespace App\Actions\Firms;

use App\Enums\AuditEvent;
use App\Enums\ContractFeeType;
use App\Models\FirmContract;
use App\Models\User;
use App\Support\Audit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HRD service contracts (admin only).
 */
class ManageContracts
{
    public const CURRENCIES = ['TRY', 'USD', 'EUR'];

    /**
     * @param  array<string, mixed>  $input
     */
    public function save(?FirmContract $contract, array $input, ?User $actor = null): FirmContract
    {
        $input = array_map(fn ($value) => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value, $input);
        // A contract stays with its firm.
        if ($contract !== null) {
            $input['firm_id'] = $contract->firm_id;
        }

        $firmId = $input['firm_id'] ?? null;

        $data = Validator::make($input, [
            'firm_id' => ['required', 'integer', Rule::exists('firms', 'id')],
            'contract_no' => ['required', 'string', 'max:50', Rule::unique('firm_contracts', 'contract_no')->ignore($contract)],
            'title' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on', 'required_if_accepted:auto_renew'],
            'auto_renew' => ['boolean'],
            'notice_days' => ['required', 'integer', 'min:0', 'max:365'],
            'fee_type' => ['required', Rule::enum(ContractFeeType::class)],
            'fee_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'currency' => ['required', Rule::in(self::CURRENCIES)],
            'document_id' => ['nullable', 'integer', Rule::exists('firm_documents', 'id')->where('firm_id', $firmId)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['ends_on.required_if_accepted' => 'Otomatik yenilenen sözleşmenin bitiş tarihi olmalıdır.'], self::attributes())->validate();

        $contract ??= new FirmContract(['firm_id' => $data['firm_id'], 'created_by' => $actor?->id]);
        $isNew = ! $contract->exists;
        unset($data['firm_id']);

        $contract->fill([...$data, 'auto_renew' => (bool) ($data['auto_renew'] ?? false)])->save();

        if ($isNew || $contract->wasChanged()) {
            Audit::log(AuditEvent::ContractSaved, "{$contract->firm->name}: sözleşme ".($isNew ? 'eklendi' : 'güncellendi')." ({$contract->contract_no})", $contract->firm, [
                'contract_id' => $contract->id, 'fields' => $isNew ? null : array_keys($contract->getChanges()),
            ], $actor);
        }

        return $contract;
    }

    public function terminate(FirmContract $contract, string $date, string $reason, ?User $actor = null): FirmContract
    {
        $data = Validator::make(['terminated_on' => $date, 'termination_reason' => trim($reason)], [
            'terminated_on' => ['required', 'date', 'after_or_equal:'.$contract->starts_on->toDateString()],
            'termination_reason' => ['required', 'string', 'max:500'],
        ], [], ['terminated_on' => 'Fesih tarihi', 'termination_reason' => 'Fesih gerekçesi'])->validate();

        if ($contract->terminated_on !== null) {
            throw ValidationException::withMessages(['terminated_on' => 'Sözleşme zaten feshedilmiş.']);
        }

        $contract->update([...$data, 'auto_renew' => false]);

        Audit::log(AuditEvent::ContractTerminated, "{$contract->firm->name}: sözleşme feshedildi ({$contract->contract_no})", $contract->firm, ['contract_id' => $contract->id], $actor);

        return $contract;
    }

    /**
     * Extend auto-renewing contracts whose end date has passed by their original term.
     *
     * @return int contracts renewed
     */
    public function renewExpired(): int
    {
        $renewed = 0;

        FirmContract::query()->with('firm')
            ->where('auto_renew', true)->whereNull('terminated_on')
            ->whereNotNull('ends_on')->whereDate('ends_on', '<', today())
            ->each(function (FirmContract $contract) use (&$renewed) {
                $oldEnd = $contract->ends_on?->toDateString();
                $contract->update(['ends_on' => self::nextEnd($contract)]);
                $renewed++;

                Audit::log(AuditEvent::ContractRenewed, "{$contract->firm->name}: sözleşme otomatik yenilendi ({$contract->contract_no}), yeni bitiş {$contract->ends_on?->format('d.m.Y')}", $contract->firm, [
                    'contract_id' => $contract->id, 'previous_end' => $oldEnd,
                ]);
            });

        return $renewed;
    }

    /**
     * Add whole terms (months when the term is a month multiple, otherwise days) until the end is not in the past.
     */
    public static function nextEnd(FirmContract $contract): CarbonInterface
    {
        $start = $contract->starts_on->copy();
        $end = ($contract->ends_on ?? today())->copy();
        $months = (int) $start->diffInMonths($end->copy()->addDay());
        $isMonthTerm = $months > 0 && $start->copy()->addMonths($months)->subDay()->isSameDay($end);
        $days = max(1, (int) $start->diffInDays($end) + 1);

        while ($end->lt(today())) {
            $end = $isMonthTerm ? $end->addDay()->addMonthsNoOverflow($months)->subDay() : $end->addDays($days);
        }

        return $end;
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'firm_id' => 'Firma',
            'contract_no' => 'Sözleşme no',
            'title' => 'Başlık',
            'starts_on' => 'Başlangıç',
            'ends_on' => 'Bitiş',
            'auto_renew' => 'Otomatik yenileme',
            'notice_days' => 'Fesih bildirim süresi',
            'fee_type' => 'Ücret tipi',
            'fee_amount' => 'Ücret',
            'currency' => 'Para birimi',
            'document_id' => 'Sözleşme belgesi',
            'notes' => 'Not',
        ];
    }
}
