<?php

namespace App\Notifications;

use App\Enums\Permission;
use App\Enums\ScopeType;
use App\Enums\UserType;
use App\Models\AccessGrant;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who hears about what.
 */
final class Recipients
{
    /**
     * Active users with firm-wide access to the firm, plus its responsible specialist.
     *
     * @return Collection<int, User>
     */
    public static function firm(Firm $firm): Collection
    {
        $ids = AccessGrant::query()
            ->where('scope_type', ScopeType::Firm)
            ->where('scope_id', $firm->id)
            ->pluck('user_id')
            ->push($firm->specialist_id)
            ->filter()
            ->unique();

        return User::query()->whereIn('id', $ids)->where('is_active', true)->get()
            ->filter(fn (User $user) => $user->mayWorkInFirm($firm->id))
            ->values();
    }

    /**
     * Abonelik: client users who may update the firm (owners / managers).
     *
     * @return Collection<int, User>
     */
    public static function billing(Firm $firm): Collection
    {
        return self::firm($firm)
            ->filter(fn (User $user) => $user->type === UserType::ClientUser && $user->hasPermissionOn(Permission::FirmUpdate, $firm))
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public static function superAdmins(): Collection
    {
        return User::query()->where('type', UserType::SuperAdmin)->where('is_active', true)->get();
    }
}
