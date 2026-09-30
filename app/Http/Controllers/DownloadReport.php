<?php

namespace App\Http\Controllers;

use App\Exports\ExportType;
use App\Exports\RunExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin: system-wide Excel reports.
 */
class DownloadReport extends Controller
{
    /** Longest period for an audit log export, in days. */
    public const MAX_AUDIT_DAYS = 366;

    public function __invoke(Request $request, string $report, RunExport $export): BinaryFileResponse
    {
        Gate::authorize('manage-settings');

        $type = ExportType::from($report);
        $query = $type->query();
        $scope = null;

        if ($type === ExportType::AuditLogs) {
            $data = $request->validate([
                'baslangic' => ['required', 'date'],
                'bitis' => ['required', 'date', 'after_or_equal:baslangic'],
            ]);

            $from = Carbon::parse($data['baslangic'])->startOfDay();
            $to = Carbon::parse($data['bitis'])->endOfDay();

            abort_if($from->diffInDays($to) > self::MAX_AUDIT_DAYS, 422, 'En fazla bir yıllık kayıt dışa aktarılabilir.');

            $query->whereBetween('created_at', [$from, $to]);
            $scope = $from->format('d.m.Y').' - '.$to->format('d.m.Y');
        }

        return $export->download($type, $query, scope: $scope);
    }
}
