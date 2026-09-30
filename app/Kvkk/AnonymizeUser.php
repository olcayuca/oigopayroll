<?php

namespace App\Kvkk;

use App\Enums\AuditEvent;
use App\Enums\UserType;
use App\Models\AccessGrant;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Erase a user's identity for a "silme" application (KVKK md. 7 / 11).
 *
 * The row stays (foreign keys, audit trail), but name and e-mail are replaced, the account is
 * closed, and credentials, sessions and access grants are removed. Consent and application
 * records are kept as evidence of compliance; security logs follow their legal retention period.
 */
class AnonymizeUser
{
    public function handle(User $user, User $actor): User
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['user' => 'Kendi hesabınızı anonimleştiremezsiniz.']);
        }

        if ($user->type === UserType::SuperAdmin) {
            throw ValidationException::withMessages(['user' => 'Süper admin hesabı anonimleştirilemez; önce kullanıcı tipini değiştirin.']);
        }

        if (self::isAnonymized($user)) {
            throw ValidationException::withMessages(['user' => 'Bu hesap zaten anonimleştirilmiş.']);
        }

        DB::transaction(function () use ($user) {
            AccessGrant::query()->where('user_id', $user->id)->delete();
            DB::table('passkeys')->where('user_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            $user->forceFill([
                'name' => 'Anonim Kullanıcı #'.$user->id,
                'email' => "anonim-{$user->id}@anonim.invalid",
                'password' => Str::random(64),
                'is_active' => false,
                'current_firm_id' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => null,
            ])->save();
        });

        Audit::log(AuditEvent::UserAnonymized, "Kullanıcı anonimleştirildi: #{$user->id}", $user, [], $actor);

        return $user;
    }

    public static function isAnonymized(User $user): bool
    {
        return str_ends_with($user->email, '@anonim.invalid');
    }
}
