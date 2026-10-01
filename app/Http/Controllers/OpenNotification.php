<?php

namespace App\Http\Controllers;

use App\Enums\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Mark a notification read and go to its target. Only URLs on our own portals are followed.
 */
class OpenNotification extends Controller
{
    public function __invoke(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()?->notifications()->whereKey($notification)->firstOrFail();
        abort_if($record === null, 404);

        $record->markAsRead();

        $url = is_string($record->data['url'] ?? null) ? $record->data['url'] : '';
        $host = parse_url($url, PHP_URL_HOST);
        $ownHosts = array_map(fn (Portal $portal) => $portal->domain(), Portal::cases());

        return in_array($host, $ownHosts, true)
            ? redirect()->away($url)
            : redirect()->route('notifications.index');
    }
}
