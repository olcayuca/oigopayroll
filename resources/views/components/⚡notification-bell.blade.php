<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * Sidebar bell (both portals): unread count and the latest notifications.
 */
new class extends Component {
    #[Computed]
    public function unread(): int
    {
        return Auth::user()?->unreadNotifications()->count() ?? 0;
    }

    /**
     * @return \Illuminate\Support\Collection<int, \Illuminate\Notifications\DatabaseNotification>
     */
    #[Computed]
    public function latest(): \Illuminate\Support\Collection
    {
        return Auth::user()?->notifications()->latest()->limit(6)->get() ?? collect();
    }
}; ?>

<div wire:poll.120s>
    <flux:dropdown position="right" align="start">
        <flux:sidebar.item icon="bell" :badge="$this->unread ?: null" class="cursor-pointer">Bildirimler</flux:sidebar.item>

        <flux:menu class="w-80">
            @forelse ($this->latest as $notification)
                <flux:menu.item :href="route('notifications.open', $notification->id)" wire:key="n-{{ $notification->id }}">
                    <div class="min-w-0 py-1">
                        <div @class(['truncate text-sm', 'font-semibold' => $notification->read_at === null])>{{ $notification->data['title'] ?? '' }}</div>
                        <div class="text-xs text-zinc-500">{{ $notification->created_at?->diffForHumans() }}</div>
                    </div>
                </flux:menu.item>
            @empty
                <div class="px-3 py-4 text-sm text-zinc-500">Bildirim yok.</div>
            @endforelse
            <flux:menu.separator />
            <flux:menu.item icon="inbox" :href="route('notifications.index')" wire:navigate>Tüm bildirimler</flux:menu.item>
        </flux:menu>
    </flux:dropdown>
</div>
