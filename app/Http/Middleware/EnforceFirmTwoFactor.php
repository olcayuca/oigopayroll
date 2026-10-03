<?php

namespace App\Http\Middleware;

use App\Enums\Portal;
use App\Support\FirmSettings;
use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel → Ayarlar → Güvenlik: when the active firm requires two-factor authentication, a user
 * without it is sent to set it up first. Destek görünümü (impersonation) is exempt.
 */
class EnforceFirmTwoFactor
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null
            || $user->two_factor_confirmed_at !== null
            || Portal::fromHost($request->getHost()) !== Portal::Panel
            || $request->route()?->getName() === null
            || $request->routeIs(...SecureAdminPortal::TWO_FACTOR_SETUP_ROUTES)
            || Impersonation::active($request)) {
            return $next($request);
        }

        $firm = $user->activeFirm();

        if ($firm !== null && FirmSettings::requiresTwoFactor($firm)) {
            return redirect()->route('security.edit')
                ->with('status', "{$firm->name} için iki adımlı doğrulama zorunlu. Devam etmek için etkinleştirin.");
        }

        return $next($request);
    }
}
