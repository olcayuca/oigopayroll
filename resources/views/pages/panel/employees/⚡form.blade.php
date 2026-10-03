<?php

use App\Actions\Employees\SaveEmployee;
use App\Enums\CodeList;
use App\Enums\DefinitionType;
use App\Livewire\PanelComponent;
use App\Models\Company;
use App\Models\Definition;
use App\Models\Employee;
use App\Models\PayrollCode;
use App\Models\Workplace;
use App\Support\EmployeeOptions;
use App\Validation\EmployeeRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/*
 * Personel form: the 8 tabs of the design, fields of the customer setup file (Personel Bilgileri).
 * Field kinds: text, email, date, time, number, textarea, secret, choice (EmployeeOptions list),
 * definition (Tanımlar), bool (Evet / Hayır), company, workplace, month, document, bank, occupation.
 */
new #[Title('Personel')] class extends PanelComponent {
    public ?Employee $employee = null;

    #[Url(as: 'sekme', except: 'kimlik')]
    public string $tab = 'kimlik';

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<string> */
    public array $storedSecrets = [];

    public const TABS = [
        'kimlik' => ['Kimlik & İletişim', 'Kimlik ve iletişim bilgileri.'],
        'kisisel' => ['Kişisel', 'Demografik bilgiler, eğitim ve askerlik.'],
        'istihdam' => ['İstihdam & Organizasyon', 'Bağlı olduğu firma, şube, birim ve görev bilgileri.'],
        'sgk' => ['SGK', 'Sigortalılık ve bildirge parametreleri.'],
        'ucret' => ['Ücret & Bordro', 'Ücret tanımı ve bordro hesaplamasını etkileyen devreden matrahlar.'],
        'banka' => ['Banka', 'Maaş ödemesinin yapılacağı hesap.'],
        'engel' => ['Engellilik & Masraf', 'Vergi indirimleri ve masraf merkezi dağılımı.'],
        'calisma' => ['Çalışma Düzeni', 'Sözleşme, mesai ve izin hakları.'],
    ];

    /**
     * Tab => field => [kind, options / definition type, extra].
     *
     * @return array<string, array<string, array{0: string, 1?: mixed}>>
     */
    public function layout(): array
    {
        return [
            'kimlik' => [
                'registry_no' => ['text'], 'tckn' => ['secret', 'tckn'],
                'first_name' => ['text'], 'last_name' => ['text'], 'second_last_name' => ['text'],
                'work_email' => ['email'], 'personal_email' => ['email'],
                'mobile_phone' => ['text', 'tel'], 'work_phone' => ['text', 'tel'],
                'address' => ['textarea'], 'province' => ['text'], 'district' => ['text'],
            ],
            'kisisel' => [
                'birth_date' => ['date'], 'gender' => ['choice', EmployeeOptions::GENDER],
                'marital_status' => ['choice', EmployeeOptions::MARITAL_STATUS], 'education' => ['choice', EmployeeOptions::EDUCATION],
                'graduation_field' => ['text'], 'military_status' => ['choice', EmployeeOptions::MILITARY],
            ],
            'istihdam' => [
                'company_id' => ['company'], 'workplace_id' => ['workplace'],
                'collar' => ['choice', EmployeeOptions::COLLAR], 'job_family_id' => ['definition', DefinitionType::JobFamily],
                'unit_id' => ['definition', DefinitionType::Unit], 'upper_unit_id' => ['definition', DefinitionType::UpperUnit],
                'duty_type' => ['choice', EmployeeOptions::DUTY_TYPE], 'title_id' => ['definition', DefinitionType::Title],
                'position_id' => ['definition', DefinitionType::Position], 'level_id' => ['definition', DefinitionType::Level],
                'leave_manager_registry_no' => ['text'], 'functional_manager_registry_no' => ['text'],
                'hire_date' => ['date'], 'seniority_date' => ['date'], 'leave_base_date' => ['date'],
            ],
            'sgk' => [
                'occupation_code' => ['occupation'], 'insurance_branch' => ['choice', EmployeeOptions::INSURANCE_BRANCH],
                'sgk_status' => ['choice', EmployeeOptions::SGK_STATUS], 'employment_type' => ['choice', EmployeeOptions::EMPLOYMENT_TYPE],
                'duty_code' => ['choice', EmployeeOptions::DUTY_CODE], 'sgk_document_type' => ['document'],
            ],
            'ucret' => [
                'wage_period' => ['choice', EmployeeOptions::WAGE_PERIOD], 'currency' => ['choice', EmployeeOptions::CURRENCY],
                'wage_type' => ['choice', EmployeeOptions::WAGE_TYPE], 'wage' => ['number', '0,00'],
                'is_minimum_wage' => ['bool'], 'minimum_wage_exemption' => ['bool'],
                'cumulative_tax_base' => ['number', '0,00', 'Dönem başı itibarıyla devreden tutar.'], 'tax_exemption_start_month' => ['month'],
                'previous_sgk_base_1' => ['number', '0,00'], 'previous_sgk_base_2' => ['number', '0,00'],
                'bes_rate' => ['number', '%3'], 'rnd_rate' => ['choice', EmployeeOptions::RND_RATE],
            ],
            'banka' => [
                'bank_name' => ['bank'], 'bank_branch' => ['text'], 'iban' => ['secret', 'iban'], 'account_no' => ['secret', 'account'],
            ],
            'engel' => [
                'disability_degree' => ['choice', EmployeeOptions::DISABILITY_DEGREE], 'disability_tax_relief' => ['bool'],
                'disability_end_date' => ['date'], 'cost_group_id' => ['definition', DefinitionType::CostGroup],
                'cost_group_rate' => ['number', '%100'],
            ],
            'calisma' => [
                'work_model' => ['choice', EmployeeOptions::WORK_MODEL], 'contract_type' => ['choice', EmployeeOptions::CONTRACT_TYPE],
                'is_shift_worker' => ['bool'], 'weekly_rest' => ['choice', EmployeeOptions::WEEKLY_REST],
                'shift_start' => ['time'], 'shift_end' => ['time'], 'remaining_leave_days' => ['number', 'Gün'],
            ],
        ];
    }

    public function mount(?Employee $employee = null): void
    {
        $fields = array_keys(array_merge(...array_values($this->layout())));
        $this->form = array_fill_keys($fields, '');

        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'kimlik';
        }

        if ($employee?->exists) {
            $this->authorize('update', $employee);
            if ($employee->firm_id !== Auth::user()->current_firm_id) {
                Auth::user()->switchFirm($employee->firm);
                unset($this->firm);
            }

            $this->employee = $employee;
            $this->storedSecrets = array_values(array_filter(Employee::SECRET_FIELDS, fn ($field) => filled($employee->getAttributes()[$field] ?? null)));

            foreach ($fields as $field) {
                if (in_array($field, Employee::SECRET_FIELDS, true)) {
                    continue; // never sent to the browser; blank = keep
                }

                $value = $employee->getAttribute($field);
                $this->form[$field] = match (true) {
                    $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
                    is_bool($value) => $value,
                    $value === null => '',
                    in_array($field, ['shift_start', 'shift_end'], true) => substr((string) $value, 0, 5),
                    in_array($field, ['wage', 'cumulative_tax_base', 'previous_sgk_base_1', 'previous_sgk_base_2'], true) => number_format((float) $value, 2, ',', '.'),
                    in_array($field, ['bes_rate', 'cost_group_rate', 'remaining_leave_days'], true) => rtrim(rtrim(str_replace('.', ',', (string) $value), '0'), ','),
                    default => (string) $value,
                };
            }
        } else {
            $this->authorize('create', [Employee::class, $this->firm]);

            // Sensible defaults of a new record (the same as the setup file's most common values).
            $this->form = array_merge($this->form, [
                'currency' => 'TRY', 'wage_period' => 'Aylık', 'insurance_branch' => 'Tüm Sigorta Kolları (Zorunlu)',
                'sgk_status' => 'Normal', 'duty_code' => 'İşçi', 'sgk_document_type' => '01', 'tax_exemption_start_month' => (string) now()->month,
                'cumulative_tax_base' => '0', 'previous_sgk_base_1' => '0', 'previous_sgk_base_2' => '0', 'bes_rate' => '3',
                'is_minimum_wage' => false, 'minimum_wage_exemption' => true, 'is_shift_worker' => false,
                'shift_start' => '09:00', 'shift_end' => '18:00', 'contract_type' => 'Tam Zamanlı', 'employment_type' => 'Belirsiz Süreli',
                'company_id' => (string) ($this->companies->count() === 1 ? $this->companies->first()->id : ''),
                'workplace_id' => (string) ($this->workplaces->count() === 1 ? $this->workplaces->first()->id : ''),
            ]);
        }
    }

    /**
     * @return Collection<int, Company>
     */
    #[Computed]
    public function companies(): Collection
    {
        return Company::query()->where('firm_id', $this->firm->id)->orderBy('company_no')->get(['id', 'company_no', 'short_name', 'title']);
    }

    /**
     * Workplaces the user may place personnel in (plus the record's current one).
     *
     * @return Collection<int, Workplace>
     */
    #[Computed]
    public function workplaces(): Collection
    {
        $ids = app(\App\Policies\EmployeePolicy::class)->creatableWorkplaces(Auth::user(), $this->firm);

        return Workplace::query()->whereIn('id', [...$ids, $this->employee?->workplace_id ?? 0])
            ->with('company.firm')->orderBy('company_id')->orderBy('workplace_no')->get();
    }

    /**
     * @return array<string, Collection<int, Definition>>
     */
    #[Computed]
    public function definitions(): array
    {
        $current = array_filter(array_map(fn (DefinitionType $type) => $this->employee?->getAttribute($type->employeeColumn()), DefinitionType::cases()));
        $all = Definition::query()->where('firm_id', $this->firm->id)
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $current))
            ->with('parent:id,name')->orderBy('name')->get();

        return collect(DefinitionType::cases())->mapWithKeys(fn (DefinitionType $type) => [$type->value => $all->where('type', $type)->values()])->all();
    }

    /**
     * @return array<string, string>
     */
    #[Computed(persist: true)]
    public function documentTypes(): array
    {
        return PayrollCode::query()->where('list', CodeList::DocumentTypes)->orderBy('code')->pluck('name', 'code')->all();
    }

    /**
     * @return list<string>
     */
    #[Computed(persist: true)]
    public function banks(): array
    {
        return array_values(array_map('strval', PayrollCode::query()->where('list', CodeList::Banks)->orderBy('name')->pluck('name')->all()));
    }

    /**
     * @return array<string, string>
     */
    #[Computed(persist: true)]
    public function occupations(): array
    {
        return PayrollCode::query()->where('list', CodeList::Occupations)->orderBy('code')->limit(3000)->pluck('name', 'code')->all();
    }

    /**
     * Tabs holding fields with validation errors.
     *
     * @return list<string>
     */
    public function invalidTabs(): array
    {
        $fields = array_map(fn ($key) => str_replace('form.', '', $key), $this->getErrorBag()->keys());

        return array_keys(array_filter($this->layout(), fn ($tabFields) => array_intersect(array_keys($tabFields), $fields) !== []));
    }

    /**
     * Live completion of the required fields: tab => [filled, total].
     *
     * @return array<string, array{0: int, 1: int}>
     */
    public function completion(): array
    {
        $filled = fn (string $field) => ($this->form[$field] ?? '') !== '' && ($this->form[$field] ?? null) !== null
            || in_array($field, $this->storedSecrets, true);

        return array_filter(array_map(
            fn (array $fields) => [count(array_filter($fields, $filled)), count($fields)],
            Employee::REQUIRED_FIELDS,
        ), fn (array $pair) => $pair[1] > 0);
    }

    public function save(SaveEmployee $saveEmployee): void
    {
        $this->resetErrorBag();
        $data = collect($this->form)->map(fn ($value) => $value === '' ? null : $value)->all();

        try {
            if ($this->employee) {
                $this->authorize('update', $this->employee);
                $workplace = Workplace::find($data['workplace_id']);
                if ($workplace && $workplace->id !== $this->employee->workplace_id) {
                    $this->authorize('createIn', [Employee::class, $workplace]);
                }
                $employee = $saveEmployee->update($this->employee, $data);
            } else {
                $workplace = $this->workplaces->firstWhere('id', (int) $data['workplace_id']);
                if ($workplace) {
                    $this->authorize('createIn', [Employee::class, $workplace]);
                }
                $employee = $saveEmployee->create($this->firm, $data, Auth::user());
            }
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError(array_key_exists($field, $this->form) ? "form.{$field}" : $field, $messages[0]);
            }

            $this->tab = $this->invalidTabs()[0] ?? $this->tab;
            Flux::toast(variant: 'danger', text: 'Formda düzeltilmesi gereken alanlar var.');

            return;
        }

        Flux::toast(variant: 'success', text: $this->employee ? 'Personel güncellendi.' : 'Personel oluşturuldu.');
        $this->redirectRoute('employees.show', $employee, navigate: true);
    }
}; ?>

