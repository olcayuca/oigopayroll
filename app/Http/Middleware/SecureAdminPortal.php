<?php

namespace App\Http\Middleware;

use App\Enums\AuditEvent;
use App\Enums\Portal;
use App\Support\Audit;
use App\Support\SecuritySettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extra protection for admin.siteadi.com (settings in Admin → Güvenlik):
 *
 * - IP allowlist for the whole admin host (including the login page)
 * - sign-out after inactivity
 * - mandatory two-factor authentication before using the panel
 */
class SecureAdminPortal
{
    public const LAST_ACTIVITY = 'security.admin_last_activity';

    /**
     * Routes reachable while two-factor authentication still has to be set up.
     *
     * @var list<string>
     */
    public const TWO_FACTOR_SETUP_ROUTES = [
        'security.edit', 'logout', 'two-factor.*', 'password.confirm', 'password.confirmation', 'password.confirm.store',
        'user-password.update', 'livewire.*', 'default-livewire.*',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Portal::fromHost($request->getHost()) !== Portal::Admin) {
            return $next($request);
        }

        if (! SecuritySettings::adminIpAllowed($request->ip())) {
            Audit::log(AuditEvent::IpBlocked, 'Admin paneline izinsiz IP adresinden erişim denemesi', null, ['path' => $request->path()]);

            abort(403, 'Bu IP adresinden yönetim paneline erişim izni yok.');
        }

        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($this->idleTooLong($request)) {
            Audit::log(AuditEvent::IdleLogout, 'Hareketsizlik nedeniyle oturum kapatıldı', $user);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->with('status', 'Uzun süre işlem yapılmadığı için oturumunuz kapatıldı. Lütfen tekrar giriş yapın.');
        }

        $request->session()->put(self::LAST_ACTIVITY, now()->getTimestamp());

        // Unnamed routes are framework assets (Livewire / Flux scripts); pages are always named.
        if (SecuritySettings::adminTwoFactorRequired()
            && $user->two_factor_confirmed_at === null
            && $request->route()?->getName() !== null
            && ! $request->routeIs(...self::TWO_FACTOR_SETUP_ROUTES)) {
            return redirect()->route('security.edit')
                ->with('status', 'Yönetim paneline devam etmek için iki adımlı doğrulamayı etkinleştirmeniz gerekiyor.');
        }

        return $next($request);
    }

    private function idleTooLong(Request $request): bool
    {
        $minutes = SecuritySettings::adminIdleMinutes();
        $last = $request->session()->get(self::LAST_ACTIVITY);

        return $minutes > 0 && is_int($last) && now()->getTimestamp() - $last > $minutes * 60;
    }
}
