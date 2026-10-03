<?php

use App\Livewire\PanelComponent;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

new #[Title('Personel')] class extends PanelComponent {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /** '' (all) | 'aktif' | 'pasif' | 'ayrildi' | 'eksik' */
    #[Url(as: 'durum', except: '')]
    public string $status = '';

    #[Url(as: 'sirket', except: '')]
    public string $companyId = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'companyId'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Personnel of the active firm the user may view.
     *
     * @return Builder<Employee>
     */
    protected function employeeQuery(): Builder
    {
        return Employee::query()->viewableBy(Auth::user(), $this->firm);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        $base = fn () => $this->employeeQuery()->when($this->companyId !== '', fn ($query) => $query->where('company_id', $this->companyId));

        return [
            'total' => $base()->count(),
            'active' => $base()->where('status', Employee::ACTIVE)->count(),
            'inactive' => $base()->where('status', '!=', Employee::ACTIVE)->count(),
            'incomplete' => $base()->incomplete()->count(),
        ];
    }

    /**
     * @return Collection<int, Company>
     */
    #[Computed]
    public function companies(): Collection
    {
        return Company::query()->whereIn('id', $this->employeeQuery()->select('company_id'))
            ->orderBy('company_no')->get(['id', 'company_no', 'short_name']);
    }

    /**
     * @return LengthAwarePaginator<int, Employee>
     */
    #[Computed]
    public function employees(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return $this->employeeQuery()
            ->with(['company:id,short_name', 'workplace:id,branch_name', 'title:id,name', 'position:id,name'])
            ->when($this->companyId !== '', fn ($query) => $query->where('company_id', $this->companyId))
            ->when($this->status === 'aktif', fn ($query) => $query->where('status', Employee::ACTIVE))
            ->when($this->status === 'pasif', fn ($query) => $query->where('status', Employee::PASSIVE))
            ->when($this->status === 'ayrildi', fn ($query) => $query->where('status', Employee::LEFT))
            ->when($this->status === 'eksik', fn ($query) => $query->incomplete())
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('registry_no', 'like', '%'.$search.'%')
                ->orWhere('first_name', 'like', '%'.$search.'%')
                ->orWhere('last_name', 'like', '%'.$search.'%')
                ->orWhere(function ($query) use ($search) {
                    // "Ayşe Yıl" → every word in the first or last name
                    foreach (preg_split('/\s+/', $search) ?: [] as $word) {
                        $query->where(fn ($query) => $query->where('first_name', 'like', '%'.$word.'%')->orWhere('last_name', 'like', '%'.$word.'%'));
                    }
                })
                ->when(preg_match('/^\d{11}$/', $search) === 1, fn ($query) => $query->orWhere('tckn_hash', Employee::hashTckn($search)))))
            ->orderBy('registry_no')
            ->paginate(25);
    }
}; ?>

