<?php

use App\Livewire\PanelComponent;
use App\Models\Company;
use App\Models\Workplace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

new #[Title('İşyerleri')] class extends PanelComponent {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'sirket', except: '')]
    public string $companyId = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'companyId'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return Collection<int, Company>
     */
    #[Computed]
    public function companies(): Collection
    {
        return Company::visibleTo(Auth::user())->where('firm_id', $this->firm->id)->orderBy('company_no')->get(['id', 'company_no', 'short_name']);
    }

    /**
     * @return LengthAwarePaginator<int, Workplace>
     */
    #[Computed]
    public function workplaces(): LengthAwarePaginator
    {
        return Workplace::visibleTo(Auth::user())
            ->whereIn('company_id', $this->companies->pluck('id'))
            ->with(['company', 'province', 'district'])
            ->when($this->companyId !== '', fn ($query) => $query->where('company_id', $this->companyId))
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('branch_name', 'like', '%'.$this->search.'%')
                ->orWhere('workplace_no', 'like', '%'.$this->search.'%')
                ->orWhere('sgk_registry_no', 'like', '%'.$this->search.'%')
                ->orWhere('title', 'like', '%'.$this->search.'%')))
            ->orderBy('company_id')
            ->orderBy('workplace_no')
            ->paginate(25);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">İşyerleri</flux:heading>
            <flux:text class="mt-1">{{ $this->firm->name }} firmasının şirketlerine bağlı işyerleri.</flux:text>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($this->companies->isNotEmpty())
                <flux:button icon="arrow-down-tray" :href="route('exports.download', ['type' => 'isyerleri', ...($companyId !== '' ? ['sirket' => $companyId] : [])])">Excel İndir</flux:button>
            @endif
            @can('import', [\App\Models\Workplace::class, $this->firm])
                <flux:button icon="table-cells" :href="route('imports.create', 'isyeri')" wire:navigate>Excel ile Aktar</flux:button>
            @endcan
            @if ($this->companies->isNotEmpty())
                <flux:button variant="primary" icon="plus" :href="route('workplaces.create', $companyId !== '' ? ['sirket' => $companyId] : [])" wire:navigate>Yeni İşyeri</flux:button>
            @endif
        </div>
    </div>

    @if ($this->companies->isEmpty())
        <flux:callout icon="information-circle" heading="Önce şirket oluşturun"
            text="İşyerleri bir şirkete bağlı olarak oluşturulur.">
            <x-slot name="actions">
                <flux:button size="sm" :href="route('companies.index')" wire:navigate>Şirketlere Git</flux:button>
            </x-slot>
        </flux:callout>
    @else
        <div class="flex flex-wrap gap-3">
            <div class="w-full sm:w-72">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Şube adı, no, SGK sicil..." clearable />
            </div>
            <div class="w-full sm:w-72">
                <flux:select wire:model.live="companyId">
                    <flux:select.option value="">Tüm şirketler</flux:select.option>
                    @foreach ($this->companies as $company)
                        <flux:select.option value="{{ $company->id }}">{{ $company->company_no }} · {{ $company->short_name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <flux:table :paginate="$this->workplaces">
            <flux:table.columns>
                <flux:table.column>Şirket</flux:table.column>
                <flux:table.column>İşyeri No</flux:table.column>
                <flux:table.column>Şube Adı</flux:table.column>
                <flux:table.column>Tip / Tür</flux:table.column>
                <flux:table.column>İl / İlçe</flux:table.column>
                <flux:table.column>Tehlike</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->workplaces as $workplace)
                    <flux:table.row :key="$workplace->id">
                        <flux:table.cell>{{ $workplace->company->short_name }}</flux:table.cell>
                        <flux:table.cell>{{ $workplace->workplace_no }}</flux:table.cell>
                        <flux:table.cell variant="strong">
                            <a href="{{ route('workplaces.show', $workplace) }}" wire:navigate class="hover:underline">{{ $workplace->branch_name }}</a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $workplace->workplace_type->label() }} · {{ $workplace->workplace_kind->label() }}</flux:table.cell>
                        <flux:table.cell>{{ $workplace->provinceLabel() }} / {{ $workplace->districtLabel() }}</flux:table.cell>
                        <flux:table.cell>{{ $workplace->hazard_class->label() }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="chevron-right" :href="route('workplaces.show', $workplace)" wire:navigate />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500">İşyeri bulunamadı.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif
</div>
