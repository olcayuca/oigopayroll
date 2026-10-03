<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\SupportTicket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Admin → Destek Talepleri: every firm's tickets. "Yanıt bekleyen" = status Açık (new or answered by the customer).
 */
new #[Title('Destek Talepleri')] class extends Component {
    use WithPagination;

    #[Url(as: 'sekme', except: 'bekleyen')]
    public string $tab = 'bekleyen';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'oncelik', except: '')]
    public string $priority = '';

    public bool $mine = false;

    public function mount(): void
    {
        $this->authorize('manage-settings');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, string>
     */
    public function tabs(): array
    {
        return ['bekleyen' => 'Yanıt bekleyen', 'acik' => 'Tüm açık', 'gecmis' => 'Geçmiş'];
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return [
            'bekleyen' => SupportTicket::where('status', TicketStatus::Open)->count(),
            'acik' => SupportTicket::query()->active()->count(),
            'gecmis' => SupportTicket::query()->active(false)->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, SupportTicket>
     */
    #[Computed]
    public function tickets(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return SupportTicket::query()
            ->with(['firm:id,name', 'user:id,name', 'assignee:id,name'])
            ->when($this->tab === 'bekleyen', fn ($query) => $query->where('status', TicketStatus::Open))
            ->when($this->tab === 'acik', fn ($query) => $query->active())
            ->when($this->tab === 'gecmis', fn ($query) => $query->active(false))
            ->when($this->priority !== '', fn ($query) => $query->where('priority', $this->priority))
            ->when($this->mine, fn ($query) => $query->where('assigned_to', Auth::id()))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('subject', 'like', '%'.$search.'%')
                ->orWhereHas('firm', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                ->when(preg_match('/^#?(\d+)$/', $search, $match) === 1, fn ($query) => $query->orWhere('id', (int) $match[1] - 1000))))
            // Critical first, then the longest waiting.
            ->orderByRaw("case priority when 'critical' then 0 when 'high' then 1 else 2 end")
            ->orderBy($this->tab === 'gecmis' ? 'closed_at' : 'last_message_at', $this->tab === 'gecmis' ? 'desc' : 'asc')
            ->paginate(25);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">Destek Talepleri</flux:heading>
        <flux:text class="mt-1">Firmaların panelden açtığı talepler. Yanıtınız müşteriye bildirim ve e-postayla gider; müşteri yanıtları "Yanıt bekleyen" sekmesine düşer.</flux:text>
    </div>

    <x-tabs :active="$tab" :tabs="$this->tabs()" :counts="$this->counts" />

    <div class="flex flex-wrap items-center gap-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Konu, firma veya talep no" class="max-w-xs" />
        <flux:select wire:model.live="priority" class="max-w-44">
            <flux:select.option value="">Tüm öncelikler</flux:select.option>
            @foreach (TicketPriority::cases() as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:checkbox wire:model.live="mine" label="Bana atananlar" />
    </div>

    <flux:table :paginate="$this->tickets">
        <flux:table.columns>
            <flux:table.column>No</flux:table.column>
            <flux:table.column>Konu</flux:table.column>
            <flux:table.column>Firma</flux:table.column>
            <flux:table.column>Öncelik</flux:table.column>
            <flux:table.column>Durum</flux:table.column>
            <flux:table.column>Son mesaj</flux:table.column>
            <flux:table.column>İlgilenen</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->tickets as $ticket)
                <flux:table.row :key="$ticket->id">
                    <flux:table.cell class="font-semibold tabular-nums">
                        <a href="{{ route('admin.support.show', $ticket) }}" wire:navigate class="hover:underline">{{ $ticket->number() }}</a>
                    </flux:table.cell>
                    <flux:table.cell>
                        <a href="{{ route('admin.support.show', $ticket) }}" wire:navigate class="font-medium hover:underline">{{ $ticket->subject }}</a>
                        <div class="text-xs text-zinc-500">{{ $ticket->category }} · {{ $ticket->module }} · {{ $ticket->user->name ?? '—' }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $ticket->firm->name }}</flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" :color="$ticket->priority->color()" inset="top bottom">{{ $ticket->priority->label() }}</flux:badge></flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" :color="$ticket->status->color()" inset="top bottom">{{ $ticket->status->label() }}</flux:badge></flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $ticket->last_message_at?->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell>{{ $ticket->assignee->name ?? '—' }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500">Bu listede talep yok.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
