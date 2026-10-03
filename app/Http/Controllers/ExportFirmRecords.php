<?php

namespace App\Http\Controllers;

use App\Exports\ExportType;
use App\Exports\RunExport;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Workplace;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Panel: the active firm's companies, workplaces or personnel the user can see, as Excel.
 * Personnel follow the list filters (şirket, durum) and the setup-file columns.
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
            ExportType::Employees => $exportType->prepare(Employee::query()->viewableBy($user, $firm)
                ->when($request->filled('sirket'), fn ($query) => $query->where('company_id', $request->integer('sirket')))
                ->when($request->query('durum') === 'aktif', fn ($query) => $query->where('status', Employee::ACTIVE))
                ->when($request->query('durum') === 'pasif', fn ($query) => $query->where('status', Employee::PASSIVE))
                ->when($request->query('durum') === 'ayrildi', fn ($query) => $query->where('status', Employee::LEFT))
                ->when($request->query('durum') === 'eksik', fn ($query) => $query->incomplete())),
            default => abort(404),
        };

        return $export->download($exportType, $query, withFirm: false, scope: $firm->name, scopeModel: $firm);
    }
}
