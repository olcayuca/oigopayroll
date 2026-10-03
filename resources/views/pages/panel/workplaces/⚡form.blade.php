<?php

use App\Actions\Workplaces\SaveWorkplace;
use App\Livewire\PanelComponent;
use App\Models\Company;
use App\Models\District;
use App\Models\LaborSector;
use App\Models\Province;
use App\Models\RiskClass;
use App\Models\Workplace;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/*
 * İşyeri form. Tabs follow the customer's setup file (KURULUM DOSYASI, Firma Bilgileri sheet):
 * Genel, Vergi, SGK, İŞKUR, Emniyet & BES, Adres hold its 36 required columns; İletişim and
 * Sendika / TİS hold the additional optional fields.
 */
new #[Title('İşyeri')] class extends PanelComponent {
    public ?Workplace $workplace = null;

    #[Url(as: 'sirket')]
    public string $companyId = '';

    /** Il / ilçe typed manually instead of chosen from the list. */
    public bool $manualLocation = false;

    #[Url(as: 'sekme', except: 'genel')]
    public string $tab = 'genel';

    public const TABS = [
        'genel' => 'Genel Bilgiler',
        'vergi' => 'Vergi',
        'sgk' => 'SGK',
        'iskur' => 'İŞKUR / TÜİK',
        'emniyet' => 'Emniyet & BES',
        'adres' => 'Adres',
        'iletisim' => 'İletişim',
        'sendika' => 'Sendika / TİS',
    ];

    /**
     * Which form tab each field lives on.
     */
    public const FIELD_TABS = [
        'genel' => ['companyId', 'workplace_type', 'branch_name', 'workplace_no', 'workplace_kind', 'nace_code', 'hazard_class',
            'labor_sector_id', 'title', 'registration_type', 'mersis_no', 'risk_class_id'],
        'vergi' => ['tax_number', 'tax_office', 'tax_office_user_code', 'dvd_username', 'dvd_password', 'dvd_passphrase', 'ebeyanname_password'],
        'sgk' => ['sgk_registry_no', 'sgk_directorate', 'sgk_officer_name', 'sgk_username', 'sgk_declaration_username', 'sgk_workplace_code',
            'sgk_workplace_password', 'sgk_system_password', 'ebildirge_officer_name', 'opening_date', 'closing_date', 'mahiyet_code', 'mahiyet_name'],
        'iskur' => ['iskur_user_name', 'iskur_user_code', 'iskur_password', 'iskur_registry_no', 'tuik_user_full_name', 'tuik_username', 'tuik_password'],
        'emniyet' => ['police_email', 'police_password', 'bes_company_name', 'bes_username', 'bes_password'],
        'adres' => ['address', 'province_id', 'province_name', 'district_id', 'district_name', 'neighborhood', 'street', 'outer_door_no',
            'inner_door_no', 'postal_code'],
        'iletisim' => ['phone', 'mobile_phone', 'email', 'kep_address', 'e_signature_officer'],
        'sendika' => ['has_union', 'union_name', 'cba_start_date', 'cba_end_date', 'cba_signed_date'],
    ];

    public const FIELDS = [
        'workplace_no', 'branch_name', 'workplace_type', 'workplace_kind', 'title', 'registration_type', 'tax_number',
        'tax_office', 'mersis_no', 'risk_class_id', 'hazard_class', 'labor_sector_id', 'nace_code',
        'province_id', 'province_name', 'district_id', 'district_name', 'neighborhood', 'street', 'outer_door_no',
        'inner_door_no', 'postal_code', 'address', 'phone', 'mobile_phone', 'email', 'kep_address', 'e_signature_officer',
        'sgk_registry_no', 'sgk_directorate', 'sgk_officer_name', 'sgk_workplace_code', 'ebildirge_officer_name',
        'opening_date', 'closing_date', 'mahiyet_code', 'mahiyet_name',
        'sgk_username', 'sgk_declaration_username', 'sgk_workplace_password', 'sgk_system_password',
        'iskur_user_name', 'iskur_user_code', 'iskur_password', 'iskur_registry_no',
        'tuik_user_full_name', 'tuik_username', 'tuik_password', 'tax_office_user_code',
        'dvd_username', 'dvd_password', 'dvd_passphrase', 'ebeyanname_password',
        'police_email', 'police_password', 'bes_company_name', 'bes_username', 'bes_password',
        'has_union', 'union_name', 'cba_start_date', 'cba_end_date', 'cba_signed_date',
    ];

    /** @var array<string, mixed> */
    public array $form = [];

    /**
     * Credentials already stored on the record (names only; values never reach the browser).
     *
     * @var list<string>
     */
    public array $storedSecrets = [];

    /**
     * Tabs holding fields with validation errors, in tab order.
     *
     * @return list<string>
     */
    public function invalidTabs(): array
    {
        $fields = array_map(fn ($key) => str_replace('form.', '', $key), $this->getErrorBag()->keys());

        return array_keys(array_filter(self::FIELD_TABS, fn ($tabFields) => array_intersect($tabFields, $fields) !== []));
    }

    public function mount(?Workplace $workplace = null): void
    {
        $this->form = array_fill_keys(self::FIELDS, '');
        $this->form['has_union'] = false;

        if (! array_key_exists($this->tab, $this::TABS)) {
            $this->tab = 'genel';
        }

        if ($workplace?->exists) {
            $this->followWorkplaceFirm($workplace);
            $this->authorize('update', $workplace);

            $this->workplace = $workplace;
            $this->companyId = (string) $workplace->company_id;
            $this->storedSecrets = array_values(array_filter(Workplace::SECRET_FIELDS, fn ($field) => filled($workplace->getAttributes()[$field] ?? null)));

            foreach (self::FIELDS as $field) {
                if (in_array($field, Workplace::SECRET_FIELDS, true)) {
                    continue; // never sent to the browser; blank = keep
                }

                $value = $workplace->getAttribute($field);
                $this->form[$field] = match (true) {
                    $value instanceof \BackedEnum => (string) $value->value,
                    $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                    is_bool($value) => $value,
                    $value === null => '',
                    default => (string) $value,
                };
            }

            $this->manualLocation = $workplace->province_id === null || $workplace->district_id === null;
            if ($this->manualLocation) {
                $this->form['province_name'] = (string) $workplace->provinceLabel();
                $this->form['district_name'] = (string) $workplace->districtLabel();
            }
        } elseif ($this->companyId !== '' && ! $this->companies->contains('id', (int) $this->companyId)) {
            $this->companyId = '';
        }

        if (! $this->workplace && $this->companies->isEmpty()) {
            abort(403, 'İşyeri ekleyebileceğiniz bir şirket bulunmuyor.');
        }
    }

    /**
     * Companies of the active firm the user may add workplaces to.
     *
     * @return Collection<int, Company>
     */
    #[Computed]
    public function companies(): Collection
    {
        return Company::visibleTo(Auth::user())
            ->where('firm_id', $this->firm->id)
            ->with('firm')
            ->orderBy('company_no')
            ->get()
            ->filter(fn (Company $company) => Auth::user()->can('create', [Workplace::class, $company]))
            ->values();
    }

    /**
     * @return Collection<int, Province>
     */
    #[Computed(persist: true)]
    public function provinces(): Collection
    {
        return Province::orderBy('name')->get();
    }

    /**
     * @return Collection<int, District>
     */
    #[Computed]
    public function districts(): Collection
    {
        return $this->form['province_id'] === ''
            ? new Collection
            : District::where('province_id', $this->form['province_id'])->orderBy('name')->get();
    }

    /**
     * @return Collection<int, RiskClass>
     */
    #[Computed]
    public function riskClasses(): Collection
    {
        return RiskClass::where('is_active', true)->orWhere('id', $this->workplace?->risk_class_id)->orderBy('name')->get();
    }

    /**
     * @return Collection<int, LaborSector>
     */
    #[Computed(persist: true)]
    public function laborSectors(): Collection
    {
        return LaborSector::orderBy('id')->get();
    }

    /**
     * Live completion of the setup-file fields: tab => [filled, total].
     *
     * @return array<string, array{0: int, 1: int}>
     */
    public function completion(): array
    {
        $filled = function (string $entry): bool {
            if ($entry === 'company_id') {
                return $this->companyId !== '';
            }

            foreach (explode('|', $entry) as $column) {
                if ($this->manualLocation && in_array($column, ['province_id', 'district_id'], true)) {
                    continue;
                }
                if (! $this->manualLocation && in_array($column, ['province_name', 'district_name'], true)) {
                    continue;
                }
                if (filled($this->form[$column] ?? null) || in_array($column, $this->storedSecrets, true)) {
                    return true;
                }
            }

            return false;
        };

        return array_map(
            fn (array $entries) => [count(array_filter($entries, $filled)), count($entries)],
            Workplace::SETUP_FIELDS,
        );
    }

    public function updatedFormProvinceId(): void
    {
        $this->form['district_id'] = '';
    }

    /**
     * Pre-fill the title and tax fields from the selected company (common for single-workplace companies).
     */
    public function copyFromCompany(): void
    {
        $company = $this->companies->firstWhere('id', (int) $this->companyId);

        if ($company) {
            $this->form['title'] = $company->title;
            $this->form['tax_number'] = $company->tax_number;
            $this->form['tax_office'] = $company->tax_office;
            $this->form['mersis_no'] = (string) $company->mersis_no;
        }
    }

    public function save(SaveWorkplace $saveWorkplace): void
    {
        $this->resetErrorBag();

        $data = collect($this->form)->map(fn ($value) => $value === '' ? null : $value)->all();
        $data['has_union'] = (bool) $this->form['has_union'];

        if ($this->manualLocation) {
            $data['province_id'] = $data['district_id'] = null;
        } else {
            $data['province_name'] = $data['district_name'] = null;
        }

        if (! $data['has_union']) {
            $data['union_name'] = $data['cba_start_date'] = $data['cba_end_date'] = $data['cba_signed_date'] = null;
        }

        try {
            if ($this->workplace) {
                $this->authorize('update', $this->workplace);
                $workplace = $saveWorkplace->update($this->workplace, $data);
            } else {
                $company = $this->companies->firstWhere('id', (int) $this->companyId);

                if (! $company) {
                    $this->addError('companyId', 'Şirket seçiniz.');
                    $this->tab = 'genel';

                    return;
                }

                $this->authorize('create', [Workplace::class, $company]);
                $workplace = $saveWorkplace->create($company, $data, Auth::user());
            }
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError(array_key_exists($field, $this->form) ? "form.{$field}" : $field, $messages[0]);
            }

            // Open the first tab with an error.
            $this->tab = $this->invalidTabs()[0] ?? $this->tab;
            Flux::toast(variant: 'danger', text: 'Formda düzeltilmesi gereken alanlar var.');

            return;
        }

        Flux::toast(variant: 'success', text: $this->workplace ? 'İşyeri güncellendi.' : 'İşyeri oluşturuldu.');

        $this->redirectRoute('workplaces.show', $workplace, navigate: true);
    }
}; ?>

