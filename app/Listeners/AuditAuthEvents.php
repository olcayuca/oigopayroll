<?php

namespace App\Listeners;

use App\Enums\AuditEvent;
use App\Models\User;
use App\Support\Audit;
use App\Support\Impersonation;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Records sign-ins, sign-outs, failed attempts and lockouts (discovered automatically).
 */
class AuditAuthEvents
{
    /**
     * Why the last password check failed; set by Fortify::authenticateUsing right before
     * Fortify fires the Failed event, and consumed by handleFailed().
     */
    public static ?string $pendingFailureReason = null;

    /**
     * @var array<string, string>
     */
    public const REASONS = [
        'unknown_user' => 'Kayıtlı olmayan e-posta',
        'wrong_password' => 'Hatalı şifre',
        'inactive' => 'Pasif hesap',
        'wrong_portal' => 'Bu panele giriş yetkisi yok',
        'two_factor' => 'Hatalı iki adımlı doğrulama kodu',
    ];

    public function handleLogin(Login $event): void
    {
        if (Impersonation::active()) {
            return;
        }

        $user = $event->user instanceof User ? $event->user : null;

        Audit::log(AuditEvent::Login, 'Giriş yapıldı', $user, ['remember' => $event->remember], $user);
    }

    public function handleLogout(Logout $event): void
    {
        if (Impersonation::active()) {
            return;
        }

        $user = $event->user instanceof User ? $event->user : null;

        Audit::log(AuditEvent::Logout, 'Çıkış yapıldı', $user, [], $user);
    }

    public function handleFailed(Failed $event): void
    {
        $email = is_string($event->credentials['email'] ?? null) ? mb_strtolower($event->credentials['email']) : null;
        $reason = self::$pendingFailureReason ?? ($event->user ? 'wrong_password' : 'unknown_user');
        self::$pendingFailureReason = null;
        $user = $event->user instanceof User ? $event->user : null;

        Audit::log(
            AuditEvent::LoginFailed,
            'Başarısız giriş: '.($email ?? '(e-posta yok)'),
            $user,
            ['email' => $email, 'reason' => $reason],
            $user,
        );
    }

    public function handleLockout(Lockout $event): void
    {
        $email = $event->request->input('email');

        Audit::log(AuditEvent::Lockout, 'Çok fazla deneme: '.(is_string($email) ? $email : ''), null, [
            'email' => is_string($email) ? mb_strtolower($email) : null,
        ]);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;

        Audit::log(AuditEvent::PasswordReset, 'Şifre sıfırlama bağlantısıyla şifre değiştirildi', $user, [], $user);
    }
}
