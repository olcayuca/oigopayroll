<?php

namespace App\Http\Controllers;

use App\Enums\ImportType;
use App\Imports\ExcelTemplateBuilder;
use App\Models\Company;
use App\Models\Workplace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Download the Excel template for bulk company / workplace creation.
 */
class DownloadImportTemplate extends Controller
{
    public function __invoke(Request $request, string $type, ExcelTemplateBuilder $builder): BinaryFileResponse
    {
        $importType = ImportType::fromSlug($type) ?? abort(404);
        abort_if($importType === ImportType::Firm, 404);
        $firm = $request->user()?->activeFirm() ?? abort(403);

        Gate::authorize('import', [$importType === ImportType::Company ? Company::class : Workplace::class, $firm]);

        return response()
            ->download($builder->build($importType), ExcelTemplateBuilder::filename($importType))
            ->deleteFileAfterSend();
    }
}
