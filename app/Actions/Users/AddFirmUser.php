<?php

namespace App\Actions\Users;

use App\Actions\Access\DelegatedGrantGuard;
use App\Actions\Access\GrantAccess;
use App\Enums\Permission;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Firm;
use App\Models\PermissionTemplate;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Give a person access within a firm (on the firm itself, one of its companies or workplaces):
 * an existing user by e-mail, or a new client user.
 */
class AddFirmUser
{
    public function __construct(
        private ManageUser $manageUser,
        private GrantAccess $grantAccess,
        private DelegatedGrantGuard $guard,
    ) {
        //
    }

    /**
     * @param  list<Permission|string>  $permissions
     * @param  bool  $delegated  true when a firm user (not HRD admin) does this from the panel
     * @return array{user: User, password: string|null} password is set only for a newly created user
     */
    public function handle(
        Firm|Company|Workplace $scope,
        string $email,
        ?string $name,
        array $permissions,
        ?PermissionTemplate $template,
        User $actor,
        bool $delegated = false,
    ): array {
        return DB::transaction(function () use ($scope, $email, $name, $permissions, $template, $actor, $delegated) {
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

            if ($delegated) {
                $this->guard->assertCanGrant(
                    $actor, DelegatedGrantGuard::firmOf($scope), $scope, $user, Permission::sanitize($permissions), $template,
                );
            }

            $this->grantAccess->handle($user, $scope, $permissions, $template, $actor);

            return ['user' => $user, 'password' => $password];
        });
    }
}
