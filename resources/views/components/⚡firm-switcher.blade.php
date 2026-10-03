<?php

use App\Models\Company;
use App\Models\Firm;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * Panel top bar: the active firm and a dropdown to switch firms / jump to one of its companies.
 */
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
            ->withCount(['companies', 'workplaces'])
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('tax_number', 'like', '%'.$this->search.'%')))
            ->orderBy('name')
            ->limit(25)
            ->get();
    }

    #[Computed]
    public function total(): int
    {
        return Firm::visibleTo(Auth::user())->count();
    }

    /**
     * Companies of the active firm the user can see (quick links).
     *
     * @return Collection<int, Company>
     */
    #[Computed]
    public function companies(): Collection
    {
        if (! $this->current) {
            return collect();
        }

        return Company::visibleTo(Auth::user())
            ->where('firm_id', $this->current->id)
            ->withCount('workplaces')
            ->orderBy('company_no')
            ->limit(8)
            ->get(['id', 'firm_id', 'company_no', 'short_name', 'tax_number']);
    }

    public function switchTo(int $firmId): void
    {
        $firm = Firm::visibleTo(Auth::user())->findOrFail($firmId);

        Auth::user()->switchFirm($firm);

        $this->redirect(url('/'), navigate: true);
    }
}; ?>

<div class="relative shrink-0" x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false">
    @if ($this->current)
        <button type="button" x-on:click="open = ! open" data-test="firm-switcher"
            class="flex h-11 items-center gap-2.5 rounded-[11px] border-[1.5px] bg-white ps-2 pe-3 text-start transition"
            x-bind:class="open ? 'border-brand' : 'border-[#E8EDF3] hover:border-line-2'" aria-haspopup="true" x-bind:aria-expanded="open">
            <x-panel.avatar :initials="\App\Support\Text::initials($this->current->name)" size="sm" tone="navy" />
            <span class="hidden min-w-0 flex-col leading-tight sm:flex">
                <span class="max-w-[220px] truncate text-[13px] font-extrabold text-ink">{{ $this->current->name }}</span>
                <span class="truncate text-[11px] font-semibold text-muted-2">
                    {{ $this->current->tax_number ? 'VKN '.$this->current->tax_number : $this->current->status->label() }}
                </span>
            </span>
            <flux:icon.chevron-down variant="micro" class="size-4 text-faint" />
        </button>

        <div x-cloak x-show="open" x-transition.opacity.duration.150ms
            class="absolute end-0 top-[52px] z-30 w-[360px] max-w-[calc(100vw-2rem)] overflow-hidden rounded-[14px] border border-[#E5EAF1] bg-white shadow-[0_16px_40px_rgba(16,40,72,0.16)]">
            <div class="border-b border-line-3 px-4 pt-3.5 pb-2.5">
                <div class="text-[10.5px] font-bold tracking-[0.12em] text-muted-2">FİRMA</div>
                <div class="text-[13px] font-bold text-ink">
                    {{ $this->total > 1 ? 'Çalışmak istediğiniz firmayı seçin' : $this->current->name }}
                </div>
            </div>

            @if ($this->total > 1)
                @if ($this->total > 5)
                    <div class="px-3 pt-3">
                        <x-panel.search wire:model.live.debounce.250ms="search" placeholder="Firma adı veya VKN..." class="!max-w-none" />
                    </div>
                @endif
                <div class="max-h-[300px] overflow-y-auto p-1.5">
                    @forelse ($this->firms as $firm)
                        @php($active = $firm->id === $this->current->id)
                        <button type="button" wire:click="switchTo({{ $firm->id }})" wire:key="firm-{{ $firm->id }}"
                            @class(['flex w-full items-center gap-3 rounded-[10px] p-2.5 text-start transition hover:bg-[#F1F5FA]', 'bg-[#F1F5FA]' => $active])>
                            <x-panel.avatar :initials="\App\Support\Text::initials($firm->name)" :tone="$active ? 'navy' : 'soft'" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[13.5px] font-bold text-ink">{{ $firm->name }}</span>
                                <span class="block truncate text-[11.5px] text-muted-2">
                                    @if ($firm->tax_number) VKN {{ $firm->tax_number }} · @endif{{ $firm->companies_count }} şirket · {{ $firm->workplaces_count }} işyeri
                                </span>
                            </span>
                            @if ($active)
                                <flux:icon.check variant="mini" class="size-[17px] text-mint" />
                            @elseif (! $firm->isActive())
                                <x-panel.badge :color="$firm->status->color()" :dot="false">{{ $firm->status->label() }}</x-panel.badge>
                            @endif
                        </button>
                    @empty
                        <div class="px-3 py-6 text-center text-[13px] text-muted">Firma bulunamadı.</div>
                    @endforelse
                </div>
            @endif

            @if ($this->companies->isNotEmpty())
                <div @class(['p-1.5', 'border-t border-line-3' => $this->total > 1])>
                    <div class="px-2.5 pt-1.5 pb-1 text-[10.5px] font-bold tracking-[0.12em] text-muted-2">ŞİRKETLER</div>
                    @foreach ($this->companies as $company)
                        <a href="{{ route('companies.show', $company) }}" wire:navigate wire:key="co-{{ $company->id }}" x-on:click="open = false"
                            class="flex items-center gap-3 rounded-[10px] px-2.5 py-2 hover:bg-[#F1F5FA]">
                            <x-panel.avatar :initials="\App\Support\Text::initials($company->short_name)" size="sm" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[13px] font-bold text-ink">{{ $company->short_name }}</span>
                                <span class="block truncate text-[11.5px] text-muted-2">VKN {{ $company->tax_number }} · {{ $company->workplaces_count }} işyeri</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="border-t border-line-3 px-4 py-2.5 text-[11.5px] text-muted-2">Seçili firma tüm panel ekranlarına uygulanır.</div>
        </div>
    @endif
</div>
