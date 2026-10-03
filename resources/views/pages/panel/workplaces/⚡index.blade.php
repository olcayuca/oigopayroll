<?php

use App\Enums\WorkplaceType;
use App\Livewire\PanelComponent;
use App\Models\Company;
use App\Models\Workplace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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

    /** '' (all) | 'merkez' | 'sube' | 'eksik' */
    #[Url(as: 'durum', except: '')]
    public string $status = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'companyId', 'status'], true)) {
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
     * Workplaces of the firm the user can see, before search / segment filters.
     *
     * @return Builder<Workplace>
     */
    protected function baseQuery(): Builder
    {
        return Workplace::visibleTo(Auth::user())
            ->whereIn('company_id', $this->companies->pluck('id'))
            ->when($this->companyId !== '', fn ($query) => $query->where('company_id', $this->companyId));
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'total' => $this->baseQuery()->count(),
            'headquarters' => $this->baseQuery()->where('workplace_type', WorkplaceType::Headquarters)->count(),
            'complete' => $this->baseQuery()->count() - $this->baseQuery()->incompleteSetup()->count(),
            'incomplete' => $this->baseQuery()->incompleteSetup()->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Workplace>
     */
    #[Computed]
    public function workplaces(): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->with(['company', 'province', 'district'])
            ->when($this->status === 'merkez', fn ($query) => $query->where('workplace_type', WorkplaceType::Headquarters))
            ->when($this->status === 'sube', fn ($query) => $query->where('workplace_type', '!=', WorkplaceType::Headquarters))
            ->when($this->status === 'eksik', fn ($query) => $query->incompleteSetup())
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

<div>
    <x-panel.page-header :crumbs="['Kurulum' => null, 'İşyerleri' => null, $this->firm->name => null]" title="İşyerleri"
        subtitle="Bordro hesaplamalarında kullanılan işyerleri, SGK ve vergi tanımları.">
        <x-slot:actions>
            @if ($this->companies->isNotEmpty())
                <flux:button icon="arrow-down-tray" :href="route('exports.download', ['type' => 'isyerleri', ...($companyId !== '' ? ['sirket' => $companyId] : [])])">Excel İndir</flux:button>
            @endif
            @can('import', [\App\Models\Workplace::class, $this->firm])
                <flux:button icon="table-cells" :href="route('imports.create', 'isyeri')" wire:navigate>Excel ile Aktar</flux:button>
            @endcan
            @if ($this->companies->isNotEmpty())
                <flux:button variant="primary" icon="plus" :href="route('workplaces.create', $companyId !== '' ? ['sirket' => $companyId] : [])" wire:navigate>Yeni İşyeri</flux:button>
            @endif
        </x-slot:actions>
    </x-panel.page-header>

    @if ($this->companies->isEmpty())
        <x-panel.alert variant="info" title="Önce şirket oluşturun">
            İşyerleri bir şirkete bağlı olarak oluşturulur.
            <x-slot:actions>
                <flux:button size="sm" :href="route('companies.index')" wire:navigate>Şirketlere Git</flux:button>
            </x-slot:actions>
        </x-panel.alert>
    @else
        <div class="mb-[18px] grid grid-cols-2 gap-3 sm:gap-3.5 xl:grid-cols-4">
            <x-panel.stat :value="$this->stats['total']" label="Toplam işyeri" color="navy" />
            <x-panel.stat :value="$this->stats['headquarters']" label="Merkez" color="blue" />
            <x-panel.stat :value="$this->stats['complete']" label="Kurulumu tamam" color="mint" />
            <x-panel.stat :value="$this->stats['incomplete']" label="Eksik bilgi" color="amber" />
        </div>

        <x-panel.table :paginate="$this->workplaces" min-width="1000px">
            <x-slot:toolbar>
                <x-panel.search wire:model.live.debounce.300ms="search" placeholder="Şube adı, no, SGK sicil..." />
                <x-panel.segmented model="status" :current="$status" :options="['' => 'Tümü', 'merkez' => 'Merkez', 'sube' => 'Şube / diğer', 'eksik' => 'Eksik bilgi']" />
                @if ($this->companies->count() > 1)
                    <select wire:model.live="companyId" aria-label="Şirket"
                        class="h-[38px] rounded-[9px] border-[1.5px] border-[#E8EDF3] bg-field ps-3 pe-8 text-[13px] font-semibold text-ink-2 outline-none focus:border-brand">
                        <option value="">Tüm şirketler</option>
                        @foreach ($this->companies as $company)
                            <option value="{{ $company->id }}">{{ $company->company_no }} · {{ $company->short_name }}</option>
                        @endforeach
                    </select>
                @endif
            </x-slot:toolbar>

            <x-slot:head>
                <x-panel.th>Şirket / Şube</x-panel.th>
                <x-panel.th>Tip</x-panel.th>
                <x-panel.th>Vergi No</x-panel.th>
                <x-panel.th>SGK Sicil No</x-panel.th>
                <x-panel.th>Tehlike Sınıfı</x-panel.th>
                <x-panel.th>İl / İlçe</x-panel.th>
                <x-panel.th>Kurulum</x-panel.th>
                <x-panel.th class="w-10"></x-panel.th>
            </x-slot:head>

            @foreach ($this->workplaces as $workplace)
                @php
                    $percent = $workplace->setupPercent();
                    $hazardColor = ['az_tehlikeli' => 'green', 'tehlikeli' => 'amber', 'cok_tehlikeli' => 'red'][$workplace->hazard_class->value] ?? 'gray';
                @endphp
                <x-panel.tr :href="route('workplaces.show', $workplace)" wire:key="workplace-{{ $workplace->id }}">
                    <td class="py-3.5 ps-[18px] pe-3.5">
                        <div class="flex items-center gap-[11px]">
                            <x-panel.avatar :initials="\App\Support\Text::initials($workplace->company->short_name)"
                                :tone="$workplace->workplace_type === WorkplaceType::Headquarters ? 'navy' : 'soft'" />
                            <div class="min-w-0">
                                <a href="{{ route('workplaces.show', $workplace) }}" wire:navigate class="block max-w-[260px] truncate text-[13.5px] font-bold text-ink hover:text-brand">{{ $workplace->company->short_name }}</a>
                                <div class="text-xs whitespace-nowrap text-muted-2">{{ $workplace->branch_name }} · No {{ $workplace->workplace_no }}</div>
                            </div>
                        </div>
                    </td>
                    <x-panel.td>
                        <x-panel.badge :color="$workplace->workplace_type === WorkplaceType::Headquarters ? 'navy' : 'gray'">
                            {{ $workplace->workplace_type->label() }}
                        </x-panel.badge>
                    </x-panel.td>
                    <x-panel.td :sub="$workplace->tax_office">{{ $workplace->tax_number }}</x-panel.td>
                    <x-panel.td :sub="$workplace->sgk_directorate">
                        {{ $workplace->sgk_registry_no ? \Illuminate\Support\Str::limit($workplace->sgk_registry_no, 4, '').'…'.substr($workplace->sgk_registry_no, -6) : 'Girilmedi' }}
                    </x-panel.td>
                    <x-panel.td><x-panel.badge :color="$hazardColor">{{ $workplace->hazard_class->label() }}</x-panel.badge></x-panel.td>
                    <x-panel.td :sub="$workplace->districtLabel()">{{ $workplace->provinceLabel() }}</x-panel.td>
                    <td class="px-3.5 py-3.5">
                        <div class="flex min-w-[130px] items-center gap-2.5" title="{{ count($workplace->missingSetupFields()) }} alan boş">
                            <div class="h-1.5 flex-1 overflow-hidden rounded bg-line-3">
                                <div @class(['h-full rounded', 'bg-mint' => $percent === 100, 'bg-st-blue-dot' => $percent < 100 && $percent >= 60, 'bg-st-amber-dot' => $percent < 60]) style="width: {{ max($percent, 3) }}%"></div>
                            </div>
                            <span class="w-9 text-end text-xs font-bold text-ink-2 tabular-nums">%{{ $percent }}</span>
                        </div>
                    </td>
                    <td class="pe-4 text-faint"><flux:icon.chevron-right variant="micro" class="size-4" /></td>
                </x-panel.tr>
            @endforeach

            <x-slot:empty>
                @if ($this->workplaces->isEmpty())
                    <x-panel.empty title="İşyeri bulunamadı">
                        {{ $search !== '' || $status !== '' ? 'Aramaya veya filtreye uyan işyeri yok.' : 'Bu firmada henüz işyeri yok. "Yeni İşyeri" veya "Excel ile Aktar" ile başlayın.' }}
                    </x-panel.empty>
                @endif
            </x-slot:empty>
        </x-panel.table>
    @endif
</div>
