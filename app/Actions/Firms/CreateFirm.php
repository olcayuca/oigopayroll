<?php

namespace App\Actions\Firms;

use App\Enums\FirmSource;
use App\Enums\FirmStatus;
use App\Models\Firm;
use App\Models\User;
use App\Validation\FirmRules;
use Illuminate\Support\Facades\Validator;

/**
 * HRD creates a firm on the client's behalf. It is active immediately and
 * needs no client membership; client users can be added later.
 */
class CreateFirm
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $admin, array $input, bool $activate = true): Firm
    {
        $data = Validator::make(FirmRules::clean($input), FirmRules::rules(), [], FirmRules::attributes())->validate();

        return Firm::create([
            ...$data,
            'status' => $activate ? FirmStatus::Active : FirmStatus::Pending,
            'source' => FirmSource::Hrd,
            'created_by' => $admin->id,
            'reviewed_by' => $activate ? $admin->id : null,
            'reviewed_at' => $activate ? now() : null,
        ]);
    }
}
