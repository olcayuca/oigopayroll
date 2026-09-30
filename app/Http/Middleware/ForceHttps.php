<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect plain-http requests to https when the app is configured for https (APP_URL).
 *
 * Mixing http and https on the same host breaks sessions: browsers refuse to let an
 * http response overwrite the Secure session cookie, which surfaces as 419 errors.
 */
class ForceHttps
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->secure() && str_starts_with((string) config('app.url'), 'https://')) {
            return redirect()->to('https://'.$request->getHttpHost().$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