<div>
    <x-panel.page-header :crumbs="['Kurulum' => null, 'Personel' => null, $this->firm->name => null]" title="Personel"
        subtitle="Çalışan kayıtları ve bordro parametreleri. Alanlar müşteri kurulum dosyasıyla (Personel Bilgileri) birebir aynıdır.">
        <x-slot:actions>
            @can('import', [\App\Models\Employee::class, $this->firm])
                <flux:button icon="table-cells" :href="route('imports.create', 'personel')" wire:navigate>Excel ile Aktar</flux:button>
            @endcan
            @can('create', [\App\Models\Employee::class, $this->firm])
                <flux:button variant="primary" icon="plus" :href="route('employees.create')" wire:navigate>Yeni Personel</flux:button>
            @endcan
        </x-slot:actions>
    </x-panel.page-header>

    <div class="mb-[18px] grid grid-cols-2 gap-3 sm:gap-3.5 xl:grid-cols-4">
        <x-panel.stat :value="$this->stats['total']" label="Toplam personel" color="navy" />
        <x-panel.stat :value="$this->stats['active']" label="Aktif" color="mint" />
        <x-panel.stat :value="$this->stats['inactive']" label="Pasif / Ayrılan" color="gray" />
        <x-panel.stat :value="$this->stats['incomplete']" label="Eksik bordro verisi" color="amber" />
    </div>

    <x-panel.table :paginate="$this->employees" min-width="1040px">
        <x-slot:toolbar>
            <x-panel.search wire:model.live.debounce.300ms="search" placeholder="Ad, soyad, sicil veya TCKN ara..." />
            <x-panel.segmented model="status" :current="$status" :options="['' => 'Tümü', 'aktif' => 'Aktif', 'pasif' => 'Pasif', 'ayrildi' => 'İşten ayrıldı', 'eksik' => 'Eksik veri']" />
            @if ($this->companies->count() > 1)
                <select wire:model.live="companyId" aria-label="Firma"
                    class="h-[38px] rounded-[9px] border-[1.5px] border-[#E8EDF3] bg-field ps-3 pe-8 text-[13px] font-semibold text-ink-2 outline-none focus:border-brand">
                    <option value="">Tüm şirketler</option>
                    @foreach ($this->companies as $company)
                        <option value="{{ $company->id }}">{{ $company->company_no }} · {{ $company->short_name }}</option>
                    @endforeach
                </select>
            @endif
        </x-slot:toolbar>

        <x-slot:head>
            <x-panel.th>Personel</x-panel.th>
            <x-panel.th>Unvan / Pozisyon</x-panel.th>
            <x-panel.th>Firma / Şube</x-panel.th>
            <x-panel.th>İşe Giriş</x-panel.th>
            <x-panel.th align="end">Ücret</x-panel.th>
            <x-panel.th>Durum</x-panel.th>
            <x-panel.th>Kayıt</x-panel.th>
            <x-panel.th class="w-10"></x-panel.th>
        </x-slot:head>

        @foreach ($this->employees as $employee)
            @php
                $percent = $employee->completionPercent();
                $statusColor = [Employee::ACTIVE => 'green', Employee::PASSIVE => 'gray', Employee::LEFT => 'red'][$employee->status] ?? 'gray';
            @endphp
            <x-panel.tr :href="route('employees.show', $employee)" wire:key="employee-{{ $employee->id }}">
                <td class="py-3.5 ps-[18px] pe-3.5">
                    <div class="flex items-center gap-[11px]">
                        <x-panel.avatar :initials="$employee->initials()" />
                        <div class="min-w-0">
                            <a href="{{ route('employees.show', $employee) }}" wire:navigate class="block max-w-[220px] truncate text-[13.5px] font-bold text-ink hover:text-brand">{{ $employee->fullName() }}</a>
                            <div class="text-xs whitespace-nowrap text-muted-2">Sicil {{ $employee->registry_no }} · {{ $employee->maskedTckn() }}</div>
                        </div>
                    </div>
                </td>
                <x-panel.td :sub="$employee->position?->name">{{ $employee->title?->name ?? '—' }}</x-panel.td>
                <x-panel.td :sub="$employee->workplace?->branch_name">{{ $employee->company?->short_name }}</x-panel.td>
                <x-panel.td>{{ $employee->hire_date?->format('d.m.Y') }}</x-panel.td>
                <x-panel.td align="end" :sub="$employee->wage_type.' · '.$employee->wage_period">
                    <span class="tabular-nums">{{ number_format((float) $employee->wage, 2, ',', '.') }} {{ $employee->currency === 'TRY' ? '₺' : $employee->currency }}</span>
                </x-panel.td>
                <x-panel.td><x-panel.badge :color="$statusColor">{{ Employee::STATUSES[$employee->status] ?? $employee->status }}</x-panel.badge></x-panel.td>
                <td class="px-3.5 py-3.5">
                    <div class="flex min-w-[110px] items-center gap-2.5">
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
            @if ($this->employees->isEmpty())
                <x-panel.empty icon="users" :title="$search !== '' || $status !== '' ? 'Sonuç bulunamadı' : 'Henüz personel yok'">
                    {{ $search !== '' || $status !== '' ? 'Aramaya veya filtreye uyan personel yok.' : 'Kurulum dosyasının Personel Bilgileri sayfasını "Excel ile Aktar" ile yükleyin ya da "Yeni Personel" ile tek tek ekleyin.' }}
                </x-panel.empty>
            @endif
        </x-slot:empty>
    </x-panel.table>
</div>
