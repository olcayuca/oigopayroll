<?php

namespace App\Http\Controllers;

use App\Exports\ExportType;
use App\Exports\RunExport;
use App\Models\Company;
use App\Models\Workplace;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Panel: the active firm's companies or workplaces the user can see, as Excel.
 */
class ExportFirmRecords extends Controller
{
    public function __invoke(Request $request, string $type, RunExport $export): BinaryFileResponse
    {
        $user = $request->user();
        $firm = $user->activeFirm();

        abort_if($firm === null, 403, 'Yetkili olduğunuz bir firma bulunmuyor.');

        $exportType = ExportType::from($type);
        $companies = Company::query()->visibleTo($user)->where('firm_id', $firm->id)->select('id');

        $query = match ($exportType) {
            ExportType::Companies => $exportType->prepare(Company::query()->visibleTo($user)->where('firm_id', $firm->id)),
            ExportType::Workplaces => $exportType->prepare(Workplace::query()->visibleTo($user)->whereIn('company_id', $companies)
                ->when($request->filled('sirket'), fn ($query) => $query->where('company_id', $request->integer('sirket')))),
            default => abort(404),
        };

        return $export->download($exportType, $query, withFirm: false, scope: $firm->name);
    }
}
