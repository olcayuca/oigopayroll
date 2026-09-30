<?php

namespace App\Exports;

use App\Enums\AuditEvent;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Stream a report query into an .xlsx download and record who exported what.
 */
class RunExport
{
    public function __construct(private readonly ExcelExporter $exporter) {}

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public function download(ExportType $type, Builder $query, bool $withFirm = true, ?string $scope = null): BinaryFileResponse
    {
        $result = $this->exporter->write($type->label(), $type->columns($withFirm), $query->lazy(500));

        Audit::log(AuditEvent::DataExported, "Excel dışa aktarma: {$type->label()} ({$result['rows']} kayıt)".($scope ? " · {$scope}" : ''), null, [
            'report' => $type->value,
            'rows' => $result['rows'],
        ]);

        return response()->download($result['path'], $type->filename())->deleteFileAfterSend();
    }
}
