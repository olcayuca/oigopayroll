<?php

namespace App\Policies;

use App\Enums\FirmStatus;
use App\Enums\Permission;
use App\Enums\UserType;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\User;

/**
 * HRD Super Admins pass every check via Gate::before (AppServiceProvider).
 */
class FirmPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Firm $firm): bool
    {
        return $user->canSee($firm);
    }

    /**
     * HRD-side creation (active immediately) is reserved for super admins.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Client self-registration of a firm, which then waits for approval.
     */
    public function register(User $user): bool
    {
        return $user->is_active && $user->type === UserType::ClientUser;
    }

    public function update(User $user, Firm $firm): bool
    {
        return $user->hasPermissionOn(Permission::FirmUpdate, $firm);
    }

    /**
     * Kurulum onayı: HRD staff only — the firm's responsible specialist, or a specialist who may update the firm.
     * (Super admins pass via Gate::before.)
     */
    public function approveSetup(User $user, Firm $firm): bool
    {
        return $user->type === UserType::PayrollSpecialist && $firm->isActive()
            && ($firm->specialist_id === $user->id || $user->hasPermissionOn(Permission::FirmUpdate, $firm));
    }

    public function review(User $user, Firm $firm): bool
    {
        return false;
    }

    public function manageUsers(User $user, Firm $firm): bool
    {
        return $firm->isActive() && $user->hasPermissionOn(Permission::FirmManageUsers, $firm);
    }

    public function viewDocuments(User $user, Firm $firm): bool
    {
        return $user->hasPermissionOn(Permission::FirmView, $firm);
    }

    public function manageDocuments(User $user, Firm $firm): bool
    {
        return $firm->status !== FirmStatus::Passive && $user->hasPermissionOn(Permission::FirmUpdate, $firm);
    }

    /**
     * Tanımlar (birim, unvan, pozisyon…): everyone who sees the firm reads them; firm editors maintain them.
     */
    public function viewDefinitions(User $user, Firm $firm): bool
    {
        return $user->canSee($firm);
    }

    public function manageDefinitions(User $user, Firm $firm): bool
    {
        return $firm->isActive() && $user->hasPermissionOn(Permission::FirmUpdate, $firm);
    }

    /**
     * İşlem Geçmişi: firm.view_audit on the firm, or on some of its companies / workplaces.
     */
    public function viewAudit(User $user, Firm $firm): bool
    {
        $reach = AuditLog::reach($user, $firm);

        return $reach['firm'] || $reach['companies'] !== [] || $reach['workplaces'] !== [];
    }

    public function delete(User $user, Firm $firm): bool
    {
        return false;
    }
}
