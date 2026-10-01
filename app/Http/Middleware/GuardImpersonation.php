<?php

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limits of a destek görünümü session: time limit and no access to the user's account settings.
 */
class GuardImpersonation
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Impersonation::active($request) || $request->user() === null) {
            return $next($request);
        }

        if (Impersonation::endsAt($request)?->isPast()) {
            return redirect()->away(Impersonation::stop($request, 'expired'));
        }

        if ($request->routeIs(...Impersonation::BLOCKED_ROUTES)) {
            if ($request->expectsJson()) {
                abort(403, 'Destek görünümünde hesap ayarları değiştirilemez.');
            }

            return redirect()->route('dashboard')->with('status', 'Destek görünümünde kullanıcının hesap ayarları açılamaz.');
        }

        return $next($request);
    }
}
