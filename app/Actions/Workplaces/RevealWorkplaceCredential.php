<?php

namespace App\Actions\Workplaces;

use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Models\CredentialAccessLog;
use App\Models\User;
use App\Models\Workplace;
use App\Support\Audit;
use App\Validation\WorkplaceRules;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * The only sanctioned way to read a stored SGK / İŞKUR / TÜİK / e-Beyanname credential.
 */
class RevealWorkplaceCredential
{
    public function handle(Workplace $workplace, string $field, User $user, ?string $ipAddress = null): ?string
    {
        if (! in_array($field, Workplace::SECRET_FIELDS, true)) {
            throw new InvalidArgumentException("[{$field}] is not a workplace credential field.");
        }

        if (! $user->hasPermissionOn(Permission::WorkplaceViewCredentials, $workplace)) {
            throw new AuthorizationException('Bu işyerinin şifrelerini görüntüleme yetkiniz yok.');
        }

        CredentialAccessLog::create([
            'user_id' => $user->id,
            'workplace_id' => $workplace->id,
            'field' => $field,
            'ip_address' => $ipAddress,
        ]);

        Audit::log(AuditEvent::CredentialRevealed, (WorkplaceRules::attributes()[$field] ?? $field)." görüntülendi: {$workplace->branch_name}", $workplace, ['field' => $field], $user);

        $value = $workplace->getAttribute($field);

        return is_string($value) ? $value : null;
    }
}
