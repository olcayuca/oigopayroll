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

new #[Title('İşyeri')] class extends PanelComponent {
    public ?Workplace $workplace = null;

    #[Url(as: 'sirket')]
    public string $companyId = '';

    /** Il / ilçe typed manually instead of chosen from the list. */
    public bool $manualLocation = false;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(?Workplace $workplace = null): void
    {
        $this->form = array_fill_keys(self::FIELDS, '');
        $this->form['has_union'] = false;

        if ($workplace?->exists) {
            $this->followWorkplaceFirm($workplace);
            $this->authorize('update', $workplace);

            $this->workplace = $workplace;
            $this->companyId = (string) $workplace->company_id;

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

    public const FIELDS = [
        'workplace_no', 'branch_name', 'workplace_type', 'workplace_kind', 'title', 'registration_type', 'tax_number',
        'tax_office', 'mersis_no', 'risk_class_id', 'hazard_class', 'labor_sector_id',
        'province_id', 'province_name', 'district_id', 'district_name', 'neighborhood', 'street', 'outer_door_no',
        'inner_door_no', 'postal_code', 'address', 'phone', 'mobile_phone', 'email', 'kep_address', 'e_signature_officer',
        'sgk_registry_no', 'sgk_directorate', 'sgk_officer_name', 'sgk_workplace_code', 'ebildirge_officer_name',
        'opening_date', 'closing_date', 'mahiyet_code', 'mahiyet_name',
        'sgk_declaration_username', 'sgk_workplace_password', 'sgk_system_password',
        'iskur_user_name', 'iskur_user_code', 'iskur_password', 'iskur_registry_no',
        'tuik_user_full_name', 'tuik_username', 'tuik_password', 'tax_office_user_code', 'ebeyanname_password',
        'has_union', 'union_name', 'cba_start_date', 'cba_end_date', 'cba_signed_date',
    ];

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

    public function updatedFormProvinceId(): void
    {
        $this->form['district_id'] = '';
    }

    /**
     * Pre-fill the tax fields from the selected company (common for single-workplace companies).
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

                    return;
                }

                $this->authorize('create', [Workplace::class, $company]);
                $workplace = $saveWorkplace->create($company, $data, Auth::user());
            }
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError(array_key_exists($field, $this->form) ? "form.{$field}" : $field, $messages[0]);
            }

            Flux::toast(variant: 'danger', text: 'Formda düzeltilmesi gereken alanlar var.');

            return;
        }

        Flux::toast(variant: 'success', text: $this->workplace ? 'İşyeri güncellendi.' : 'İşyeri oluşturuldu.');

        $this->redirectRoute('workplaces.show', $workplace, navigate: true);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('workplaces.index')" wire:navigate>İşyerleri</flux:breadcrumbs.item>
        @if ($workplace)
            <flux:breadcrumbs.item :href="route('workplaces.show', $workplace)" wire:navigate>{{ $workplace->branch_name }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Düzenle</flux:breadcrumbs.item>
        @else
            <flux:breadcrumbs.item>Yeni İşyeri</flux:breadcrumbs.item>
        @endif
    </flux:breadcrumbs>

    <flux:heading size="xl">{{ $workplace ? 'İşyerini Düzenle' : 'Yeni İşyeri' }}</flux:heading>

    @error('firm')
        <flux:callout icon="exclamation-triangle" color="red" :heading="$message" />
    @enderror

    <form wire:submit="save" class="max-w-5xl space-y-8">
        {{-- Temel bilgiler --}}
        <section class="space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">İşyeri Temel Bilgileri</flux:heading>

            @if ($workplace)
                <flux:text>Şirket: <strong>{{ $workplace->company->company_no }} · {{ $workplace->company->title }}</strong></flux:text>
            @else
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-72 flex-1">
                        <flux:select wire:model.live="companyId" label="Şirket" required>
                            <flux:select.option value="">Seçiniz</flux:select.option>
                            @foreach ($this->companies as $company)
                                <flux:select.option value="{{ $company->id }}">{{ $company->company_no }} · {{ $company->title }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    @if ($companyId !== '')
                        <flux:button icon="document-duplicate" wire:click="copyFromCompany">Unvan ve vergi bilgilerini şirketten al</flux:button>
                    @endif
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:select wire:model="form.workplace_type" label="İşyeri Tipi" required>
                    <flux:select.option value="">Seçiniz</flux:select.option>
                    @foreach (\App\Enums\WorkplaceType::cases() as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.branch_name" label="İşyeri Şube Adı" required />
                <flux:input wire:model="form.workplace_no" label="İşyeri Numarası" required description="Şirket içinde benzersiz." />
                <flux:select wire:model="form.workplace_kind" label="İşyeri Türü" required>
                    <flux:select.option value="">Seçiniz</flux:select.option>
                    @foreach (\App\Enums\WorkplaceKind::cases() as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="sm:col-span-2">
                    <flux:input wire:model="form.title" label="Ünvan" required />
                </div>
                <flux:select wire:model.live="form.registration_type" label="Tescil Tipi">
                    <flux:select.option value="">Seçiniz</flux:select.option>
                    @foreach (\App\Enums\RegistrationType::cases() as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="form.tax_number" label="Vergi Numarası" required inputmode="numeric" maxlength="11"
                    :description="$form['registration_type'] === 'gercek' ? '10 haneli VKN veya 11 haneli TCKN.' : '10 haneli VKN.'" />
                <flux:input wire:model="form.tax_office" label="Vergi Dairesi" required />
                <flux:input wire:model="form.mersis_no" label="MERSİS Numarası" inputmode="numeric" maxlength="16" />
                <flux:select wire:model="form.risk_class_id" label="Risk Sınıfı">
                    <flux:select.option value="">Seçiniz</flux:select.option>
                    @foreach ($this->riskClasses as $risk)
                        <flux:select.option value="{{ $risk->id }}">{{ $risk->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="form.hazard_class" label="Tehlike Sınıfı" required>
                    <flux:select.option value="">Seçiniz</flux:select.option>
                    @foreach (\App\Enums\HazardClass::cases() as $case)
                        <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="sm:col-span-2 lg:col-span-3">
                    <flux:select wire:model="form.labor_sector_id" label="Çalışma ve Sosyal Güvenlik Bakanlığı İş Kolu">
                        <flux:select.option value="">Seçiniz</flux:select.option>
                        @foreach ($this->laborSectors as $sector)
                            <flux:select.option value="{{ $sector->id }}">{{ $sector->id }} · {{ $sector->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>
        </section>

        {{-- Adres ve iletişim --}}
        <section class="space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <flux:heading size="lg">Adres ve İletişim</flux:heading>
                <flux:checkbox wire:model.live="manualLocation" label="İl / ilçe listede yok, elle gireceğim" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
                <flux:input wire:model="form.neighborhood" label="Mahalle" />
                <flux:input wire:model="form.street" label="Cadde / Sokak / Bulvar" />
                <flux:input wire:model="form.outer_door_no" label="Dış Kapı No" />
                <flux:input wire:model="form.inner_door_no" label="İç Kapı No" />
                <flux:input wire:model="form.postal_code" label="Posta Kodu" inputmode="numeric" maxlength="5" />
                <div class="sm:col-span-2 lg:col-span-3">
                    <flux:textarea wire:model="form.address" label="İşyeri Açık Adresi" rows="2" required />
                </div>
                <flux:input wire:model="form.phone" label="Telefon" />
                <flux:input wire:model="form.mobile_phone" label="Cep Telefonu" />
                <flux:input wire:model="form.email" type="email" label="E-Posta" />
                <flux:input wire:model="form.kep_address" label="KEP Adresi" />
                <flux:input wire:model="form.e_signature_officer" label="E-İmza Yetkilisi" />
            </div>
        </section>

        {{-- SGK --}}
        <section class="space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div>
                <flux:heading size="lg">SGK Bilgileri</flux:heading>
                @if ($workplace)
                    <flux:text class="mt-1">Şifre alanları güvenlik nedeniyle gösterilmez; değiştirmeyecekseniz boş bırakın.</flux:text>
                @endif
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:input wire:model="form.sgk_officer_name" label="SGK İşyeri Yetkilisi Adı Soyadı" required />
                <flux:input wire:model="form.sgk_workplace_code" label="SGK İşyeri Kodu" required />
                <flux:input wire:model="form.ebildirge_officer_name" label="e-Bildirge Yetkilisi Adı Soyadı" required />
                <flux:input wire:model="form.opening_date" type="date" label="İşyeri Açılış Tarihi" required />
                <flux:input wire:model="form.closing_date" type="date" label="İşyeri Kapanış Tarihi" />
                <flux:input wire:model="form.sgk_registry_no" label="İşyeri SGK Sicil Numarası" inputmode="numeric" maxlength="26" description="26 hane." />
                <flux:input wire:model="form.sgk_directorate" label="Bağlı Bulunulan SGK Müdürlüğü" />
                <flux:input wire:model="form.mahiyet_code" label="Mahiyet Kodu" />
                <flux:input wire:model="form.mahiyet_name" label="Mahiyet Adı" />
                <flux:input wire:model="form.sgk_declaration_username" label="SGK Bildirge Kullanıcı Adı (TCKN)" :required="! $workplace" inputmode="numeric" maxlength="11" autocomplete="off" />
                <flux:input wire:model="form.sgk_workplace_password" type="password" label="SGK İşyeri Şifresi" :required="! $workplace" viewable autocomplete="new-password" />
                <flux:input wire:model="form.sgk_system_password" type="password" label="SGK Sistem Şifresi" :required="! $workplace" viewable autocomplete="new-password" />
            </div>
        </section>

        {{-- İŞKUR / TÜİK / Vergi --}}
        <section class="space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg">İŞKUR / TÜİK / Vergi Bilgileri</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:input wire:model="form.iskur_user_name" label="İŞKUR Kullanıcı Adı Soyadı" />
                <flux:input wire:model="form.iskur_user_code" label="İŞKUR Kullanıcı Kodu (TCKN)" inputmode="numeric" maxlength="11" autocomplete="off" />
                <flux:input wire:model="form.iskur_password" type="password" label="İŞKUR Şifresi" viewable autocomplete="new-password" />
                <flux:input wire:model="form.iskur_registry_no" label="İŞKUR Sicil Numarası" />
                <flux:input wire:model="form.tuik_user_full_name" label="TÜİK Kullanıcı Adı Soyadı" />
                <flux:input wire:model="form.tuik_username" label="TÜİK Kullanıcı Adı" />
                <flux:input wire:model="form.tuik_password" type="password" label="TÜİK Şifresi" viewable autocomplete="new-password" />
                <flux:input wire:model="form.tax_office_user_code" label="Vergi Dairesi Kullanıcı Kodu" />
                <flux:input wire:model="form.ebeyanname_password" type="password" label="e-Beyanname Şifresi" viewable autocomplete="new-password" />
            </div>
        </section>

        {{-- Sendika --}}
        <section class="space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">Sendika / Toplu İş Sözleşmesi</flux:heading>
                <flux:switch wire:model.live="form.has_union" label="Sendikalı işyeri" />
            </div>
            @if ($form['has_union'])
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="sm:col-span-2 lg:col-span-4">
                        <flux:input wire:model="form.union_name" label="Sendika Adı" required />
                    </div>
                    <flux:input wire:model="form.cba_start_date" type="date" label="TİS Başlangıç Tarihi" />
                    <flux:input wire:model="form.cba_end_date" type="date" label="TİS Bitiş Tarihi" />
                    <flux:input wire:model="form.cba_signed_date" type="date" label="TİS İmza Tarihi" />
                </div>
            @endif
        </section>

        <div class="flex justify-end gap-2">
            <flux:button :href="$workplace ? route('workplaces.show', $workplace) : route('workplaces.index')" wire:navigate>Vazgeç</flux:button>
            <flux:button type="submit" variant="primary">{{ $workplace ? 'Kaydet' : 'İşyerini Oluştur' }}</flux:button>
        </div>
    </form>
</div>
