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

    /** '' (all) | 'isyeri-var' | 'isyeri-yok' */
    #[Url(as: 'durum', except: '')]
    public string $status = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        $companies = Company::visibleTo(Auth::user())->where('firm_id', $this->firm->id)->withCount('workplaces')->get(['id']);

        return [
            'total' => $companies->count(),
            'withWorkplace' => $companies->where('workplaces_count', '>', 0)->count(),
            'withoutWorkplace' => $companies->where('workplaces_count', 0)->count(),
            'workplaces' => (int) $companies->sum('workplaces_count'),
        ];
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
            ->when($this->status === 'isyeri-var', fn ($query) => $query->has('workplaces'))
            ->when($this->status === 'isyeri-yok', fn ($query) => $query->withoutWorkplaces())
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('title', 'like', '%'.$this->search.'%')
                ->orWhere('short_name', 'like', '%'.$this->search.'%')
                ->orWhere('company_no', 'like', '%'.$this->search.'%')
                ->orWhere('tax_number', 'like', '%'.$this->search.'%')))
            ->orderBy('company_no')
            ->paginate(20);
    }
}; ?>

<div>
    <x-panel.page-header :crumbs="['Kurulum' => null, 'Şirketler' => null, $this->firm->name => null]" title="Şirketler"
        subtitle="Firmanıza bağlı şirketler. Her şirketin en az bir işyeri olmalıdır.">
        <x-slot:actions>
            <flux:button icon="arrow-down-tray" :href="route('exports.download', 'sirketler')">Excel İndir</flux:button>
            @can('import', [\App\Models\Company::class, $this->firm])
                <flux:button icon="table-cells" :href="route('imports.create', 'sirket')" wire:navigate>Excel ile Aktar</flux:button>
            @endcan
            @can('create', [\App\Models\Company::class, $this->firm])
                <flux:button variant="primary" icon="plus" :href="route('companies.create')" wire:navigate>Yeni Şirket</flux:button>
            @endcan
        </x-slot:actions>
    </x-panel.page-header>

    @unless ($this->firm->isActive())
        <x-panel.alert variant="warning" title="Firma aktif değil" class="mb-[18px]">
            Firma onaylanana (veya yeniden aktifleştirilene) kadar şirket eklenemez ve düzenlenemez.
        </x-panel.alert>
    @endunless

    <div class="mb-[18px] grid grid-cols-2 gap-3 sm:gap-3.5 xl:grid-cols-4">
        <x-panel.stat :value="$this->stats['total']" label="Toplam şirket" color="navy" />
        <x-panel.stat :value="$this->stats['withWorkplace']" label="İşyeri tanımlı" color="mint" />
        <x-panel.stat :value="$this->stats['withoutWorkplace']" label="İşyeri yok" color="amber" />
        <x-panel.stat :value="$this->stats['workplaces']" label="Toplam işyeri" color="blue" :href="route('workplaces.index')" />
    </div>

    <x-panel.table :paginate="$this->companies">
        <x-slot:toolbar>
            <x-panel.search wire:model.live.debounce.300ms="search" placeholder="Unvan, kısa ad, numara veya VKN..." />
            <x-panel.segmented model="status" :current="$status" :options="['' => 'Tümü', 'isyeri-var' => 'İşyeri var', 'isyeri-yok' => 'İşyeri yok']" />
        </x-slot:toolbar>

        <x-slot:head>
            <x-panel.th>Şirket</x-panel.th>
            <x-panel.th>Tip</x-panel.th>
            <x-panel.th>Sektör</x-panel.th>
            <x-panel.th>Vergi No</x-panel.th>
            <x-panel.th>MERSİS</x-panel.th>
            <x-panel.th align="end">İşyeri</x-panel.th>
            <x-panel.th class="w-10"></x-panel.th>
        </x-slot:head>

        @foreach ($this->companies as $company)
            <x-panel.tr :href="route('companies.show', $company)" wire:key="company-{{ $company->id }}">
                <td class="py-3.5 ps-[18px] pe-3.5">
                    <div class="flex items-center gap-[11px]">
                        <x-panel.avatar :initials="\App\Support\Text::initials($company->short_name)" tone="navy" />
                        <div class="min-w-0">
                            <a href="{{ route('companies.show', $company) }}" wire:navigate class="block max-w-[320px] truncate text-[13.5px] font-bold text-ink hover:text-brand">{{ $company->title }}</a>
                            <div class="text-xs whitespace-nowrap text-muted-2">{{ $company->short_name }} · No {{ $company->company_no }}</div>
                        </div>
                    </div>
                </td>
                <x-panel.td><x-panel.badge color="navy">{{ $company->company_type->label() }}</x-panel.badge></x-panel.td>
                <x-panel.td>{{ $company->sector->name }}</x-panel.td>
                <x-panel.td :sub="$company->tax_office">{{ $company->tax_number }}</x-panel.td>
                <x-panel.td>{{ $company->mersis_no ?: '—' }}</x-panel.td>
                <x-panel.td align="end">
                    @if ($company->workplaces_count === 0)
                        <x-panel.badge color="amber">İşyeri yok</x-panel.badge>
                    @else
                        <span class="tabular-nums">{{ $company->workplaces_count }}</span>
                    @endif
                </x-panel.td>
                <td class="pe-4 text-faint"><flux:icon.chevron-right variant="micro" class="size-4" /></td>
            </x-panel.tr>
        @endforeach

        <x-slot:empty>
            @if ($this->companies->isEmpty())
                <x-panel.empty :icon="$search !== '' || $status !== '' ? 'magnifying-glass' : 'building-office'"
                    :title="$search !== '' || $status !== '' ? 'Sonuç bulunamadı' : 'Henüz şirket yok'">
                    {{ $search !== '' || $status !== '' ? 'Aramaya veya filtreye uyan şirket yok.' : '"Yeni Şirket" veya "Excel ile Aktar" ile başlayın.' }}
                </x-panel.empty>
            @endif
        </x-slot:empty>
    </x-panel.table>
</div>
