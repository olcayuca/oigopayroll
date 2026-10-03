<?php

use App\Actions\Companies\SaveCompany;
use App\Livewire\PanelComponent;
use App\Models\Company;
use App\Models\Workplace;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Title('Şirket')] class extends PanelComponent {
    public Company $company;

    #[Url(as: 'sekme', except: 'bilgiler')]
    public string $tab = 'bilgiler';

    public function mount(Company $company): void
    {
        $this->followCompanyFirm($company);

        $this->company = $company->load(['sector', 'firm', 'creator']);
    }

    /**
     * @return Collection<int, Workplace>
     */
    #[Computed]
    public function workplaces(): Collection
    {
        return Workplace::visibleTo(Auth::user())
            ->where('company_id', $this->company->id)
            ->with(['province', 'district'])
            ->orderBy('workplace_no')
            ->get();
    }

    public function delete(SaveCompany $saveCompany): void
    {
        $this->authorize('delete', $this->company);

        try {
            $saveCompany->delete($this->company);
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Şirket silindi.');
        $this->redirectRoute('companies.index', navigate: true);
    }
}; ?>

<div>
    <x-panel.page-header
        :crumbs="['Şirketler' => route('companies.index'), $company->short_name => null]"
        :back="route('companies.index')"
        :initials="\App\Support\Text::initials($company->short_name)"
        :title="$company->title"
        :subtitle="'No '.$company->company_no.' · VKN '.$company->tax_number.' · '.$company->sector->name">
        <x-slot:badge>
            <x-panel.badge color="navy">{{ $company->company_type->label() }}</x-panel.badge>
        </x-slot:badge>
        <x-slot:actions>
            @can('delete', $company)
                <flux:button icon="trash" wire:click="delete" wire:confirm="Şirket silinsin mi?" class="!text-st-red">Sil</flux:button>
            @endcan
            @can('update', $company)
                <flux:button variant="primary" icon="pencil-square" :href="route('companies.edit', $company)" wire:navigate>Düzenle</flux:button>
            @endcan
        </x-slot:actions>
    </x-panel.page-header>

    @if ($this->workplaces->isEmpty())
        <x-panel.alert variant="warning" title="Bu şirketin henüz işyeri yok" class="mb-4">
            Tek işyeri olsa bile (şubesi olmasa da) en az bir işyeri tanımlanması zorunludur.
            @can('create', [\App\Models\Workplace::class, $company])
                <x-slot:actions>
                    <flux:button size="sm" icon="plus" :href="route('workplaces.create', ['sirket' => $company->id])" wire:navigate>İşyeri Ekle</flux:button>
                </x-slot:actions>
            @endcan
        </x-panel.alert>
    @endif

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_290px]">
        <div class="min-w-0 rounded-2xl border border-line bg-white shadow-[0_1px_3px_rgba(16,40,72,0.04)]">
            <x-tabs :active="$tab" :tabs="['bilgiler' => 'Şirket Bilgileri', 'isyerleri' => 'İşyerleri']"
                :counts="['isyerleri' => $this->workplaces->count()]" class="px-[22px]" />

            @if ($tab === 'bilgiler')
                <div class="p-6">
                    <h2 class="mb-5 text-[17px] font-extrabold tracking-tight text-ink">Şirket Bilgileri</h2>
                    <x-panel.details :columns="3" :items="[
                        'Şirket Numarası' => $company->company_no,
                        'Şirket Tipi' => $company->company_type->label(),
                        'Sektör' => $company->sector->name,
                        'Kısa Ad' => $company->short_name,
                        'Vergi Numarası' => $company->tax_number,
                        'Vergi Dairesi' => $company->tax_office,
                        'MERSİS Numarası' => $company->mersis_no,
                        'Ticaret Sicil Numarası' => $company->trade_registry_no,
                        'KEP Adresi' => $company->kep_address,
                        'Web Adresi' => $company->website,
                        'Telefon' => $company->phone,
                        'Adres' => $company->address,
                    ]" />
                </div>
            @endif

            @if ($tab === 'isyerleri')
                <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-5 pb-4">
                    <p class="text-[13px] text-muted">Bu şirkete bağlı işyerleri.</p>
                    @can('create', [\App\Models\Workplace::class, $company])
                        <flux:button size="sm" icon="plus" :href="route('workplaces.create', ['sirket' => $company->id])" wire:navigate>İşyeri Ekle</flux:button>
                    @endcan
                </div>

                @if ($this->workplaces->isEmpty())
                    <x-panel.empty icon="map-pin" title="İşyeri yok">Bu şirkete henüz işyeri eklenmedi.</x-panel.empty>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px] border-collapse">
                            <thead>
                                <tr class="bg-head">
                                    <x-panel.th>Şube</x-panel.th>
                                    <x-panel.th>Tip / Tür</x-panel.th>
                                    <x-panel.th>İl / İlçe</x-panel.th>
                                    <x-panel.th>SGK Sicil No</x-panel.th>
                                    <x-panel.th>Kurulum</x-panel.th>
                                    <x-panel.th class="w-10"></x-panel.th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->workplaces as $workplace)
                                    @php $percent = $workplace->setupPercent(); @endphp
                                    <x-panel.tr :href="route('workplaces.show', $workplace)" wire:key="wp-{{ $workplace->id }}">
                                        <x-panel.td strong :sub="'No '.$workplace->workplace_no">
                                            <a href="{{ route('workplaces.show', $workplace) }}" wire:navigate class="hover:text-brand">{{ $workplace->branch_name }}</a>
                                        </x-panel.td>
                                        <x-panel.td :sub="$workplace->workplace_kind->label()">{{ $workplace->workplace_type->label() }}</x-panel.td>
                                        <x-panel.td :sub="$workplace->districtLabel()">{{ $workplace->provinceLabel() }}</x-panel.td>
                                        <x-panel.td>{{ $workplace->sgk_registry_no ?: '—' }}</x-panel.td>
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
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>

        <div class="flex flex-col gap-4 xl:sticky xl:top-[84px]">
            <div class="rounded-2xl border border-line bg-white p-[18px]">
                <div class="mb-3 text-[12px] font-bold tracking-[0.04em] text-muted">ÖZET</div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-canvas px-3.5 py-3">
                        <div class="text-[22px] leading-tight font-extrabold text-ink tabular-nums">{{ $this->workplaces->count() }}</div>
                        <div class="text-xs font-semibold text-muted">İşyeri</div>
                    </div>
                    <div class="rounded-xl bg-canvas px-3.5 py-3">
                        <div class="text-[22px] leading-tight font-extrabold text-ink tabular-nums">
                            %{{ $this->workplaces->isEmpty() ? 0 : (int) round($this->workplaces->avg(fn ($workplace) => $workplace->setupPercent())) }}
                        </div>
                        <div class="text-xs font-semibold text-muted">Ort. kurulum</div>
                    </div>
                </div>
            </div>

            <x-panel.record-meta :record="$company">
                <div class="flex justify-between gap-3"><span class="text-muted-2">Firma</span><span class="truncate font-bold text-ink">{{ $company->firm->name }}</span></div>
            </x-panel.record-meta>
        </div>
    </div>
</div>
