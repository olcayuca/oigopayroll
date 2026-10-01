<?php

namespace App\Actions\Firms;

use App\Actions\Access\GrantAccess;
use App\Enums\AuditEvent;
use App\Enums\FirmSource;
use App\Enums\FirmStatus;
use App\Enums\Permission;
use App\Models\Firm;
use App\Models\User;
use App\Notifications\FirmAwaitingApproval;
use App\Notifications\Recipients;
use App\Support\Audit;
use App\Validation\FirmRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

/**
 * A client creates a firm from their own account. It waits for HRD approval.
 */
class RegisterFirm
{
    public function __construct(private GrantAccess $grantAccess)
    {
        //
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $user, array $input): Firm
    {
        $data = Validator::make(FirmRules::clean($input), FirmRules::rules(), [], FirmRules::attributes())->validate();

        $firm = DB::transaction(function () use ($user, $data) {
            $firm = Firm::create([
                ...$data,
                'status' => FirmStatus::Pending,
                'source' => FirmSource::Client,
                'created_by' => $user->id,
            ]);

            $this->grantAccess->handle($user, $firm, Permission::firmOwnerDefaults());

            Audit::log(AuditEvent::FirmCreated, "Firma başvurusu yapıldı: {$firm->name}", $firm);

            return $firm;
        });

        Notification::send(Recipients::superAdmins(), new FirmAwaitingApproval($firm));

        return $firm;
    }
}
