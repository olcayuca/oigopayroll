<?php

namespace App\Http\Middleware;

use App\Kvkk\Policies;
use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every signed-in user decides on the current KVKK texts before using either portal.
 * A new version of a text asks everyone again.
 */
class RequirePolicyConsent
{
    /**
     * Routes reachable while a decision is pending.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = ['kvkk.consent', 'kvkk.document', 'logout'];

    public function __construct(private readonly Policies $policies) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Unnamed routes are framework assets (Livewire / Flux scripts); pages are always named.
        if ($user === null
            || Impersonation::active($request)
            || $request->route()?->getName() === null
            || $request->routeIs(...self::ALLOWED_ROUTES, ...SecureAdminPortal::TWO_FACTOR_SETUP_ROUTES)) {
            return $next($request);
        }

        if ($this->policies->pendingFor($user)->isNotEmpty()) {
            if ($request->expectsJson()) {
                abort(403, 'KVKK metinlerinin onaylanması gerekiyor.');
            }

            return redirect()->guest(route('kvkk.consent'));
        }

        return $next($request);
    }
}
