<?php

namespace App\Actions\Firms;

use App\Actions\Access\DelegatedGrantGuard;
use App\Enums\Permission;
use App\Models\Firm;
use App\Models\FirmLink;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Let one firm manage another. Set up by HRD, or by an authorized user of the managed firm
 * ($delegated), who may only pass on permissions they hold on their own firm.
 */
class ManageFirmLink
{
    public function __construct(private DelegatedGrantGuard $guard)
    {
        //
    }

    /**
     * @param  list<Permission|string>  $permissions
     */
    public function link(Firm $manager, Firm $managed, array $permissions, User $actor, bool $delegated = false): FirmLink
    {
        $permissions = Permission::sanitize($permissions);

        if ($manager->is($managed)) {
            throw ValidationException::withMessages(['manager' => 'Bir firma kendisini yönetici olarak ekleyemez.']);
        }

        if ($permissions === []) {
            throw ValidationException::withMessages(['permissions' => 'En az bir yetki seçilmelidir.']);
        }

        if ($delegated) {
            $this->ensureDelegateMayManage($actor, $managed);

            $excess = array_diff($permissions, $this->guard->grantable($actor, $managed));

            if ($excess !== []) {
                $labels = implode(', ', array_map(fn ($p) => Permission::from($p)->label(), $excess));

                throw ValidationException::withMessages(['permissions' => "Sahip olmadığınız yetkiler verilemez: {$labels}."]);
            }
        }

        return FirmLink::updateOrCreate(
            ['manager_firm_id' => $manager->id, 'managed_firm_id' => $managed->id],
            ['permissions' => $permissions, 'granted_by' => $actor->id],
        );
    }

    public function unlink(FirmLink $link, User $actor, bool $delegated = false): void
    {
        if ($delegated) {
            $this->ensureDelegateMayManage($actor, $link->managed);
        }

        $link->delete();
    }

    private function ensureDelegateMayManage(User $actor, Firm $managed): void
    {
        if (! $actor->can('manageUsers', $managed)) {
            throw ValidationException::withMessages(['manager' => 'Bu firmanın erişimlerini yönetme yetkiniz yok.']);
        }

        // Access that arrives through another firm may not be passed on again.
        if (! $actor->isSuperAdmin() && ! $actor->accessGrants()->where('scope_type', 'firm')->where('scope_id', $managed->id)->exists()) {
            throw ValidationException::withMessages(['manager' => 'Firma erişimlerini yalnızca firmanın kendi yetkilileri yönetebilir.']);
        }
    }
}
