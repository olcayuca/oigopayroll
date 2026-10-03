<?php

namespace App\Http\Controllers;

use App\Models\SupportMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Both portals: a support message's attachment, for whoever may see the ticket.
 */
class DownloadSupportAttachment extends Controller
{
    public function __invoke(SupportMessage $message): StreamedResponse
    {
        Gate::authorize('view', $message->ticket);

        $disk = Storage::disk(SupportMessage::DISK);
        abort_unless($message->attachment_path !== null && $disk->exists($message->attachment_path), 404, 'Dosya bulunamadı.');

        return $disk->download($message->attachment_path, $message->attachment_name, array_filter(['Content-Type' => $message->attachment_mime]));
    }
}
