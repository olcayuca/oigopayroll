<?php

namespace App\Http\Controllers;

use App\Models\Firm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A firm's logo (Panel → Ayarlar → Marka & Logo), for users working in that firm and HRD.
 */
class ShowFirmLogo extends Controller
{
    public function __invoke(Request $request, Firm $firm): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->isSuperAdmin() || $user->mayWorkInFirm($firm->id)), 403);

        $disk = Storage::disk('local');
        abort_unless($firm->logo_path !== null && $disk->exists($firm->logo_path), 404);

        return $disk->response($firm->logo_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }
}
