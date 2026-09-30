<?php

use App\Livewire\PanelComponent;
use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

new #[Title('Şirketler')] class extends PanelComponent {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Company>
     */
    #[Computed]
    public function companies(): LengthAwarePaginator
    {
        return Company::visibleTo(Auth::user())
            ->where('firm_id', $this->firm->id)
            ->with('sector')
            ->withCount('workplaces')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('title', 'like', '%'.$this->search.'%')
                ->orWhere('short_name', 'like', '%'.$this->search.'%')
                ->orWhere('company_no', 'like', '%'.$this->search.'%')
                ->orWhere('tax_number', 'like', '%'.$this->search.'%')))
            ->orderBy('company_no')
            ->paginate(20);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Şirketler</flux:heading>
            <flux:text class="mt-1">{{ $this->firm->name }} firmasına bağlı şirketler. Her şirketin en az bir işyeri olmalıdır.</flux:text>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button icon="arrow-down-tray" :href="route('exports.download', 'sirketler')">Excel İndir</flux:button>
            @can('import', [\App\Models\Company::class, $this->firm])
                <flux:button icon="table-cells" :href="route('imports.create', 'sirket')" wire:navigate>Excel ile Aktar</flux:button>
            @endcan
            @can('create', [\App\Models\Company::class, $this->firm])
                <flux:button variant="primary" icon="plus" :href="route('companies.create')" wire:navigate>Yeni Şirket</flux:button>
            @endcan
        </div>
    </div>

    @unless ($this->firm->isActive())
        <flux:callout icon="information-circle" color="amber" heading="Firma aktif değil"
            text="Firma onaylanana (veya yeniden aktifleştirilene) kadar şirket eklenemez ve düzenlenemez." />
    @endunless

    <div class="w-full sm:w-80">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Unvan, kısa ad, numara veya VKN..." clearable />
    </div>

    <flux:table :paginate="$this->companies">
        <flux:table.columns>
            <flux:table.column>No</flux:table.column>
            <flux:table.column>Unvan</flux:table.column>
            <flux:table.column>Tip</flux:table.column>
            <flux:table.column>Sektör</flux:table.column>
            <flux:table.column>Vergi No / Dairesi</flux:table.column>
            <flux:table.column align="end">İşyeri</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->companies as $company)
                <flux:table.row :key="$company->id">
                    <flux:table.cell>{{ $company->company_no }}</flux:table.cell>
                    <flux:table.cell variant="strong">
                        <a href="{{ route('companies.show', $company) }}" wire:navigate class="hover:underline">{{ $company->title }}</a>
                        <div class="text-xs font-normal text-zinc-500">{{ $company->short_name }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $company->company_type->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $company->sector->name }}</flux:table.cell>
                    <flux:table.cell>{{ $company->tax_number }} <div class="text-xs text-zinc-500">{{ $company->tax_office }}</div></flux:table.cell>
                    <flux:table.cell align="end">
                        @if ($company->workplaces_count === 0)
                            <flux:badge size="sm" color="amber" inset="top bottom">İşyeri yok</flux:badge>
                        @else
                            {{ $company->workplaces_count }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" variant="ghost" icon="chevron-right" :href="route('companies.show', $company)" wire:navigate />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500">
                        {{ $search !== '' ? 'Aramaya uyan şirket yok.' : 'Henüz şirket yok. "Yeni Şirket" veya "Excel ile Aktar" ile başlayın.' }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