<div>
    @php
        $tabKeys = array_keys($this::TABS);
        $tabIndex = array_search($tab, $tabKeys, true);
        $tabLabels = array_map(fn ($tab) => $tab[0], $this::TABS);
        $completion = $this->completion();
        $done = array_sum(array_column($completion, 0));
        $total = array_sum(array_column($completion, 1));
        $percent = $total ? (int) floor($done * 100 / $total) : 0;
        $required = array_merge(...array_values(\App\Models\Employee::REQUIRED_FIELDS));
        $labels = EmployeeRules::attributes();
        $labels['bank_branch'] = 'Şube Adı';
        $labels['workplace_id'] = 'SGK Firma / İş Yeri Şube';
        $notes = [
            'ucret' => 'Yıl içinde başka bir işverenden gelen personel için önceki işyerindeki kümülatif gelir vergisi matrahını girin; aksi halde vergi dilimi hatalı hesaplanır.',
            'istihdam' => 'Birim, üst birim, unvan, pozisyon ve seviye Tanımlar ekranından gelir. Listede yoksa önce Tanımlar\'a ekleyin.',
        ];
    @endphp

    <x-panel.page-header
        :crumbs="$employee
            ? ['Personel' => route('employees.index'), $employee->fullName() => route('employees.show', $employee), 'Düzenle' => null]
            : ['Personel' => route('employees.index'), 'Yeni kayıt' => null]"
        :back="$employee ? route('employees.show', $employee) : route('employees.index')"
        :initials="$employee?->initials()"
        :title="$employee ? $employee->fullName() : 'Yeni Personel'"
        :subtitle="$employee ? 'Sicil '.$employee->registry_no.' · '.$employee->company?->short_name.' · '.$employee->workplace?->branch_name : 'Kırmızı yıldızlı alanlar kurulum dosyasında zorunludur. TCKN, IBAN ve hesap no şifreli saklanır.'">
        <x-slot:badge>
            @if (! $employee)
                <x-panel.badge color="blue">Yeni kayıt</x-panel.badge>
            @elseif ($done === $total)
                <x-panel.badge color="green">Eksiksiz</x-panel.badge>
            @else
                <x-panel.badge color="amber">{{ $total - $done }} eksik alan</x-panel.badge>
            @endif
        </x-slot:badge>
        <x-slot:actions>
            <flux:button :href="$employee ? route('employees.show', $employee) : route('employees.index')" wire:navigate>Vazgeç</flux:button>
            <flux:button type="submit" form="employee-form" variant="primary">{{ $employee ? 'Değişiklikleri Kaydet' : 'Kaydet' }}</flux:button>
        </x-slot:actions>
    </x-panel.page-header>

    <x-panel.setup-approved-notice :firm="$this->firm" />

    @if ($this->invalidTabs() !== [])
        <x-panel.alert variant="danger" class="mb-4" title="Formda düzeltilmesi gereken alanlar var.">
            Kırmızı işaretli sekmelerdeki alanları kontrol edin.
        </x-panel.alert>
    @endif

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_290px]">
        <form id="employee-form" wire:submit="save" class="min-w-0 rounded-2xl border border-line bg-white shadow-[0_1px_3px_rgba(16,40,72,0.04)]">
            <x-tabs :active="$tab" :invalid="$this->invalidTabs()" :tabs="$tabLabels" class="px-[22px]" />

            <section class="space-y-5 p-6" wire:key="tab-{{ $tab }}">
                <div>
                    <flux:heading class="!text-[17px]">{{ $this::TABS[$tab][0] }}</flux:heading>
                    <flux:text class="mt-1">{{ $this::TABS[$tab][1] }}</flux:text>
                </div>

                @if (isset($notes[$tab]))
                    <x-panel.alert variant="info">{{ $notes[$tab] }}</x-panel.alert>
                @endif

                <div class="grid gap-x-[22px] gap-y-5 sm:grid-cols-2">
                    @foreach ($this->layout()[$tab] as $field => $config)
                        @php
                            $kind = $config[0];
                            $label = $labels[$field] ?? $field;
                            $isRequired = in_array($field, $required, true);
                            $stored = in_array($field, $storedSecrets, true);
                        @endphp
                        <div @class(['sm:col-span-2' => $kind === 'textarea']) wire:key="f-{{ $field }}">
                            @switch($kind)
                                @case('textarea')
                                    <flux:textarea wire:model="form.{{ $field }}" :label="$label" rows="2" :required="$isRequired" />
                                    @break
                                @case('secret')
                                    <flux:input wire:model="form.{{ $field }}" :label="$label" autocomplete="off" :required="$isRequired && ! $stored"
                                        :maxlength="$config[1] === 'tckn' ? 11 : ($config[1] === 'iban' ? 34 : 50)"
                                        :inputmode="$config[1] === 'tckn' ? 'numeric' : 'text'"
                                        :placeholder="$stored ? 'Kayıtlı — değiştirmek için yazın' : ($config[1] === 'iban' ? 'TR00 0000 0000 0000 0000 0000 00' : ($config[1] === 'tckn' ? '11 haneli' : ''))"
                                        :description:trailing="$stored ? 'Şifreli saklanır; boş bırakılırsa değişmez.' : 'Şifreli saklanır.'" />
                                    @break
                                @case('choice')
                                    <flux:select wire:model="form.{{ $field }}" :label="$label" :required="$isRequired">
                                        <flux:select.option value="">Seçiniz…</flux:select.option>
                                        @foreach ($config[1] as $option)
                                            <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    @break
                                @case('definition')
                                    @php $definitionType = $config[1]; $options = $this->definitions[$definitionType->value]; @endphp
                                    <flux:select wire:model="form.{{ $field }}" :label="$label" :required="$isRequired"
                                        :description:trailing="$options->isEmpty() ? 'Henüz tanımlanmadı — Tanımlar › '.$definitionType->label().'.' : null">
                                        <flux:select.option value="">Seçiniz…</flux:select.option>
                                        @foreach ($options as $definition)
                                            <flux:select.option :value="$definition->id">{{ $definition->name }}{{ $definition->parent ? ' · '.$definition->parent->name : '' }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    @break
                                @case('company')
                                    <flux:select wire:model="form.company_id" :label="$label" required>
                                        <flux:select.option value="">Seçiniz…</flux:select.option>
                                        @foreach ($this->companies as $company)
                                            <flux:select.option :value="$company->id">{{ $company->company_no }} · {{ $company->short_name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    @break
                                @case('workplace')
                                    <flux:select wire:model="form.workplace_id" :label="$label" required description:trailing="SGK bildirimi bu işyerinin sicilinden yapılır.">
                                        <flux:select.option value="">Seçiniz…</flux:select.option>
                                        @foreach ($this->workplaces as $workplace)
                                            <flux:select.option :value="$workplace->id">{{ $workplace->company->short_name }} · {{ $workplace->branch_name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    @break
                                @case('month')
                                    <flux:select wire:model="form.{{ $field }}" :label="$label" required>
                                        <flux:select.option value="">Seçiniz…</flux:select.option>
                                        @foreach (\App\Support\EmployeeOptions::MONTHS as $number => $month)
                                            <flux:select.option :value="$number">{{ $month }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    @break
                                @case('document')
                                    <flux:select wire:model="form.{{ $field }}" :label="$label" required>
                                        <flux:select.option value="">Seçiniz…</flux:select.option>
                                        @foreach ($this->documentTypes as $code => $name)
                                            <flux:select.option :value="$code">{{ $code }} · {{ \Illuminate\Support\Str::limit($name, 70) }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    @break
                                @case('bank')
                                    <flux:input wire:model="form.{{ $field }}" :label="$label" required list="bank-list" autocomplete="off" />
                                    <datalist id="bank-list">
                                        @foreach ($this->banks as $bank)
                                            <option value="{{ $bank }}"></option>
                                        @endforeach
                                    </datalist>
                                    @break
                                @case('occupation')
                                    <flux:input wire:model="form.{{ $field }}" :label="$label" required list="occupation-list" placeholder="0000.00" autocomplete="off"
                                        description:trailing="ISCO-08 tabanlı SGK meslek kodu, ör. 2411.12" />
                                    <datalist id="occupation-list">
                                        @foreach ($this->occupations as $code => $name)
                                            <option value="{{ $code }}">{{ $name }}</option>
                                        @endforeach
                                    </datalist>
                                    @break
                                @case('bool')
                                    <div data-flux-field class="grid gap-2">
                                        <span data-flux-label class="text-sm font-medium">{{ $label }}@if ($isRequired)<span class="text-[#D24B47]"> *</span>@endif</span>
                                        <div class="flex w-fit gap-1 rounded-[10px] bg-seg p-[3px]" role="group" aria-label="{{ $label }}">
                                            @foreach ([true => 'Evet', false => 'Hayır'] as $value => $text)
                                                @php $selected = $form[$field] === (bool) $value; @endphp
                                                <button type="button" wire:click="$set('form.{{ $field }}', {{ $value ? 'true' : 'false' }})" aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                                    @class(['h-9 rounded-lg px-4 text-[13px] font-bold transition', 'bg-white text-ink shadow-[0_1px_2px_rgba(16,40,72,0.08)]' => $selected, 'text-muted hover:text-ink' => ! $selected])>{{ $text }}</button>
                                            @endforeach
                                        </div>
                                        @error('form.'.$field) <span class="text-xs font-semibold text-[#C13E3A]">{{ $message }}</span> @enderror
                                    </div>
                                    @break
                                @case('date')
                                    <flux:input wire:model="form.{{ $field }}" type="date" :label="$label" :required="$isRequired" />
                                    @break
                                @case('time')
                                    <flux:input wire:model="form.{{ $field }}" type="time" :label="$label" :required="$isRequired" />
                                    @break
                                @case('number')
                                    <flux:input wire:model="form.{{ $field }}" :label="$label" :required="$isRequired" inputmode="decimal"
                                        :placeholder="$config[1] ?? ''" :description:trailing="$config[2] ?? null" />
                                    @break
                                @case('email')
                                    <flux:input wire:model="form.{{ $field }}" type="email" :label="$label" :required="$isRequired" />
                                    @break
                                @default
                                    <flux:input wire:model="form.{{ $field }}" :label="$label" :required="$isRequired"
                                        :type="($config[1] ?? null) === 'tel' ? 'tel' : 'text'" />
                            @endswitch
                        </div>
                    @endforeach
                </div>
            </section>

            <x-panel.form-footer :tabs="$tabLabels" :index="$tabIndex" />
        </form>

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
                    @foreach ($this::TABS as $key => [$tabLabel])
                        @php [$groupDone, $groupTotal] = $completion[$key] ?? [0, 0]; @endphp
                        <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                            'flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-start text-[13px] font-semibold transition hover:bg-row-hover',
                            'bg-[#F1F5FA] text-ink' => $tab === $key,
                            'text-ink-2' => $tab !== $key,
                        ])>
                            <span @class(['size-2 shrink-0 rounded-full', 'bg-mint' => $groupDone === $groupTotal, 'bg-st-amber-dot' => $groupDone < $groupTotal])></span>
                            <span class="flex-1">{{ $tabLabel }}</span>
                            <span class="text-[11.5px] font-bold text-muted-2 tabular-nums">{{ $groupTotal ? $groupDone.' / '.$groupTotal : 'İsteğe bağlı' }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            @if ($employee)
                <x-panel.record-meta :record="$employee" />
            @endif
        </div>
    </div>
</div>
