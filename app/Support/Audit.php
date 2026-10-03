<?php

namespace App\Support;

use App\Enums\AuditEvent;
use App\Enums\Portal;
use App\Models\AuditLog;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Write audit records. Never throws: auditing must not break the action being audited.
 *
 * Every record is placed in a firm / company (şirket) / workplace (şube) when the subject — or the
 * explicit $scope — belongs to one (AuditScope), so the panel's İşlem Geçmişi can be filtered by them.
 */
final class Audit
{
    /**
     * Properties added to every record written inside within() (e.g. the Excel import a row came from).
     *
     * @var array<string, mixed>
     */
    private static array $context = [];

    /**
     * @param  array<string, mixed>  $properties  never put secrets here; field changes go under "changes" (AuditChanges)
     * @param  Model|null  $scope  record that decides firm / company / workplace when the subject does not (e.g. a grant's scope)
     */
    public static function log(
        AuditEvent $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?User $user = null,
        ?Model $scope = null,
    ): void {
        try {
            $request = request();
            $actor = $user ?? Auth::user();
            $impersonation = Impersonation::state($request);

            $properties = [...self::$context, ...$properties];

            if ($impersonation !== null) {
                $properties['impersonated_by'] = $impersonation['by'];
            }

            $placement = AuditScope::of($scope ?? $subject);

            AuditLog::create([
                'user_id' => $actor instanceof User ? $actor->getKey() : null,
                ...$placement,
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

    /**
     * Run $callback with extra properties on every record it writes.
     *
     * @template TReturn
     *
     * @param  array<string, mixed>  $context
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function within(array $context, Closure $callback): mixed
    {
        $previous = self::$context;
        self::$context = [...$previous, ...$context];

        try {
            return $callback();
        } finally {
            self::$context = $previous;
        }
    }
}
