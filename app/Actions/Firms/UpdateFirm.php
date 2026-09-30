<?php

namespace App\Actions\Firms;

use App\Enums\AuditEvent;
use App\Enums\FirmStatus;
use App\Models\Firm;
use App\Support\Audit;
use App\Validation\FirmRules;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Edit firm details and move firms between active and passive.
 */
class UpdateFirm
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function update(Firm $firm, array $input): Firm
    {
        $firm->update(Validator::make(FirmRules::clean($input), FirmRules::rules($firm), [], FirmRules::attributes())->validate());

        if ($firm->wasChanged()) {
            Audit::log(AuditEvent::FirmUpdated, "Firma bilgileri güncellendi: {$firm->name}", $firm, ['fields' => array_keys($firm->getChanges())]);
        }

        return $firm;
    }

    /**
     * Suspend an active firm: nothing can be created or changed under it.
     */
    public function deactivate(Firm $firm): Firm
    {
        $this->ensureStatus($firm, FirmStatus::Active, 'Yalnızca aktif firmalar pasife alınabilir.');

        $firm->update(['status' => FirmStatus::Passive]);
        Audit::log(AuditEvent::FirmDeactivated, "Firma pasife alındı: {$firm->name}", $firm);

        return $firm;
    }

    public function reactivate(Firm $firm): Firm
    {
        $this->ensureStatus($firm, FirmStatus::Passive, 'Yalnızca pasif firmalar yeniden aktifleştirilebilir.');

        $firm->update(['status' => FirmStatus::Active]);
        Audit::log(AuditEvent::FirmReactivated, "Firma aktifleştirildi: {$firm->name}", $firm);

        return $firm;
    }

    private function ensureStatus(Firm $firm, FirmStatus $expected, string $message): void
    {
        if ($firm->status !== $expected) {
            throw ValidationException::withMessages(['firm' => $message]);
        }
    }
}
