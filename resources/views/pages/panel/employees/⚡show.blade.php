<?php

use App\Actions\Employees\SaveEmployee;
use App\Enums\AuditEvent;
use App\Livewire\PanelComponent;
use App\Models\Employee;
use App\Support\Audit;
use App\Support\EmployeeOptions;
use App\Validation\EmployeeRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

new #[Title('Personel')] class extends PanelComponent {
    public Employee $employee;

    #[Url(as: 'sekme', except: 'kimlik')]
    public string $tab = 'kimlik';

    /**
     * Encrypted values revealed on this page view (authorized and audited); gone after navigating away.
     *
     * @var array<string, string>
     */
    public array $revealed = [];

    public function mount(Employee $employee): void
    {
        $this->authorize('view', $employee);

        if ($employee->firm_id !== Auth::user()->current_firm_id) {
            Auth::user()->switchFirm($employee->firm);
            unset($this->firm);
        }

        $this->employee = $employee->load(['company', 'workplace.company', 'upperUnit', 'unit', 'jobFamily', 'title', 'position', 'level', 'costGroup', 'creator']);

        if (! array_key_exists($this->tab, $this->tabLabels())) {
            $this->tab = 'kimlik';
        }
    }

    public function reveal(string $field): void
    {
        abort_unless(in_array($field, Employee::SECRET_FIELDS, true), 404);
        $this->authorize('update', $this->employee);

        Audit::log(AuditEvent::CredentialRevealed, (EmployeeRules::attributes()[$field] ?? $field)." görüntülendi: {$this->employee->registry_no} {$this->employee->fullName()}",
            $this->employee, ['field' => $field]);

        $this->revealed[$field] = (string) $this->employee->getAttribute($field);
    }

    public function hide(string $field): void
    {
        unset($this->revealed[$field]);
    }

    public function delete(SaveEmployee $saveEmployee): void
    {
        $this->authorize('delete', $this->employee);
        $saveEmployee->delete($this->employee);

        Flux::toast(variant: 'success', text: 'Personel silindi.');
        $this->redirectRoute('employees.index', navigate: true);
    }

    /**
     * @return array<string, string>
     */
    public function tabLabels(): array
    {
        return [
            'kimlik' => 'Kimlik & İletişim', 'kisisel' => 'Kişisel', 'istihdam' => 'İstihdam & Organizasyon', 'sgk' => 'SGK',
            'ucret' => 'Ücret & Bordro', 'banka' => 'Banka', 'engel' => 'Engellilik & Masraf', 'calisma' => 'Çalışma Düzeni',
        ];
    }

    /**
     * Tab => label => value; an encrypted field is given as ['secret' => column, 'masked' => text].
     *
     * @return array<string, array<string, mixed>>
     */
    public function sections(): array
    {
        $e = $this->employee;
        $date = fn ($value) => $value?->format('d.m.Y');
        $bool = fn (?bool $value) => $value === null ? null : ($value ? 'Evet' : 'Hayır');
        $money = fn ($value) => $value === null ? null : number_format((float) $value, 2, ',', '.').' '.($e->currency === 'TRY' ? '₺' : $e->currency);
        $percent = fn ($value) => $value === null ? null : '%'.rtrim(rtrim(str_replace('.', ',', (string) $value), '0'), ',');
        $iban = (string) $e->iban;

        return [
            'kimlik' => [
                'Sicil No' => $e->registry_no,
                'TC Kimlik No' => ['secret' => 'tckn', 'masked' => $e->maskedTckn()],
                'Adı' => $e->first_name, 'Soyadı' => $e->last_name, 'İkinci Soyadı' => $e->second_last_name,
                'Şirket E-posta' => $e->work_email, 'Kişisel E-posta' => $e->personal_email,
                'Cep Telefonu' => $e->mobile_phone, 'İş Telefonu' => $e->work_phone,
                'Oturulan Adres' => $e->address, 'İl / İlçe' => trim(($e->province ?? '').' / '.($e->district ?? ''), ' /') ?: null,
            ],
            'kisisel' => [
                'Doğum Tarihi' => $date($e->birth_date), 'Cinsiyet' => $e->gender, 'Medeni Hal' => $e->marital_status,
                'Eğitim Durumu' => $e->education, 'Mezuniyet Bölümü' => $e->graduation_field, 'Askerlik' => $e->military_status,
            ],
            'istihdam' => [
                'Firma' => $e->company?->title, 'SGK Firma' => $e->workplace?->company?->title, 'İş Yeri Şube' => $e->workplace?->branch_name,
                'Yaka' => $e->collar, 'İş Ailesi' => $e->jobFamily?->name, 'Birim' => $e->unit?->name, 'Üst Birim' => $e->upperUnit?->name,
                'Görev Tipi' => $e->duty_type, 'Unvan' => $e->title?->name, 'Pozisyon' => $e->position?->name, 'Seviye' => $e->level?->name,
                'İzin Yönetici Sicil No' => $e->leave_manager_registry_no, 'Fonksiyonel Yönetici Sicil No' => $e->functional_manager_registry_no,
                'İşe Giriş Tarihi' => $date($e->hire_date), 'Kıdeme Esas Tarihi' => $date($e->seniority_date), 'İzne Esas Tarihi' => $date($e->leave_base_date),
            ],
            'sgk' => [
                'SGK Meslek Kodu' => $e->occupation_code, 'Sigorta Kolu' => $e->insurance_branch, 'SGK Statü' => $e->sgk_status,
                'Çalışan Tipi' => $e->employment_type, 'Görev Kodu' => $e->duty_code, 'SGK Belge Türü' => $e->sgk_document_type,
            ],
            'ucret' => [
                'Ücret' => $money($e->wage), 'Ücret Tipi / Periyodu' => $e->wage_type.' · '.$e->wage_period, 'Para Birimi' => $e->currency,
                'Asgari Ücretli' => $bool($e->is_minimum_wage), 'Asgari Ücret Vergi İstisnasına Tabi' => $bool($e->minimum_wage_exemption),
                'Kümülatif Gelir Vergisi Matrahı' => $money($e->cumulative_tax_base),
                'Vergi İstisnası Başlangıç Ayı' => EmployeeOptions::MONTHS[$e->tax_exemption_start_month] ?? null,
                'Bir Önceki Dönem Devreden SGK Matrahı' => $money($e->previous_sgk_base_1),
                'İki Önceki Dönem Devreden SGK Matrahı' => $money($e->previous_sgk_base_2),
                'Otomatik BES Oranı' => $percent($e->bes_rate), 'Ar-Ge İndirim Oranı' => $e->rnd_rate,
            ],
            'banka' => [
                'Banka Adı' => $e->bank_name, 'Şube Adı' => $e->bank_branch,
                'IBAN' => ['secret' => 'iban', 'masked' => $iban !== '' ? substr($iban, 0, 4).' •••• •••• •••• '.substr($iban, -4) : '—'],
                'Hesap No' => ['secret' => 'account_no', 'masked' => '•••••'.substr((string) $e->account_no, -2)],
            ],
            'engel' => [
                'Engellilik Derecesi' => $e->disability_degree, 'Engelli Gelir Vergisi İndirimi' => $bool($e->disability_tax_relief),
                'Engellilik Bitiş Tarihi' => $date($e->disability_end_date), 'Masraf Grubu' => $e->costGroup?->name, 'Masraf Grubu Oranı' => $percent($e->cost_group_rate),
            ],
            'calisma' => [
                'Çalışma Modeli' => $e->work_model, 'Sözleşme Türü' => $e->contract_type, 'Vardiyalı Çalışma' => $bool($e->is_shift_worker),
                'Mesai' => substr((string) $e->shift_start, 0, 5).' – '.substr((string) $e->shift_end, 0, 5), 'Hafta Tatili' => $e->weekly_rest,
                'Kalan Yıllık İzin Hakkı' => $e->remaining_leave_days !== null ? rtrim(rtrim(str_replace('.', ',', (string) $e->remaining_leave_days), '0'), ',').' gün' : null,
            ],
        ];
    }
}; ?>