<div>
    @php
        $tabIndex = array_search($tab, array_keys($this::TABS), true);
        $completion = $this->completion();
        $done = array_sum(array_column($completion, 0));
        $total = array_sum(array_column($completion, 1));
        $percent = $total ? (int) floor($done * 100 / $total) : 0;
        // Credential inputs are required on create; on edit a blank input keeps the stored value.
        $secretPlaceholder = fn (string $field, string $empty) => in_array($field, $storedSecrets, true) ? 'Kayıtlı — değiştirmek için yazın' : $empty;
    @endphp

    <x-panel.page-header
        :crumbs="$workplace
            ? ['İşyerleri' => route('workplaces.index'), $workplace->branch_name => route('workplaces.show', $workplace), 'Düzenle' => null]
            : ['İşyerleri' => route('workplaces.index'), 'Yeni kayıt' => null]"
        :back="$workplace ? route('workplaces.show', $workplace) : route('workplaces.index')"
        :initials="$workplace ? \App\Support\Text::initials($workplace->company->short_name) : null"
        :title="$workplace ? $workplace->branch_name : 'Yeni İşyeri'"
        :subtitle="$workplace ? $workplace->company->title.' · İşyeri No '.$workplace->workplace_no : 'Kurulum dosyasındaki tüm alanlar zorunludur (*). Şifreler şifreli saklanır.'">
        <x-slot:badge>
            @if (! $workplace)
                <x-panel.badge color="blue">Yeni kayıt</x-panel.badge>
            @elseif ($done === $total)
                <x-panel.badge color="green">Eksiksiz</x-panel.badge>
            @else
                <x-panel.badge color="amber">{{ $total - $done }} eksik alan</x-panel.badge>
            @endif
        </x-slot:badge>
        <x-slot:actions>
            <flux:button :href="$workplace ? route('workplaces.show', $workplace) : route('workplaces.index')" wire:navigate>Vazgeç</flux:button>
            <flux:button type="submit" form="workplace-form" variant="primary">{{ $workplace ? 'Değişiklikleri Kaydet' : 'İşyerini Oluştur' }}</flux:button>
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

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_290px]">
        <form id="workplace-form" wire:submit="save" class="min-w-0 rounded-2xl border border-line bg-white shadow-[0_1px_3px_rgba(16,40,72,0.04)]">
            <x-tabs :active="$tab" :invalid="$this->invalidTabs()" :tabs="$this::TABS" class="px-[22px]" />

            @if ($tab === 'genel')
                <section class="space-y-5 p-6">
                    <div>
                        <flux:heading class="!text-[17px]">Genel Bilgiler</flux:heading>
                        <flux:text class="mt-1">İşyerinin temel kimliği ve faaliyet sınıflandırması.</flux:text>
                    </div>

                    @if ($workplace)
                        <flux:text>Şirket: <strong>{{ $workplace->company->company_no }} · {{ $workplace->company->title }}</strong></flux:text>
                    @endif

                    <div class="grid gap-x-[22px] gap-y-5 sm:grid-cols-2">
                        <flux:select wire:model="form.workplace_type" label="İşyeri Tipi" required>
                            <flux:select.option value="">Seçiniz</flux:select.option>
                            @foreach (\App\Enums\WorkplaceType::cases() as $case)
                                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @unless ($workplace)
                            <div class="space-y-2">
                                <flux:select wire:model.live="companyId" label="Şirket" required>
                                    <flux:select.option value="">Seçiniz</flux:select.option>
                                    @foreach ($this->companies as $company)
                                        <flux:select.option value="{{ $company->id }}">{{ $company->company_no }} · {{ $company->title }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                @if ($companyId !== '')
                                    <button type="button" wire:click="copyFromCompany" class="text-xs font-bold text-brand hover:text-mint">Unvan ve vergi bilgilerini şirketten al</button>
                                @endif
                            </div>
                        @endunless
                        <flux:input wire:model="form.branch_name" label="İşyeri Şube Adı" required />
                        <flux:input wire:model="form.workplace_no" label="İşyeri Numarası" required description:trailing="Şirket içinde benzersiz." />
                        <flux:select wire:model="form.workplace_kind" label="İşyeri Türü" required>
                            <flux:select.option value="">Seçiniz</flux:select.option>
                            @foreach (\App\Enums\WorkplaceKind::cases() as $case)
                                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="form.nace_code" label="NACE Kodu" required placeholder="00.00.00" maxlength="8" />
                        <flux:select wire:model="form.hazard_class" label="Tehlike Sınıfı" required>
                            <flux:select.option value="">Seçiniz</flux:select.option>
                            @foreach (\App\Enums\HazardClass::cases() as $case)
                                <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="form.labor_sector_id" label="ÇSGB İşkolu" required description:trailing="Örn: 20 (Genel İşler)">
                            <flux:select.option value="">Seçiniz</flux:select.option>
                            @foreach ($this->laborSectors as $sector)
                                <flux:select.option value="{{ $sector->id }}">{{ $sector->id }} · {{ $sector->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="border-t border-line-3 pt-5">
                        <div class="mb-4 text-[12px] font-bold tracking-[0.04em] text-muted">EK BİLGİLER (İSTEĞE BAĞLI)</div>
                        <div class="grid gap-x-[22px] gap-y-5 sm:grid-cols-2">
                            <flux:input wire:model="form.title" label="Ünvan" description:trailing="Boş bırakılırsa şirketin unvanı kullanılır." />
                            <flux:select wire:model.live="form.registration_type" label="Tescil Tipi">
                                <flux:select.option value="">Seçiniz</flux:select.option>
                                @foreach (\App\Enums\RegistrationType::cases() as $case)
                                    <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:input wire:model="form.mersis_no" label="MERSİS Numarası" inputmode="numeric" maxlength="16" />
                            <flux:select wire:model="form.risk_class_id" label="Risk Sınıfı">
                                <flux:select.option value="">Seçiniz</flux:select.option>
                                @foreach ($this->riskClasses as $risk)
                                    <flux:select.option value="{{ $risk->id }}">{{ $risk->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>
                </section>
            @endif

            @if ($tab === 'vergi')
                <section class="space-y-5 p-6">
                    <div>
                        <flux:heading class="!text-[17px]">Vergi</flux:heading>
                        <flux:text class="mt-1">Vergi kimliği ve Dijital Vergi Dairesi / e-Beyanname erişimleri.</flux:text>
                    </div>
                    <div class="grid gap-x-[22px] gap-y-5 sm:grid-cols-2">
                        <flux:input wire:model="form.tax_number" label="Vergi Numarası" required inputmode="numeric" maxlength="11"
                            :description:trailing="$form['registration_type'] === 'gercek' ? '10 haneli VKN veya 11 haneli TCKN.' : '10 haneli VKN.'" />
                        <flux:input wire:model="form.tax_office" label="Vergi Dairesi" required />
                        <flux:input wire:model="form.tax_office_user_code" label="Vergi Dairesi Kullanıcı Kodu" required />
                        <flux:input wire:model="form.dvd_username" label="Dijital Vergi Dairesi Kullanıcı Adı" required autocomplete="off" />
                        <flux:input wire:model="form.dvd_password" label="Dijital Vergi Dairesi Şifre" type="password" viewable autocomplete="new-password" :required="! in_array('dvd_password', $storedSecrets, true)" :placeholder="$secretPlaceholder('dvd_password', '••••••••')" />
                        <flux:input wire:model="form.dvd_passphrase" label="Dijital Vergi Dairesi Parola" type="password" viewable autocomplete="new-password" :required="! in_array('dvd_passphrase', $storedSecrets, true)" :placeholder="$secretPlaceholder('dvd_passphrase', '••••••••')" />
                        <flux:input wire:model="form.ebeyanname_password" label="e-Beyanname Şifresi" type="password" viewable autocomplete="new-password" :required="! in_array('ebeyanname_password', $storedSecrets, true)" :placeholder="$secretPlaceholder('ebeyanname_password', '••••••••')" />
                    </div>
                </section>
            @endif

            @if ($tab === 'sgk')
                <section class="space-y-5 p-6">
                    <div>
                        <flux:heading class="!text-[17px]">SGK</flux:heading>
                        <flux:text class="mt-1">SGK işyeri sicili, bildirge ve sistem erişim bilgileri.</flux:text>
                    </div>
                    <x-panel.alert variant="info">
                        SGK işyeri sicil numarası 26 hanelidir. Bildirge kullanıcı bilgileri aylık prim hizmet bildirgesi gönderiminde kullanılır.
                        @if ($workplace) Şifre alanlarını değiştirmeyecekseniz boş bırakın. @endif
                    </x-panel.alert>
                    <div class="grid gap-x-[22px] gap-y-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <flux:input wire:model="form.sgk_registry_no" label="İşyeri SGK Sicil Numarası" required inputmode="numeric" maxlength="26" placeholder="26 haneli sicil numarası" />
                        </div>
                        <flux:input wire:model="form.sgk_directorate" label="Bağlı Bulunulan SGK Müdürlüğü" required />
                        <flux:input wire:model="form.sgk_officer_name" label="SGK İşyeri Yetkilisi Adı Soyadı" required />
                        <flux:input wire:model="form.sgk_username" label="SGK Kullanıcı Adı (TCKN)" inputmode="numeric" maxlength="11"
                            autocomplete="off" :required="! in_array('sgk_username', $storedSecrets, true)" :placeholder="$secretPlaceholder('sgk_username', '11 haneli')" />
                        <flux:input wire:model="form.sgk_declaration_username" label="SGK Bildirge Kullanıcı Adı (TCKN)" inputmode="numeric" maxlength="11"
                            autocomplete="off" :required="! in_array('sgk_declaration_username', $storedSecrets, true)" :placeholder="$secretPlaceholder('sgk_declaration_username', '11 haneli')" />
                        <flux:input wire:model="form.sgk_workplace_code" label="SGK İşyeri Kodu" required />
                        <flux:input wire:model="form.ebildirge_officer_name" label="e-Bildirge Yetkilisi Adı Soyadı" required />
                        <flux:input wire:model="form.sgk_workplace_password" label="SGK İşyeri Şifresi" type="password" viewable autocomplete="new-password" :required="! in_array('sgk_workplace_password', $storedSecrets, true)" :placeholder="$secretPlaceholder('sgk_workplace_password', '••••••••')" />
                        <flux:input wire:model="form.sgk_system_password" label="SGK Sistem Şifresi" type="password" viewable autocomplete="new-password" :required="! in_array('sgk_system_password', $storedSecrets, true)" :placeholder="$secretPlaceholder('sgk_system_password', '••••••••')" />
                    </div>

                    <div class="border-t border-line-3 pt-5">
                        <div class="mb-4 text-[12px] font-bold tracking-[0.04em] text-muted">EK BİLGİLER (İSTEĞE BAĞLI)</div>
                        <div class="grid gap-x-[22px] gap-y-5 sm:grid-cols-2">
                            <flux:input wire:model="form.opening_date" type="date" label="İşyeri Açılış Tarihi" />
                            <flux:input wire:model="form.closing_date" type="date" label="İşyeri Kapanış Tarihi" />
                            <flux:input wire:model="form.mahiyet_code" label="Mahiyet Kodu" />
                            <flux:input wire:model="form.mahiyet_name" label="Mahiyet Adı" />
                        </div>
                    </div>
                </section>
            @endif

            @if ($tab === 'iskur')
                <section class="space-y-5 p-6">
                    <div>
                        <flux:heading class="!text-[17px]">İŞKUR</flux:heading>
                        <flux:text class="mt-1">İŞKUR işveren sistemi erişim bilgileri.</flux:text>
                    </div>
                    <div class="grid gap-x-[22px] gap-y-5 sm:grid-cols-2">
                        <flux:input wire:model="form.iskur_user_name" label="İŞKUR Kullanıcı Adı Soyadı" required />
                        <flux:input wire:model="form.iskur_user_code" label="İŞKUR Kullanıcı Kodu (TCKN)" inputmode="numeric" maxlength="11"
                            autocomplete="off" :required="! in_array('iskur_user_code', $storedSecrets, true)" :placeholder="$secretPlaceholder('iskur_user_code', '11 haneli')" />
                        <flux:input wire:model="form.iskur_password" label="İŞKUR Şifresi" type="password" viewable autocomplete="new-password" :required="! in_array('iskur_password', $storedSecrets, true)" :placeholder="$secretPlaceholder('iskur_password', '••••••••')" />
                        <flux:input wire:model="form.iskur_registry_no" label="İŞKUR Sicil Numarası" required />
                    </div>

                    <div class="border-t border-line-3 pt-5">
                        <div class="mb-4 text-[12px] font-bold tracking-[0.04em] text-muted">TÜİK (İSTEĞE BAĞLI)</div>
                        <div class="grid gap-x-[22px] gap-y-5 sm:grid-cols-2">
                            <flux:input wire:model="form.tuik_user_full_name" label="TÜİK Kullanıcı Adı Soyadı" />
                            <flux:input wire:model="form.tuik_username" label="TÜİK Kullanıcı Adı" />
                            <flux:input wire:model="form.tuik_password" type="password" label="TÜİK Şifresi" viewable autocomplete="new-password"
                                :placeholder="$secretPlaceholder('tuik_password', '')" />
                        </div>
                    </div>
                </section>
            @endif

            @if ($tab === 'emniyet')
                <section class="space-y-5 p-6">
                    <div>
                        <flux:heading class="!text-[17px]">Emniyet & BES</flux:heading>
                        <flux:text class="mt-1">Karakol (kimlik) bildirimi ve otomatik BES firması erişimi.</flux:text>
                    </div>
                    <div class="grid gap-x-[22px] gap-y-5 sm:grid-cols-2">
                        <flux:input wire:model="form.police_email" type="email" label="Emniyet (Karakol) Bildirimi E-posta" required />
                        <flux:input wire:model="form.police_password" label="Emniyet (Karakol) Bildirimi Şifre" type="password" viewable autocomplete="new-password" :required="! in_array('police_password', $storedSecrets, true)" :placeholder="$secretPlaceholder('police_password', '••••••••')" />
                        <flux:input wire:model="form.bes_company_name" label="BES Firma Adı" required />
                        <flux:input wire:model="form.bes_username" label="BES Firma Kullanıcı Adı" required autocomplete="off" />
                        <flux:input wire:model="form.bes_password" label="BES Firma Şifre" type="password" viewable autocomplete="new-password" :required="! in_array('bes_password', $storedSecrets, true)" :placeholder="$secretPlaceholder('bes_password', '••••••••')" />
                    </div>
                </section>
            @endif

            @if ($tab === 'adres')
                <section class="space-y-5 p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <flux:heading class="!text-[17px]">Adres</flux:heading>
                            <flux:text class="mt-1">İşyerinin resmi adresi.</flux:text>
                        </div>
                        <flux:checkbox wire:model.live="manualLocation" label="İl / ilçe listede yok, elle gireceğim" />
                    </div>

                    <div class="grid gap-x-[22px] gap-y-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <flux:textarea wire:model="form.address" label="İşyeri Açık Adresi" rows="2" required />
                        </div>
                        @if ($manualLocation)
                            <flux:input wire:model="form.province_name" label="İl" required />
                            <flux:input wire:model="form.district_name" label="İlçe" required />
                        @else
                            <flux:select wire:model.live="form.province_id" label="İl" required>
                                <flux:select.option value="">Seçiniz</flux:select.option>
                                @foreach ($this->provinces as $province)
                                    <flux:select.option value="{{ $province->id }}">{{ $province->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:select wire:model="form.district_id" label="İlçe" required :disabled="$form['province_id'] === ''">
                                <flux:select.option value="">{{ $form['province_id'] === '' ? 'Önce il seçiniz' : 'Seçiniz' }}</flux:select.option>
                                @foreach ($this->districts as $district)
                                    <flux:select.option value="{{ $district->id }}">{{ $district->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            @error('form.district_name') <flux:text class="text-red-500 sm:col-span-2">{{ $message }}</flux:text> @enderror
                            @error('form.province_name') <flux:text class="text-red-500 sm:col-span-2">{{ $message }}</flux:text> @enderror
                        @endif
                    </div>

                    <div class="border-t border-line-3 pt-5">
                        <div class="mb-4 text-[12px] font-bold tracking-[0.04em] text-muted">ADRES AYRINTILARI (İSTEĞE BAĞLI)</div>
                        <div class="grid gap-x-[22px] gap-y-5 sm:grid-cols-2">
                            <flux:input wire:model="form.neighborhood" label="Mahalle" />
                            <flux:input wire:model="form.street" label="Cadde / Sokak / Bulvar" />
                            <flux:input wire:model="form.outer_door_no" label="Dış Kapı No" />
                            <flux:input wire:model="form.inner_door_no" label="İç Kapı No" />
                            <flux:input wire:model="form.postal_code" label="Posta Kodu" inputmode="numeric" maxlength="5" />
                        </div>
                    </div>
                </section>
            @endif

            @if ($tab === 'iletisim')
                <section class="space-y-5 p-6">
                    <div>
                        <flux:heading class="!text-[17px]">İletişim</flux:heading>
                        <flux:text class="mt-1">İsteğe bağlı iletişim bilgileri.</flux:text>
                    </div>
                    <div class="grid gap-x-[22px] gap-y-5 sm:grid-cols-2">
                        <flux:input wire:model="form.phone" label="Telefon" />
                        <flux:input wire:model="form.mobile_phone" label="Cep Telefonu" />
                        <flux:input wire:model="form.email" type="email" label="E-Posta" />
                        <flux:input wire:model="form.kep_address" label="KEP Adresi" />
                        <flux:input wire:model="form.e_signature_officer" label="E-İmza Yetkilisi" />
                    </div>
                </section>
            @endif

            @if ($tab === 'sendika')
                <section class="space-y-5 p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <flux:heading class="!text-[17px]">Sendika / Toplu İş Sözleşmesi</flux:heading>
                            <flux:text class="mt-1">İsteğe bağlı.</flux:text>
                        </div>
                        <flux:switch wire:model.live="form.has_union" label="Sendikalı işyeri" />
                    </div>
                    @if ($form['has_union'])
                        <div class="grid gap-x-[22px] gap-y-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <flux:input wire:model="form.union_name" label="Sendika Adı" required />
                            </div>
                            <flux:input wire:model="form.cba_start_date" type="date" label="TİS Başlangıç Tarihi" />
                            <flux:input wire:model="form.cba_end_date" type="date" label="TİS Bitiş Tarihi" />
                            <flux:input wire:model="form.cba_signed_date" type="date" label="TİS İmza Tarihi" />
                        </div>
                    @endif
                </section>
            @endif

            <x-panel.form-footer :tabs="$this::TABS" :index="$tabIndex" />
        </form>

        {{-- Completion rail --}}
        <div class="flex flex-col gap-4 xl:sticky xl:top-[84px]">
            <div class="rounded-2xl border border-line bg-white p-[18px]">
                <div class="mb-2.5 text-[12px] font-bold tracking-[0.04em] text-muted">KAYIT TAMAMLANMA</div>
                <div class="mb-2.5 flex items-baseline gap-2">
                    <span class="text-[28px] font-extrabold tracking-tight text-ink tabular-nums">%{{ $percent }}</span>
                    <span class="text-[12.5px] font-semibold text-muted-2">{{ $done }} / {{ $total }} zorunlu alan</span>
                </div>
                <div class="mb-4 h-[7px] overflow-hidden rounded bg-line-3">
                    <div @class(['h-full rounded transition-all', 'bg-mint' => $percent === 100, 'bg-linear-to-r from-brand to-st-blue-dot' => $percent < 100]) style="width: {{ max($percent, 2) }}%"></div>
                </div>
                <div class="flex flex-col gap-0.5">
                    @foreach ($completion as $group => [$groupDone, $groupTotal])
                        <button type="button" wire:click="$set('tab', '{{ $group }}')" @class([
                            'flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-start text-[13px] font-semibold transition hover:bg-row-hover',
                            'bg-[#F1F5FA] text-ink' => $tab === $group,
                            'text-ink-2' => $tab !== $group,
                        ])>
                            <span @class(['size-2 shrink-0 rounded-full', 'bg-mint' => $groupDone === $groupTotal, 'bg-st-amber-dot' => $groupDone < $groupTotal])></span>
                            <span class="flex-1">{{ $this::TABS[$group] }}</span>
                            <span class="text-[11.5px] font-bold text-muted-2 tabular-nums">{{ $groupDone }} / {{ $groupTotal }}</span>
                        </button>
                    @endforeach
                </div>
                <p class="mt-3 text-xs leading-relaxed text-muted-2">Alanlar müşteri kurulum dosyasıyla (Firma Bilgileri) birebir aynıdır.</p>
            </div>

            @if ($workplace)
                <x-panel.record-meta :record="$workplace" />
            @endif
        </div>
    </div>
</div>
