<?php

namespace App\Actions\Users;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin-side user management. New accounts get a one-time temporary password that the
 * admin passes on (e-mail delivery is not configured yet); users change it after login.
 */
class ManageUser
{
    /**
     * @param  array<string, mixed>  $input  name, email, type
     * @return array{user: User, password: string}
     */
    public function create(array $input): array
    {
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'type' => ['required', Rule::enum(UserType::class)],
        ], [], self::attributes())->validate();

        $password = self::temporaryPassword();

        $user = new User(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'password' => $password]);
        $user->forceFill(['type' => $data['type'], 'is_active' => true, 'email_verified_at' => now()])->save();

        return ['user' => $user, 'password' => $password];
    }

    /**
     * @param  array<string, mixed>  $input  name, email, type
     */
    public function update(User $user, array $input, User $actor): User
    {
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'type' => ['required', Rule::enum(UserType::class)],
        ], [], self::attributes())->validate();

        if ($user->is($actor) && $data['type'] !== UserType::SuperAdmin->value) {
            throw ValidationException::withMessages(['type' => 'Kendi süper admin yetkinizi kaldıramazsınız.']);
        }

        $user->fill(['name' => $data['name'], 'email' => mb_strtolower($data['email'])]);
        $user->forceFill(['type' => $data['type']])->save();

        return $user;
    }

    public function setActive(User $user, bool $active, User $actor): User
    {
        if ($user->is($actor) && ! $active) {
            throw ValidationException::withMessages(['user' => 'Kendi hesabınızı pasife alamazsınız.']);
        }

        $user->forceFill(['is_active' => $active])->save();

        return $user;
    }

    /**
     * Replace the password with a new temporary one and return it.
     */
    public function resetPassword(User $user): string
    {
        $password = self::temporaryPassword();

        $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

        return $password;
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return ['name' => 'Ad Soyad', 'email' => 'E-posta', 'type' => 'Kullanıcı Tipi'];
    }

    private static function temporaryPassword(): string
    {
        return Str::password(12, symbols: false);
    }
}
