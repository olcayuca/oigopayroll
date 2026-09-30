<?php

namespace App\Http\Middleware;

use App\Enums\Portal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps each portal to its own audience:
 *
 * - landing: only the public pages; auth pages are forwarded to the client panel
 * - admin:   HRD staff only, no self-registration
 * - panel:   client users only
 *
 * A signed-in user of the wrong type (e.g. via passkey or a stale session) is logged out.
 */
class EnforcePortal
{
    /**
     * Route names that are part of the public landing site.
     *
     * @var list<string>
     */
    private const LANDING_ROUTES = ['home'];

    /**
     * Route names of self-registration.
     *
     * @var list<string>
     */
    private const REGISTRATION_ROUTES = ['register', 'register.store'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $portal = Portal::fromHost($request->getHost());
        app()->instance(Portal::class, $portal);

        $routeName = $request->route()?->getName();

        if ($portal === Portal::Landing) {
            if (! in_array($routeName, self::LANDING_ROUTES, true)) {
                return redirect()->away(Portal::Panel->url($request->getRequestUri()));
            }

            return $next($request);
        }

        if (! $portal->allowsRegistration() && in_array($routeName, self::REGISTRATION_ROUTES, true)) {
            abort(404);
        }

        $user = $request->user();

        if ($user !== null && ! $portal->admits($user)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors([
                'email' => 'Bu hesap '.$portal->label().' için yetkili değil.',
            ]);
        }

        return $next($request);
    }
}
