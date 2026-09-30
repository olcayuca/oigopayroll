<?php

namespace App\Http\Controllers;

use App\Kvkk\PersonalDataExport;
use App\Models\KvkkRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Admin: personal data copy of the applicant, to answer a KVKK application.
 */
class DownloadPersonalData extends Controller
{
    public function __invoke(Request $request, KvkkRequest $kvkkRequest, PersonalDataExport $export): JsonResponse
    {
        Gate::authorize('manage-settings');

        abort_if($kvkkRequest->user === null, 404, 'Başvuru sahibinin hesabı bulunamadı.');

        return response()
            ->json($export->for($kvkkRequest->user, $request->user()), 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Content-Disposition', "attachment; filename=\"kisisel-veri-basvuru-{$kvkkRequest->id}.json\"");
    }
}
