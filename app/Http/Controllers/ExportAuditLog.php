<?php

namespace App\Http\Controllers;

use App\Enums\AuditEvent;
use App\Exports\ExcelExporter;
use App\Exports\ExportColumn;
use App\Models\AuditLog;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * İşlem Geçmişi as Excel, with the page's filters. The export itself is audited.
 */
class ExportAuditLog extends Controller
{
    public function __invoke(Request $request, ExcelExporter $exporter): BinaryFileResponse
    {
        $user = $request->user() ?? abort(403);
        $firm = $user->activeFirm() ?? abort(403);
        Gate::authorize('viewAudit', $firm);

        $filters = [
            'q' => (string) $request->query('q', ''), 'user' => (string) $request->query('kullanici', ''),
            'company' => (string) $request->query('sirket', ''), 'workplace' => (string) $request->query('sube', ''),
            'module' => (string) $request->query('modul', ''), 'from' => (string) $request->query('baslangic', ''),
            'to' => (string) $request->query('bitis', ''), 'record' => (string) $request->query('kayit', ''),
        ];

        $query = AuditLog::query()->visibleInFirm($user, $firm)->filter($filters)
            ->with(['user:id,name,email', 'company:id,short_name', 'workplace:id,branch_name'])
            ->latest('created_at')->latest('id')->limit(50000);

        $changes = fn (AuditLog $log) => collect($log->changes())->map(fn ($change) => "{$change['label']}: {$change['old']} → {$change['new']}")->implode("\n");

        $result = $exporter->write('İşlem Geçmişi', [
            new ExportColumn('Tarih', fn (AuditLog $log) => $log->created_at, ExportColumn::DATETIME),
            new ExportColumn('Kullanıcı', fn (AuditLog $log) => $log->user->name ?? 'Sistem'),
            new ExportColumn('E-posta', fn (AuditLog $log) => $log->user?->email),
            new ExportColumn('Modül', fn (AuditLog $log) => AuditEvent::groups()[$log->event->group()] ?? $log->event->group()),
            new ExportColumn('İşlem', fn (AuditLog $log) => $log->event->label()),
            new ExportColumn('Açıklama', fn (AuditLog $log) => $log->description),
            new ExportColumn('Şirket', fn (AuditLog $log) => $log->company?->short_name),
            new ExportColumn('Şube', fn (AuditLog $log) => $log->workplace?->branch_name),
            new ExportColumn('Değişiklikler', $changes),
            new ExportColumn('Kaynak', fn (AuditLog $log) => ($log->properties['source'] ?? null) === 'excel'
                ? 'Excel: '.($log->properties['import_file'] ?? '') : (isset($log->properties['impersonated_by']) ? 'Destek görünümü' : $log->portal)),
            new ExportColumn('IP Adresi', fn (AuditLog $log) => $log->ip_address),
        ], $query->lazy(1000));

        Audit::log(AuditEvent::DataExported, "Excel dışa aktarma: İşlem Geçmişi ({$result['rows']} kayıt)", null, [
            'report' => 'audit_log', 'rows' => $result['rows'], 'filters' => array_filter($filters),
        ], scope: $firm);

        return response()->download($result['path'], 'Islem_Gecmisi_'.now()->format('Ymd_Hi').'.xlsx')->deleteFileAfterSend();
    }
}
