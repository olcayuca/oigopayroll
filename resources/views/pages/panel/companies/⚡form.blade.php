<?php

use App\Actions\Companies\SaveCompany;
use App\Livewire\PanelComponent;
use App\Models\Company;
use App\Models\Sector;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('Şirket')] class extends PanelComponent {
    public ?Company $company = null;

    public string $tab = 'sirket';

    public const FIELD_TABS = [
        'sirket' => ['company_no', 'title', 'short_name', 'company_type', 'sector_id', 'tax_number', 'tax_office'],
        'diger' => ['website', 'kep_address', 'trade_registry_no', 'mersis_no', 'phone', 'address'],
    ];

    /**
     * @return list<string>
     */
    public function invalidTabs(): array
    {
        $fields = array_map(fn ($key) => str_replace('form.', '', $key), $this->getErrorBag()->keys());

        return array_keys(array_filter(self::FIELD_TABS, fn ($tabFields) => array_intersect($tabFields, $fields) !== []));
    }

    /** @var array<string, string|null> */
    public array $form = [
        'company_no' => '', 'title' => '', 'short_name' => '', 'company_type' => '', 'sector_id' => '',
        'tax_number' => '', 'tax_office' => '', 'website' => '', 'kep_address' => '',
        'trade_registry_no' => '', 'mersis_no' => '', 'phone' => '', 'address' => '',
    ];

    public function mount(?Company $company = null): void
    {
        if ($company?->exists) {
            $this->followCompanyFirm($company);
            $this->authorize('update', $company);

            $this->company = $company;

            foreach (array_keys($this->form) as $field) {
                $value = $company->getAttribute($field);
                $this->form[$field] = $value instanceof \BackedEnum ? (string) $value->value : ($value === null ? '' : (string) $value);
            }
        } else {
            $this->authorize('create', [Company::class, $this->firm]);
        }
    }

    /**
     * Active sectors, plus the current one even if it has since been deactivated.
     *
     * @return Collection<int, Sector>
     */
    #[Computed]
    public function sectors(): Collection
    {
        return Sector::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $this->company?->sector_id))
            ->orderBy('name')
            ->get();
    }

    public function save(SaveCompany $saveCompany): void
    {
        $this->resetErrorBag();

        $data = collect($this->form)->map(fn ($value) => $value === '' ? null : $value)->all();

        try {
            if ($this->company) {
                $this->authorize('update', $this->company);
                $company = $saveCompany->update($this->company, $data);
            } else {
                $this->authorize('create', [Company::class, $this->firm]);
                $company = $saveCompany->create($this->firm, $data, Auth::user());
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Map action errors (company_no, ...) onto the form.* fields.
            foreach ($e->errors() as $field => $messages) {
                $this->addError(array_key_exists($field, $this->form) ? "form.{$field}" : $field, $messages[0]);
            }

            $this->tab = $this->invalidTabs()[0] ?? $this->tab;

            return;
        }

        Flux::toast(variant: 'success', text: $this->company ? 'Şirket güncellendi.' : 'Şirket oluşturuldu. Şimdi en az bir işyeri ekleyin.');

        $this->redirectRoute('companies.show', $company, navigate: true);
    }
}; ?>

