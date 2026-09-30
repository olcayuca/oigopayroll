<?php

namespace App\Http\Controllers;

use App\Enums\ImportType;
use App\Imports\ExcelTemplateBuilder;
use App\Models\Firm;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin: Excel template for bulk firm creation.
 */
class DownloadFirmTemplate extends Controller
{
    public function __invoke(ExcelTemplateBuilder $builder): BinaryFileResponse
    {
        Gate::authorize('create', Firm::class);

        return response()
            ->download($builder->build(ImportType::Firm), ImportType::Firm->templateFilename())
            ->deleteFileAfterSend();
    }
}
