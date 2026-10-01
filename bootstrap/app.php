<?php

use App\Http\Middleware\EnforcePortal;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\GuardImpersonation;
use App\Http\Middleware\RequirePolicyConsent;
use App\Http\Middleware\SecureAdminPortal;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            ForceHttps::class,
            SecurityHeaders::class,
        ], append: [
            EnforcePortal::class,
            SecureAdminPortal::class,
            GuardImpersonation::class,
            RequirePolicyConsent::class,
        ]);

        // Stay on the portal the guest was trying to reach.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->getSchemeAndHttpHost().'/login');
        $middleware->redirectUsersTo(fn (Request $request) => $request->getSchemeAndHttpHost().'/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
