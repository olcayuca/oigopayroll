<?php

namespace App\Actions\Firms;

use App\Actions\Access\GrantAccess;
use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Enums\ScopeType;
use App\Enums\UserType;
use App\Models\AccessGrant;
use App\Models\Firm;
use App\Models\PermissionTemplate;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sorumlu bordro uzmanı: the HRD specialist who runs a firm's payroll.
 *
 * Assigning makes sure the specialist can work on the firm (a firm-level grant with the
 * "Bordro Uzmanı" template, unless they already have one). The previous specialist loses
 * their firm-level grant; keep them as a backup by granting access again from Kullanıcılar.
 */
class AssignSpecialist
{
    public const TEMPLATE = 'Bordro Uzmanı';

    public function __construct(private readonly GrantAccess $grantAccess) {}

    public function handle(Firm $firm, ?User $specialist, ?User $actor = null): Firm
    {
        if ($specialist !== null && ($specialist->type !== UserType::PayrollSpecialist || ! $specialist->is_active)) {
            throw ValidationException::withMessages(['specialist' => 'Sorumlu olarak yalnızca aktif bir bordro uzmanı seçilebilir.']);
        }

        $previous = $firm->specialist;

        if ($previous?->is($specialist) || ($previous === null && $specialist === null)) {
            return $firm;
        }

        DB::transaction(function () use ($firm, $specialist, $previous, $actor) {
            if ($previous !== null) {
                $this->grantAccess->revoke($previous, $firm);
            }

            if ($specialist !== null && ! $this->hasFirmGrant($specialist, $firm)) {
                $template = PermissionTemplate::query()->where('name', self::TEMPLATE)->first();

                $this->grantAccess->handle($specialist, $firm, $template ? [] : self::defaultPermissions(), $template, $actor);
            }

            $firm->forceFill([
                'specialist_id' => $specialist?->id,
                'specialist_assigned_at' => $specialist ? now() : null,
            ])->save();
        });

        Audit::log(
            AuditEvent::SpecialistAssigned,
            "{$firm->name}: sorumlu uzman ".($previous->name ?? '—').' → '.($specialist->name ?? '—'),
            $firm,
            ['from' => $previous?->id, 'to' => $specialist?->id],
            $actor,
        );

        return $firm->setRelation('specialist', $specialist);
    }

    /**
     * Move every firm of one specialist to another (leave, resignation, rebalancing).
     *
     * @return int number of firms moved
     */
    public function transfer(User $from, User $to, ?User $actor = null): int
    {
        if ($from->is($to)) {
            throw ValidationException::withMessages(['to' => 'Devredilecek uzman farklı olmalıdır.']);
        }

        $firms = Firm::query()->where('specialist_id', $from->id)->with('specialist')->get();

        DB::transaction(function () use ($firms, $to, $actor) {
            foreach ($firms as $firm) {
                $this->handle($firm, $to, $actor);
            }
        });

        return $firms->count();
    }

    /**
     * Permissions used when the "Bordro Uzmanı" template has been deleted.
     *
     * @return list<Permission>
     */
    public static function defaultPermissions(): array
    {
        return array_values(array_filter(Permission::cases(), fn (Permission $permission) => ! in_array($permission, [
            Permission::FirmManageUsers, Permission::CompanyDelete, Permission::WorkplaceDelete,
        ], true)));
    }

    private function hasFirmGrant(User $user, Firm $firm): bool
    {
        return AccessGrant::query()
            ->where('user_id', $user->id)
            ->where('scope_type', ScopeType::Firm)
            ->where('scope_id', $firm->id)
            ->exists();
    }
}
