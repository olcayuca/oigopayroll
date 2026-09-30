<?php

namespace App\Actions\Users;

use App\Actions\Access\GrantAccess;
use App\Enums\Permission;
use App\Enums\UserType;
use App\Models\Firm;
use App\Models\PermissionTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Give a person access to a firm: an existing user by e-mail, or a new client user.
 */
class AddFirmUser
{
    public function __construct(private ManageUser $manageUser, private GrantAccess $grantAccess)
    {
        //
    }

    /**
     * @param  list<Permission|string>  $permissions
     * @return array{user: User, password: string|null} password is set only for a newly created user
     */
    public function handle(
        Firm $firm,
        string $email,
        ?string $name,
        array $permissions,
        ?PermissionTemplate $template,
        User $actor,
    ): array {
        return DB::transaction(function () use ($firm, $email, $name, $permissions, $template, $actor) {
            $user = User::where('email', mb_strtolower(trim($email)))->first();
            $password = null;

            if ($user === null) {
                if (blank($name)) {
                    throw ValidationException::withMessages(['name' => 'Yeni kullanıcı için ad soyad zorunludur.']);
                }

                ['user' => $user, 'password' => $password] = $this->manageUser->create([
                    'name' => $name, 'email' => $email, 'type' => UserType::ClientUser->value,
                ]);
            } elseif ($user->isSuperAdmin()) {
                throw ValidationException::withMessages(['email' => 'Süper adminler tüm firmalara zaten erişebilir.']);
            }

            $this->grantAccess->handle($user, $firm, $permissions, $template, $actor);

            return ['user' => $user, 'password' => $password];
        });
    }
}