<div>
    @php
        $missing = $employee->missingRequiredFields();
        $percent = $employee->completionPercent();
        $statusColor = [\App\Models\Employee::ACTIVE => 'green', \App\Models\Employee::PASSIVE => 'gray', \App\Models\Employee::LEFT => 'red'][$employee->status] ?? 'gray';
        $labels = EmployeeRules::attributes();
    @endphp

    <x-panel.page-header
        :crumbs="['Personel' => route('employees.index'), $employee->fullName() => null]"
        :back="route('employees.index')"
        :initials="$employee->initials()"
        :title="$employee->fullName()"
        :subtitle="'Sicil '.$employee->registry_no.' · '.($employee->title?->name ?? '—').' · '.$employee->company?->short_name.' / '.$employee->workplace?->branch_name">
        <x-slot:badge>
            <x-panel.badge :color="$statusColor">{{ \App\Models\Employee::STATUSES[$employee->status] ?? $employee->status }}</x-panel.badge>
            @if ($missing === [])
                <x-panel.badge color="green">Eksiksiz</x-panel.badge>
            @else
                <x-panel.badge color="amber">{{ count($missing) }} eksik alan</x-panel.badge>
            @endif
        </x-slot:badge>
        <x-slot:actions>
            <x-panel.history-link :filter="['kayit' => 'personel:'.$employee->id]" />
            @can('delete', $employee)
                <flux:button icon="trash" wire:click="delete" wire:confirm="Personel kaydı silinsin mi?" class="!text-st-red">Sil</flux:button>
            @endcan
            @can('update', $employee)
                <flux:button variant="primary" icon="pencil-square" :href="route('employees.edit', [$employee, 'sekme' => $tab])" wire:navigate>Düzenle</flux:button>
            @endcan
        </x-slot:actions>
    </x-panel.page-header>

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_290px]">
        <div class="min-w-0 rounded-2xl border border-line bg-white shadow-[0_1px_3px_rgba(16,40,72,0.04)]">
            <x-tabs :active="$tab" :tabs="$this->tabLabels()" class="px-[22px]" />

            <div class="p-6">
                <h2 class="mb-5 text-[17px] font-extrabold tracking-tight text-ink">{{ $this->tabLabels()[$tab] }}</h2>

                <dl class="grid gap-x-[22px] gap-y-5 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($this->sections()[$tab] as $label => $value)
                        <div class="min-w-0">
                            <dt class="mb-1 text-[12px] font-bold tracking-wide text-muted-2">{{ $label }}</dt>
                            @if (is_array($value))
                                @php $field = $value['secret']; @endphp
                                <dd class="flex items-center gap-2">
                                    <span class="font-mono text-[14px] text-ink select-all">{{ $revealed[$field] ?? $value['masked'] }}</span>
                                    @can('update', $employee)
                                        @if (array_key_exists($field, $revealed))
                                            <flux:button size="sm" variant="ghost" icon="eye-slash" wire:click="hide('{{ $field }}')" aria-label="Gizle" />
                                        @else
                                            <flux:button size="sm" variant="ghost" icon="eye" wire:click="reveal('{{ $field }}')">Göster</flux:button>
                                        @endif
                                    @endcan
                                </dd>
                            @else
                                <dd @class(['text-[14px] font-semibold break-words', 'text-ink' => filled($value), 'text-faint' => blank($value)])>{{ filled($value) ? $value : '—' }}</dd>
                            @endif
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>

        <div class="flex flex-col gap-4 xl:sticky xl:top-[84px]">
            <div class="rounded-2xl border border-line bg-white p-[18px]">
                <div class="mb-2.5 text-[12px] font-bold tracking-[0.04em] text-muted">KAYIT TAMAMLANMA</div>
                <div class="mb-2.5 flex items-baseline gap-2">
                    <span class="text-[28px] font-extrabold tracking-tight text-ink tabular-nums">%{{ $percent }}</span>
                    <span class="text-[12.5px] font-semibold text-muted-2">{{ count($missing) }} alan boş</span>
                </div>
                <div class="mb-4 h-[7px] overflow-hidden rounded bg-line-3">
                    <div @class(['h-full rounded', 'bg-mint' => $percent === 100, 'bg-linear-to-r from-brand to-st-blue-dot' => $percent < 100]) style="width: {{ max($percent, 2) }}%"></div>
                </div>
                <div class="flex flex-col gap-0.5">
                    @foreach (\App\Models\Employee::REQUIRED_FIELDS as $group => $fields)
                        @continue($fields === [])
                        @php $groupMissing = count(array_intersect($fields, $missing)); @endphp
                        <button type="button" wire:click="$set('tab', '{{ $group }}')" @class([
                            'flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-start text-[13px] font-semibold transition hover:bg-row-hover',
                            'bg-[#F1F5FA] text-ink' => $tab === $group,
                            'text-ink-2' => $tab !== $group,
                        ])>
                            <span @class(['size-2 shrink-0 rounded-full', 'bg-mint' => $groupMissing === 0, 'bg-st-amber-dot' => $groupMissing > 0])></span>
                            <span class="flex-1">{{ $this->tabLabels()[$group] }}</span>
                            <span class="text-[11.5px] font-bold text-muted-2 tabular-nums">{{ count($fields) - $groupMissing }} / {{ count($fields) }}</span>
                        </button>
                    @endforeach
                </div>
                @if ($missing !== [])
                    <p class="mt-3 text-xs leading-relaxed text-muted-2">Eksik: {{ collect($missing)->map(fn ($field) => $labels[$field] ?? $field)->implode(', ') }}.</p>
                @endif
            </div>

            <x-panel.record-meta :record="$employee" />
        </div>
    </div>
</div>
