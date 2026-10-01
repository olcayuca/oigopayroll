<?php

namespace App\Support;

use App\Enums\AuditEvent;
use App\Enums\Portal;
use App\Enums\UserType;
use App\Kvkk\AnonymizeUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Destek görünümü: a super admin sees the panel as a client user or payroll specialist.
 *
 * The admin portal issues a single-use token (valid for TOKEN_SECONDS); the panel host exchanges
 * it for a panel session of the target user. Admin and panel cookies are host-only, so the
 * admin's own session is untouched. The panel session carries the impersonator, which
 * - shows a banner with an end button on every page,
 * - ends the session after MAX_MINUTES,
 * - blocks account settings (password, 2FA, profile, KVKK decisions),
 * - is written into every audit record made meanwhile.
 */
final class Impersonation
{
    public const SESSION_KEY = 'impersonation';

    public const TOKEN_SECONDS = 60;

    public const MAX_MINUTES = 30;

    /**
     * Routes the impersonator may not use: they act on the user's own account.
     *
     * @var list<string>
     */
    public const BLOCKED_ROUTES = [
        'profile.*', 'security.*', 'appearance.*', 'kvkk.consent', 'kvkk.edit', 'user-password.*', 'two-factor.*',
        'passkey*', 'password.*', 'user-profile-information.*', 'verification.*',
    ];

    /**
     * Admin side: create the hand-over token.
     */
    public static function issue(User $admin, User $target): string
    {
        self::ensureAllowed($admin, $target);

        $token = Str::random(48);
        Cache::put(self::cacheKey($token), ['by' => $admin->id, 'user' => $target->id], self::TOKEN_SECONDS);

        return $token;
    }

    /**
     * Panel side: exchange the token for a session of the target user.
     */
    public static function start(string $token, Request $request): User
    {
        $payload = Cache::pull(self::cacheKey($token));
        $adminId = is_array($payload) && is_int($payload['by'] ?? null) ? $payload['by'] : null;
        $targetId = is_array($payload) && is_int($payload['user'] ?? null) ? $payload['user'] : null;
        $admin = $adminId !== null ? User::find($adminId) : null;
        $target = $targetId !== null ? User::find($targetId) : null;

        if ($admin === null || $target === null) {
            throw ValidationException::withMessages(['token' => 'Destek bağlantısı geçersiz veya süresi dolmuş.']);
        }

        self::ensureAllowed($admin, $target);

        // Whoever was signed in on the panel in this browser is signed out first.
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        $request->session()->invalidate();
        $request->session()->put(self::SESSION_KEY, ['by' => $admin->id, 'started_at' => now()->getTimestamp()]);

        Auth::guard('web')->login($target);
        $request->session()->regenerate();

        Audit::log(AuditEvent::ImpersonationStarted, "Destek görünümü başladı: {$admin->name} → {$target->name} ({$target->email})", $target, [], $admin);

        return $target;
    }

    /**
     * End the session and return to the user's page on the admin portal.
     */
    public static function stop(Request $request, string $reason = 'ended'): string
    {
        $state = self::state($request);
        $target = $request->user();

        if ($state !== null) {
            Audit::log(AuditEvent::ImpersonationEnded, 'Destek görünümü bitti'.($reason === 'expired' ? ' (süre doldu)' : '').': '.($target->name ?? ''), $target, [
                'minutes' => (int) ceil((now()->getTimestamp() - $state['started_at']) / 60),
            ], User::find($state['by']));
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Portal::Admin->url($target ? "kullanicilar/{$target->id}" : 'kullanicilar');
    }

    /**
     * @return array{by: int, started_at: int}|null
     */
    public static function state(?Request $request = null): ?array
    {
        $request ??= request();

        if (! $request->hasSession()) {
            return null;
        }

        $state = $request->session()->get(self::SESSION_KEY);

        return is_array($state) && is_int($state['by'] ?? null) && is_int($state['started_at'] ?? null)
            ? ['by' => $state['by'], 'started_at' => $state['started_at']]
            : null;
    }

    public static function active(?Request $request = null): bool
    {
        return self::state($request) !== null;
    }

    public static function impersonator(?Request $request = null): ?User
    {
        $state = self::state($request);

        return $state ? User::find($state['by']) : null;
    }

    public static function endsAt(?Request $request = null): ?Carbon
    {
        $state = self::state($request);

        return $state ? Carbon::createFromTimestamp($state['started_at'])->addMinutes(self::MAX_MINUTES) : null;
    }

    private static function ensureAllowed(User $admin, User $target): void
    {
        $error = match (true) {
            ! $admin->isSuperAdmin() => 'Destek görünümünü yalnızca süper adminler başlatabilir.',
            $admin->is($target) => 'Kendi hesabınızla destek görünümü başlatamazsınız.',
            ! $target->is_active => 'Pasif hesapla destek görünümü başlatılamaz.',
            $target->type === UserType::SuperAdmin => 'Süper admin hesapları görüntülenemez.',
            AnonymizeUser::isAnonymized($target) => 'Anonimleştirilmiş hesap görüntülenemez.',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['user' => $error]);
        }
    }

    private static function cacheKey(string $token): string
    {
        return 'impersonation-token:'.hash('sha256', $token);
    }
}
