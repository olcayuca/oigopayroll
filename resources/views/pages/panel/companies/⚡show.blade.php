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

        $this->company = $company->load('sector');
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

<div class="flex w-full flex-1 flex-col gap-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('companies.index')" wire:navigate>Şirketler</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $company->short_name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $company->title }}</flux:heading>
            <flux:text class="mt-1">{{ $company->company_no }} · {{ $company->company_type->label() }} · {{ $company->sector->name }}</flux:text>
        </div>
        <div class="flex gap-2">
            @can('update', $company)
                <flux:button icon="pencil-square" :href="route('companies.edit', $company)" wire:navigate>Düzenle</flux:button>
            @endcan
            @can('delete', $company)
                <flux:button icon="trash" variant="ghost" wire:click="delete" wire:confirm="Şirket silinsin mi?" />
            @endcan
        </div>
    </div>

    @if ($this->workplaces->isEmpty())
        <flux:callout icon="exclamation-triangle" color="amber" heading="Bu şirketin henüz işyeri yok"
            text="Tek işyeri olsa bile (şubesi olmasa da) en az bir işyeri tanımlanması zorunludur." />
    @endif

    <x-tabs :active="$tab" :tabs="['bilgiler' => 'Şirket Bilgileri', 'isyerleri' => 'İşyerleri']"
        :counts="['isyerleri' => $this->workplaces->count()]" />

    @if ($tab === 'bilgiler')
    <dl class="grid gap-x-8 gap-y-4 rounded-xl border border-zinc-200 p-6 sm:grid-cols-2 lg:grid-cols-3 dark:border-zinc-700">
        @foreach ([
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
        ] as $label => $value)
            <div>
                <dt class="text-sm text-zinc-500">{{ $label }}</dt>
                <dd class="mt-0.5">{{ $value ?: '—' }}</dd>
            </div>
        @endforeach
    </dl>
    @endif

    @if ($tab === 'isyerleri')
    <section class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:text>Bu şirkete bağlı işyerleri.</flux:text>
            @can('create', [\App\Models\Workplace::class, $company])
                <flux:button size="sm" icon="plus" :href="route('workplaces.create', ['sirket' => $company->id])" wire:navigate>İşyeri Ekle</flux:button>
            @endcan
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>No</flux:table.column>
                <flux:table.column>Şube Adı</flux:table.column>
                <flux:table.column>Tip / Tür</flux:table.column>
                <flux:table.column>İl / İlçe</flux:table.column>
                <flux:table.column>SGK Sicil No</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->workplaces as $workplace)
                    <flux:table.row :key="$workplace->id">
                        <flux:table.cell>{{ $workplace->workplace_no }}</flux:table.cell>
                        <flux:table.cell variant="strong">
                            <a href="{{ route('workplaces.show', $workplace) }}" wire:navigate class="hover:underline">{{ $workplace->branch_name }}</a>
                        </flux:table.cell>
                        <flux:table.cell>{{ $workplace->workplace_type->label() }} · {{ $workplace->workplace_kind->label() }}</flux:table.cell>
                        <flux:table.cell>{{ $workplace->provinceLabel() }} / {{ $workplace->districtLabel() }}</flux:table.cell>
                        <flux:table.cell>{{ $workplace->sgk_registry_no ?: '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">İşyeri yok.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </section>
    @endif
</div>
