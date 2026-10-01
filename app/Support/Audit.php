<?php

namespace App\Support;

use App\Enums\AuditEvent;
use App\Enums\Portal;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Write audit records. Never throws: auditing must not break the action being audited.
 */
final class Audit
{
    /**
     * @param  array<string, mixed>  $properties  never put secrets here
     */
    public static function log(
        AuditEvent $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?User $user = null,
    ): void {
        try {
            $request = request();
            $actor = $user ?? Auth::user();
            $impersonation = Impersonation::state($request);

            if ($impersonation !== null) {
                $properties['impersonated_by'] = $impersonation['by'];
            }

            AuditLog::create([
                'user_id' => $actor instanceof User ? $actor->getKey() : null,
                'event' => $event,
                'description' => mb_substr($description, 0, 255),
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'properties' => $properties ?: null,
                'portal' => app()->runningInConsole() && ! app()->runningUnitTests() ? null : Portal::fromHost($request->getHost())->value,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 512) ?: null,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
