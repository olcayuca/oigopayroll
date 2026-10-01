<?php

namespace App\Actions\Firms;

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
use Illuminate\Validation\ValidationException;

/**
 * A firm opens a sub-firm it manages. Like any client-created firm it waits for HRD approval.
 *
 * The parent gets a full management link, and the creator is assigned to the sub-firm with
 * the permissions they hold on the parent.
 */
class CreateSubFirm
{
    public function __construct(private ManageFirmLink $manageFirmLink)
    {
        //
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Firm $parent, array $input, User $actor): Firm
    {
        if (! $parent->isActive()) {
            throw ValidationException::withMessages(['firm' => 'Alt firma yalnızca aktif bir firma altında açılabilir.']);
        }

        if (! $actor->hasPermissionOn(Permission::FirmCreateSubfirm, $parent)
            || (! $actor->isSuperAdmin() && $actor->firm_id !== $parent->id)) {
            throw ValidationException::withMessages(['firm' => 'Bu firma adına alt firma açma yetkiniz yok.']);
        }

        $data = Validator::make(FirmRules::clean($input), FirmRules::rules(), [], FirmRules::attributes())->validate();

        $firm = DB::transaction(function () use ($parent, $data, $actor) {
            $firm = Firm::create([
                ...$data,
                'parent_firm_id' => $parent->id,
                'status' => FirmStatus::Pending,
                'source' => FirmSource::Client,
                'created_by' => $actor->id,
            ]);

            $link = $this->manageFirmLink->link($parent, $firm, Permission::cases(), $actor);

            // The creator follows the sub-firm with what they are allowed on the parent.
            $permissions = $actor->isSuperAdmin()
                ? $link->permissions
                : array_values(array_filter($link->permissions, fn ($p) => $actor->hasPermissionOn(Permission::from($p), $parent)));

            if (! $actor->isSuperAdmin() && $permissions !== []) {
                $actor->flushAccessCache();
                $this->manageFirmLink->assign($link, $actor, $firm, $permissions, null, $actor);
            }

            Audit::log(AuditEvent::FirmCreated, "Alt firma oluşturuldu: {$firm->name} (üst: {$parent->name})", $firm, ['parent_firm_id' => $parent->id]);

            return $firm;
        });

        Notification::send(Recipients::superAdmins(), new FirmAwaitingApproval($firm));

        return $firm;
    }
}
