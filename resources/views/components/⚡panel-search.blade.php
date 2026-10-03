<?php

use App\Models\Company;
use App\Models\Workplace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/*
 * Panel top bar search: companies and workplaces of the active firm the user can see.
 */
new class extends Component {
    public string $query = '';

    /**
     * @return Collection<int, Company>
     */
    #[Computed]
    public function companies(): Collection
    {
        $firm = Auth::user()->activeFirm();

        if (! $firm || mb_strlen(trim($this->query)) < 2) {
            return collect();
        }

        $term = '%'.trim($this->query).'%';

        return Company::visibleTo(Auth::user())
            ->where('firm_id', $firm->id)
            ->where(fn ($query) => $query
                ->where('title', 'like', $term)
                ->orWhere('short_name', 'like', $term)
                ->orWhere('company_no', 'like', $term)
                ->orWhere('tax_number', 'like', $term))
            ->orderBy('company_no')
            ->limit(5)
            ->get(['id', 'firm_id', 'company_no', 'short_name', 'title', 'tax_number']);
    }

    /**
     * @return Collection<int, Workplace>
     */
    #[Computed]
    public function workplaces(): Collection
    {
        $firm = Auth::user()->activeFirm();

        if (! $firm || mb_strlen(trim($this->query)) < 2) {
            return collect();
        }

        $term = '%'.trim($this->query).'%';

        return Workplace::visibleTo(Auth::user())
            ->whereHas('company', fn ($query) => $query->where('firm_id', $firm->id))
            ->where(fn ($query) => $query
                ->where('branch_name', 'like', $term)
                ->orWhere('workplace_no', 'like', $term)
                ->orWhere('sgk_registry_no', 'like', $term))
            ->with('company:id,short_name')
            ->orderBy('workplace_no')
            ->limit(6)
            ->get();
    }
}; ?>

<div class="relative w-10 shrink-0 lg:w-auto lg:max-w-[360px] lg:min-w-[180px] lg:flex-[1_1_200px]"
    x-data="{ open: false }"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false; $refs.input.blur()"
    x-on:keydown.window.prevent.slash="if (! ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) $refs.input.focus()">
    <flux:icon.magnifying-glass variant="mini" class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-faint" />
    <input x-ref="input" type="search" wire:model.live.debounce.250ms="query" x-on:focus="open = true" x-on:input="open = true"
        placeholder="Şirket, işyeri veya SGK sicil ara..." aria-label="Panelde ara" autocomplete="off"
        class="h-10 w-full rounded-[10px] border-[1.5px] border-[#E8EDF3] bg-field ps-9 pe-3 text-[13.5px] text-ink outline-none placeholder:text-faint focus:border-brand focus:bg-white max-lg:cursor-pointer max-lg:placeholder:text-transparent max-lg:focus:fixed max-lg:focus:inset-x-4 max-lg:focus:top-3 max-lg:focus:z-40 max-lg:focus:w-auto max-lg:focus:placeholder:text-faint" />

    @if (mb_strlen(trim($query)) >= 2)
        <div x-cloak x-show="open" x-transition.opacity.duration.150ms
            class="fixed inset-x-4 top-16 z-40 overflow-hidden rounded-[14px] border border-[#E5EAF1] bg-white shadow-[0_16px_40px_rgba(16,40,72,0.16)] lg:absolute lg:inset-x-auto lg:start-0 lg:top-12 lg:w-[420px]">
            @if ($this->companies->isEmpty() && $this->workplaces->isEmpty())
                <div class="px-4 py-8 text-center text-[13px] text-muted">“{{ $query }}” için sonuç yok.</div>
            @else
                <div class="max-h-[420px] overflow-y-auto p-1.5">
                    @if ($this->companies->isNotEmpty())
                        <div class="px-2.5 pt-2 pb-1 text-[10.5px] font-bold tracking-[0.12em] text-muted-2">ŞİRKETLER</div>
                        @foreach ($this->companies as $company)
                            <a href="{{ route('companies.show', $company) }}" wire:navigate wire:key="s-co-{{ $company->id }}" class="flex items-center gap-3 rounded-[10px] px-2.5 py-2 hover:bg-[#F1F5FA]">
                                <x-panel.avatar :initials="\App\Support\Text::initials($company->short_name)" size="sm" />
                                <span class="min-w-0">
                                    <span class="block truncate text-[13px] font-bold text-ink">{{ $company->title }}</span>
                                    <span class="block truncate text-[11.5px] text-muted-2">No {{ $company->company_no }} · VKN {{ $company->tax_number }}</span>
                                </span>
                            </a>
                        @endforeach
                    @endif
                    @if ($this->workplaces->isNotEmpty())
                        <div class="px-2.5 pt-2 pb-1 text-[10.5px] font-bold tracking-[0.12em] text-muted-2">İŞYERLERİ</div>
                        @foreach ($this->workplaces as $workplace)
                            <a href="{{ route('workplaces.show', $workplace) }}" wire:navigate wire:key="s-wp-{{ $workplace->id }}" class="flex items-center gap-3 rounded-[10px] px-2.5 py-2 hover:bg-[#F1F5FA]">
                                <span class="flex size-[30px] shrink-0 items-center justify-center rounded-lg bg-mint-soft text-mint-strong">
                                    <flux:icon.map-pin variant="micro" class="size-4" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-[13px] font-bold text-ink">{{ $workplace->branch_name }}</span>
                                    <span class="block truncate text-[11.5px] text-muted-2">{{ $workplace->company->short_name }} · No {{ $workplace->workplace_no }}</span>
                                </span>
                            </a>
                        @endforeach
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
