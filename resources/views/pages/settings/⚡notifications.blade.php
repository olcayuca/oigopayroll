<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Bildirimler')] class extends Component {
    use WithPagination;

    #[Url(as: 'sekme')]
    public string $tab = 'okunmamis';

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    #[Computed]
    public function notifications(): LengthAwarePaginator
    {
        $query = $this->tab === 'okunmamis' ? Auth::user()->unreadNotifications() : Auth::user()->notifications();

        return $query->latest()->paginate(25);
    }

    #[Computed]
    public function unread(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        unset($this->notifications, $this->unread);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Bildirimler</flux:heading>
            <flux:text class="mt-1">Firma onayları, belge ve sözleşme hatırlatmaları, KVKK yanıtları.</flux:text>
        </div>
        @if ($this->unread > 0)
            <flux:button icon="check" wire:click="markAllRead">Tümünü okundu say</flux:button>
        @endif
    </div>

    <x-tabs :active="$tab" :tabs="['okunmamis' => 'Okunmamış', 'tumu' => 'Tümü']" :counts="['okunmamis' => $this->unread]" />

    <div class="divide-y divide-zinc-100 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
        @forelse ($this->notifications as $notification)
            @php($level = $notification->data['level'] ?? 'info')
            <a href="{{ route('notifications.open', $notification->id) }}" wire:key="n-{{ $notification->id }}"
                class="flex items-start gap-3 p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                <span @class([
                    'mt-1.5 size-2 shrink-0 rounded-full',
                    'bg-sky-500' => $level === 'info',
                    'bg-green-500' => $level === 'success',
                    'bg-amber-500' => $level === 'warning',
                    'bg-red-500' => $level === 'danger',
                    'opacity-30' => $notification->read_at !== null,
                ])></span>
                <span class="min-w-0 flex-1">
                    <span @class(['block text-sm', 'font-semibold' => $notification->read_at === null])>{{ $notification->data['title'] ?? '' }}</span>
                    <span class="mt-0.5 block text-sm text-zinc-500">{{ $notification->data['body'] ?? '' }}</span>
                </span>
                <span class="shrink-0 text-xs text-zinc-500">{{ $notification->created_at?->format('d.m.Y H:i') }}</span>
            </a>
        @empty
            <div class="p-10 text-center text-sm text-zinc-500">{{ $tab === 'okunmamis' ? 'Okunmamış bildirim yok.' : 'Bildirim yok.' }}</div>
        @endforelse
    </div>

    {{ $this->notifications->links() }}
</div>
