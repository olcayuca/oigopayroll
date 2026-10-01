<?php

namespace App\Http\Controllers;

use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Panel side of destek görünümü (see App\Support\Impersonation).
 */
class ImpersonationController extends Controller
{
    public function start(Request $request, string $token): RedirectResponse
    {
        try {
            Impersonation::start($token, $request);
        } catch (ValidationException $e) {
            abort(403, collect($e->errors())->flatten()->first());
        }

        return redirect()->route('dashboard');
    }

    public function stop(Request $request): RedirectResponse
    {
        abort_unless(Impersonation::active($request), 404);

        return redirect()->away(Impersonation::stop($request));
    }
}
