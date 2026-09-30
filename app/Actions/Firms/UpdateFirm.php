<?php

namespace App\Actions\Firms;

use App\Enums\FirmStatus;
use App\Models\Firm;
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

        return $firm;
    }

    /**
     * Suspend an active firm: nothing can be created or changed under it.
     */
    public function deactivate(Firm $firm): Firm
    {
        $this->ensureStatus($firm, FirmStatus::Active, 'Yalnızca aktif firmalar pasife alınabilir.');

        $firm->update(['status' => FirmStatus::Passive]);

        return $firm;
    }

    public function reactivate(Firm $firm): Firm
    {
        $this->ensureStatus($firm, FirmStatus::Passive, 'Yalnızca pasif firmalar yeniden aktifleştirilebilir.');

        $firm->update(['status' => FirmStatus::Active]);

        return $firm;
    }

    private function ensureStatus(Firm $firm, FirmStatus $expected, string $message): void
    {
        if ($firm->status !== $expected) {
            throw ValidationException::withMessages(['firm' => $message]);
        }
    }
}
