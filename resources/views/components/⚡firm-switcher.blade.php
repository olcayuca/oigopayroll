<?php

use App\Models\Firm;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $search = '';

    #[Computed]
    public function current(): ?Firm
    {
        return Auth::user()->activeFirm();
    }

    /**
     * @return Collection<int, Firm>
     */
    #[Computed]
    public function firms(): Collection
    {
        return Firm::visibleTo(Auth::user())
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->limit(25)
            ->get();
    }

    #[Computed]
    public function total(): int
    {
        return Firm::visibleTo(Auth::user())->count();
    }

    public function switchTo(int $firmId): void
    {
        $firm = Firm::visibleTo(Auth::user())->findOrFail($firmId);

        Auth::user()->switchFirm($firm);

        $this->redirect(url('/'), navigate: true);
    }
}; ?>

<div>
    @if ($this->current)
        <flux:modal.trigger name="firm-switcher">
            <button type="button" class="flex w-full items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-start hover:bg-zinc-800/5 dark:border-zinc-700 dark:hover:bg-white/10" data-test="firm-switcher">
                <flux:icon name="building-office-2" variant="mini" class="shrink-0 text-zinc-400" />
                <div class="grid flex-1 leading-tight">
                    <span class="truncate text-sm font-medium">{{ $this->current->name }}</span>
                    <span class="text-xs text-zinc-500">{{ $this->current->status->label() }}</span>
                </div>
                @if ($this->total > 1)
                    <flux:icon name="chevrons-up-down" variant="micro" class="text-zinc-400" />
                @endif
            </button>
        </flux:modal.trigger>

        <flux:modal name="firm-switcher" class="md:w-[28rem]">
            <div class="space-y-4">
                <flux:heading size="lg">Firma Seç</flux:heading>

                @if ($this->total > 5)
                    <flux:input wire:model.live.debounce.250ms="search" icon="magnifying-glass" placeholder="Firma ara..." autofocus />
                @endif

                <div class="max-h-80 space-y-1 overflow-y-auto">
                    @forelse ($this->firms as $firm)
                        <button type="button" wire:click="switchTo({{ $firm->id }})"
                            class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-start text-sm hover:bg-zinc-800/5 dark:hover:bg-white/10 {{ $firm->id === $this->current->id ? 'bg-zinc-800/5 dark:bg-white/10' : '' }}">
                            <span class="truncate">{{ $firm->name }}</span>
                            <flux:badge size="sm" :color="$firm->status->color()">{{ $firm->status->label() }}</flux:badge>
                        </button>
                    @empty
                        <flux:text class="py-6 text-center">Firma bulunamadı.</flux:text>
                    @endforelse
                </div>
            </div>
        </flux:modal>
    @endif
</div>
