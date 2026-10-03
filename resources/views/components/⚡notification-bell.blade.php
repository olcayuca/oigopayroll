<?php

use App\Notifications\DeliverDueReminders;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
 * Bell with the unread count and the latest notifications.
 * "sidebar": admin sidebar item; "topbar": panel top bar button.
 */
new class extends Component {
    #[Locked]
    public string $variant = 'sidebar';

    #[Computed]
    public function unread(): int
    {
        // Personal reminders arrive with the bell poll as well (no scheduler needed).
        if ($user = Auth::user()) {
            app(DeliverDueReminders::class)->run($user);
        }

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

    public function markAllRead(): void
    {
        Auth::user()?->unreadNotifications()->update(['read_at' => now()]);

        unset($this->unread, $this->latest);
    }
}; ?>

<div wire:poll.120s @class(['relative' => $variant === 'topbar'])
    x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
    @if ($variant === 'topbar')
        <button type="button" x-on:click="open = ! open" aria-label="Bildirimler" data-test="notification-bell"
            class="relative flex size-11 items-center justify-center rounded-[11px] border-[1.5px] bg-white text-ink-2 transition"
            x-bind:class="open ? 'border-brand' : 'border-[#E8EDF3] hover:border-line-2'">
            <flux:icon.bell class="size-[18px]" />
            @if ($this->unread > 0)
                <span class="absolute -end-1 -top-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full border-2 border-white bg-st-red-dot px-1 text-[10px] font-extrabold text-white">{{ $this->unread > 9 ? '9+' : $this->unread }}</span>
            @endif
        </button>

        <div x-cloak x-show="open" x-transition.opacity.duration.150ms
            class="absolute end-0 top-[52px] z-30 w-[380px] max-w-[calc(100vw-2rem)] overflow-hidden rounded-[14px] border border-[#E5EAF1] bg-white shadow-[0_16px_40px_rgba(16,40,72,0.16)]">
            <div class="flex items-center justify-between border-b border-line-3 px-4 py-3.5">
                <span class="text-[14px] font-extrabold text-ink">Bildirimler</span>
                @if ($this->unread > 0)
                    <button type="button" wire:click="markAllRead" class="text-xs font-bold text-brand hover:text-mint">Tümünü okundu say</button>
                @endif
            </div>
            <div class="max-h-[400px] overflow-y-auto">
                @forelse ($this->latest as $notification)
                    @php($level = $notification->data['level'] ?? 'info')
                    <a href="{{ route('notifications.open', $notification->id) }}" wire:key="n-{{ $notification->id }}"
                        @class(['flex gap-3 border-b border-line-4 px-4 py-3 transition hover:bg-row-hover', 'bg-[#F8FAFE]' => $notification->read_at === null])>
                        <span @class([
                            'mt-1 size-2.5 shrink-0 rounded-[3px]',
                            'bg-st-red-dot' => in_array($level, ['danger', 'critical', 'error'], true),
                            'bg-st-amber-dot' => $level === 'warning',
                            'bg-mint' => $level === 'success',
                            'bg-st-blue-dot' => ! in_array($level, ['danger', 'critical', 'error', 'warning', 'success'], true),
                        ])></span>
                        <span class="min-w-0 flex-1">
                            <span @class(['block text-[13px] text-ink', 'font-extrabold' => $notification->read_at === null, 'font-semibold' => $notification->read_at !== null])>{{ $notification->data['title'] ?? '' }}</span>
                            @if (filled($notification->data['body'] ?? null))
                                <span class="mt-0.5 line-clamp-2 block text-xs text-muted">{{ $notification->data['body'] }}</span>
                            @endif
                            <span class="mt-1 block text-[11px] font-semibold text-faint">{{ $notification->created_at?->diffForHumans() }}</span>
                        </span>
                        @if ($notification->read_at === null)
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-st-blue-dot"></span>
                        @endif
                    </a>
                @empty
                    <div class="px-4 py-10 text-center text-[13px] text-muted">Bildirim yok.</div>
                @endforelse
            </div>
            <a href="{{ route('notifications.index') }}" wire:navigate class="block border-t border-line-3 px-4 py-3 text-center text-[13px] font-bold text-brand hover:bg-row-hover">Tüm bildirimler</a>
        </div>
    @else
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
    @endif
</div>