<div>
    @php
        $formTabs = ['sirket' => 'Şirket Bilgileri', 'diger' => 'Diğer Bilgiler'];
        $tabIndex = array_search($tab, array_keys($formTabs), true);
    @endphp

    <x-panel.page-header
        :crumbs="$company
            ? ['Şirketler' => route('companies.index'), $company->short_name => route('companies.show', $company), 'Düzenle' => null]
            : ['Şirketler' => route('companies.index'), 'Yeni kayıt' => null]"
        :back="$company ? route('companies.show', $company) : route('companies.index')"
        :initials="$company ? \App\Support\Text::initials($company->short_name) : null"
        :title="$company ? $company->title : 'Yeni Şirket'"
        :subtitle="$company ? 'No '.$company->company_no.' · VKN '.$company->tax_number : 'Zorunlu alanlar tamamlanmadan şirket kaydı oluşturulamaz.'">
        <x-slot:badge>
            <x-panel.badge :color="$company ? 'navy' : 'blue'">{{ $company ? 'Düzenleniyor' : 'Yeni kayıt' }}</x-panel.badge>
        </x-slot:badge>
        <x-slot:actions>
            <flux:button :href="$company ? route('companies.show', $company) : route('companies.index')" wire:navigate>Vazgeç</flux:button>
            <flux:button type="submit" form="company-form" variant="primary">{{ $company ? 'Değişiklikleri Kaydet' : 'Şirketi Oluştur' }}</flux:button>
        </x-slot:actions>
    </x-panel.page-header>

    @error('firm')
        <x-panel.alert variant="danger" :title="$message" class="mb-4" />
    @enderror

    @if ($this->invalidTabs() !== [])
        <x-panel.alert variant="danger" class="mb-4" title="Formda düzeltilmesi gereken alanlar var.">
            Kırmızı işaretli sekmelerdeki alanları kontrol edin.
        </x-panel.alert>
    @endif

    <form id="company-form" wire:submit="save" class="max-w-4xl rounded-2xl border border-line bg-white shadow-[0_1px_3px_rgba(16,40,72,0.04)]">
        <x-tabs :active="$tab" :invalid="$this->invalidTabs()" :tabs="$formTabs" class="px-[22px]" />

        @if ($tab === 'sirket')
        <section class="space-y-5 p-6">
            <flux:heading class="!text-[17px]">Şirket Bilgileri</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.company_no" label="Şirket Numarası" required description:trailing="Sistem genelinde benzersiz olmalıdır." />
                <flux:input wire:model="form.short_name" label="Şirket Kısa Adı" required />
                <div class="sm:col-span-2">
                    <flux:input wire:model="form.title" label="Şirket Adı / Unvanı" required />
                </div>
                <flux:select wire:model.live="form.company_type" label="Şirket Tipi" required>
                    <flux:select.option value="">Seçiniz</flux:select.option>
                    @foreach (\App\Enums\CompanyType::cases() as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.sector_id" label="Şirket Sektörü" required>
                    <flux:select.option value="">Seçiniz</flux:select.option>
                    @foreach ($this->sectors as $sector)
                        <flux:select.option value="{{ $sector->id }}">{{ $sector->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.tax_number" label="Vergi Numarası" required inputmode="numeric" maxlength="11"
                    :description:trailing="$form['company_type'] === 'sahis' ? 'Şahıs firmasında 11 haneli T.C. kimlik numarası da girilebilir.' : '10 haneli vergi kimlik numarası.'" />
                <flux:input wire:model="form.tax_office" label="Vergi Dairesi" required />
            </div>
        </section>
        @endif

        @if ($tab === 'diger')
        <section class="space-y-5 p-6">
            <div>
                <flux:heading class="!text-[17px]">Diğer Bilgiler</flux:heading>
                <flux:text class="mt-1">Bu bilgiler zorunlu değildir.</flux:text>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.website" label="Web Adresi" placeholder="https://" />
                <flux:input wire:model="form.kep_address" label="KEP Adresi" />
                <flux:input wire:model="form.trade_registry_no" label="Ticaret Sicil Numarası" />
                <flux:input wire:model="form.mersis_no" label="MERSİS Numarası" inputmode="numeric" maxlength="16" description:trailing="16 hane." />
                <flux:input wire:model="form.phone" label="Telefon" />
                <div class="sm:col-span-2">
                    <flux:textarea wire:model="form.address" label="Adres" rows="2" />
                </div>
            </div>
        </section>
        @endif

        <x-panel.form-footer :tabs="$formTabs" :index="$tabIndex" />
    </form>
</div>
