<?php

namespace App\Http\Controllers;

use App\Enums\AuditEvent;
use App\Models\FirmDocument;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download a firm document (both portals). Access: FirmPolicy::viewDocuments.
 */
class DownloadFirmDocument extends Controller
{
    public function __invoke(Request $request, FirmDocument $document): StreamedResponse
    {
        Gate::authorize('viewDocuments', $document->firm);

        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), 404, 'Dosya bulunamadı.');

        Audit::log(AuditEvent::DocumentDownloaded, "{$document->firm->name}: belge indirildi ({$document->title})", $document->firm, ['document_id' => $document->id], scope: $document);

        $extension = pathinfo($document->path, PATHINFO_EXTENSION);
        $name = str($document->title)->slug()->append('.'.$extension)->toString();

        return $disk->download($document->path, $name, ['Content-Type' => $document->mime_type]);
    }
}
