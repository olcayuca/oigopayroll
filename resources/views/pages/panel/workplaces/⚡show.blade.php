<?php

use App\Actions\Workplaces\RevealWorkplaceCredential;
use App\Actions\Workplaces\SaveWorkplace;
use App\Livewire\PanelComponent;
use App\Models\Workplace;
use App\Validation\WorkplaceRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/*
 * İşyeri detail. Tabs follow the customer's setup file; credentials are shown masked in their own
 * tab and revealed one by one (authorized + logged).
 */
new #[Title('İşyeri')] class extends PanelComponent {
    public Workplace $workplace;

    #[Url(as: 'sekme', except: 'genel')]
    public string $tab = 'genel';

    /**
     * Credentials revealed on this page view (each reveal is authorized and logged).
     * They live only in this page's component state and are gone after navigating away.
     *
     * @var array<string, string|null>
     */
    public array $revealed = [];

    public function mount(Workplace $workplace): void
    {
        $this->followWorkplaceFirm($workplace);

        $this->workplace = $workplace->load(['company', 'province', 'district', 'riskClass', 'laborSector', 'creator']);

        if (! array_key_exists($this->tab, $this->tabLabels())) {
            $this->tab = 'genel';
        }
    }

    public function reveal(string $field, RevealWorkplaceCredential $reveal): void
    {
        $this->revealed[$field] = $reveal->handle($this->workplace, $field, Auth::user(), request()->ip()) ?? '—';
    }

    public function hide(string $field): void
    {
        unset($this->revealed[$field]);
    }

    public function delete(SaveWorkplace $saveWorkplace): void
    {
        $this->authorize('delete', $this->workplace);

        $company = $this->workplace->company;
        $saveWorkplace->delete($this->workplace);

        Flux::toast(variant: 'success', text: 'İşyeri silindi.');
        $this->redirectRoute('companies.show', $company, navigate: true);
    }

    /**
     * @return array<string, string>
     */
    public function tabLabels(): array
    {
        return [
            'genel' => 'Genel Bilgiler',
            'vergi' => 'Vergi',
            'sgk' => 'SGK',
            'iskur' => 'İŞKUR / TÜİK',
            'emniyet' => 'Emniyet & BES',
            'adres' => 'Adres',
            'iletisim' => 'İletişim',
            'sendika' => 'Sendika / TİS',
        ];
    }

    /**
     * Tab => label => value; a credential is given as ['secret' => column].
     *
     * @return array<string, array<string, string|array{secret: string}|null>>
     */
    public function sections(): array
    {
        $w = $this->workplace;
        $date = fn ($value) => $value?->format('d.m.Y');
        $secret = fn (string $field) => ['secret' => $field];

        return [
            'genel' => [
                'İşyeri Tipi' => $w->workplace_type->label(),
                'Şirket' => $w->company->title,
                'İşyeri Şube Adı' => $w->branch_name,
                'İşyeri Numarası' => $w->workplace_no,
                'İşyeri Türü' => $w->workplace_kind->label(),
                'NACE Kodu' => $w->nace_code,
                'Tehlike Sınıfı' => $w->hazard_class->label(),
                'ÇSGB İşkolu' => $w->laborSector ? $w->laborSector->id.' · '.$w->laborSector->name : null,
                'Ünvan' => $w->title,
                'Tescil Tipi' => $w->registration_type?->label(),
                'MERSİS Numarası' => $w->mersis_no,
                'Risk Sınıfı' => $w->riskClass?->name,
            ],
            'vergi' => [
                'Vergi Numarası' => $w->tax_number,
                'Vergi Dairesi' => $w->tax_office,
                'Vergi Dairesi Kullanıcı Kodu' => $w->tax_office_user_code,
                'Dijital Vergi Dairesi Kullanıcı Adı' => $w->dvd_username,
                'Dijital Vergi Dairesi Şifre' => $secret('dvd_password'),
                'Dijital Vergi Dairesi Parola' => $secret('dvd_passphrase'),
                'e-Beyanname Şifresi' => $secret('ebeyanname_password'),
            ],
            'sgk' => [
                'İşyeri SGK Sicil Numarası' => $w->sgk_registry_no,
                'Bağlı Bulunulan SGK Müdürlüğü' => $w->sgk_directorate,
                'SGK İşyeri Yetkilisi' => $w->sgk_officer_name,
                'SGK Kullanıcı Adı (TCKN)' => $secret('sgk_username'),
                'SGK Bildirge Kullanıcı Adı (TCKN)' => $secret('sgk_declaration_username'),
                'SGK İşyeri Kodu' => $w->sgk_workplace_code,
                'SGK İşyeri Şifresi' => $secret('sgk_workplace_password'),
                'SGK Sistem Şifresi' => $secret('sgk_system_password'),
                'e-Bildirge Yetkilisi' => $w->ebildirge_officer_name,
                'Açılış Tarihi' => $date($w->opening_date),
                'Kapanış Tarihi' => $date($w->closing_date),
                'Mahiyet' => trim(($w->mahiyet_code ?? '').' '.($w->mahiyet_name ?? '')) ?: null,
            ],
            'iskur' => [
                'İŞKUR Kullanıcı Adı Soyadı' => $w->iskur_user_name,
                'İŞKUR Kullanıcı Kodu (TCKN)' => $secret('iskur_user_code'),
                'İŞKUR Şifresi' => $secret('iskur_password'),
                'İŞKUR Sicil Numarası' => $w->iskur_registry_no,
                'TÜİK Kullanıcı Adı Soyadı' => $w->tuik_user_full_name,
                'TÜİK Kullanıcı Adı' => $w->tuik_username,
                'TÜİK Şifresi' => $secret('tuik_password'),
            ],
            'emniyet' => [
                'Emniyet (Karakol) Bildirimi E-posta' => $w->police_email,
                'Emniyet (Karakol) Bildirimi Şifre' => $secret('police_password'),
                'BES Firma Adı' => $w->bes_company_name,
                'BES Firma Kullanıcı Adı' => $w->bes_username,
                'BES Firma Şifre' => $secret('bes_password'),
            ],
            'adres' => [
                'Açık Adres' => $w->address,
                'İl' => $w->provinceLabel(),
                'İlçe' => $w->districtLabel(),
                'Mahalle' => $w->neighborhood,
                'Cadde / Sokak' => $w->street,
                'Dış / İç Kapı' => trim(($w->outer_door_no ?? '').' / '.($w->inner_door_no ?? ''), ' /') ?: null,
                'Posta Kodu' => $w->postal_code,
            ],
            'iletisim' => [
                'Telefon' => $w->phone,
                'Cep Telefonu' => $w->mobile_phone,
                'E-Posta' => $w->email,
                'KEP Adresi' => $w->kep_address,
                'E-İmza Yetkilisi' => $w->e_signature_officer,
            ],
            'sendika' => $w->has_union ? [
                'Sendika' => $w->union_name,
                'TİS Başlangıç' => $date($w->cba_start_date),
                'TİS Bitiş' => $date($w->cba_end_date),
                'TİS İmza' => $date($w->cba_signed_date),
            ] : ['Sendikalı İşyeri' => 'Hayır'],
        ];
    }
}; ?>

<div>
    @php
        $missing = $workplace->missingSetupFields();
        $setupTotal = count(array_merge(...array_values(\App\Models\Workplace::SETUP_FIELDS)));
        $percent = $workplace->setupPercent();
        $items = $this->sections()[$tab] ?? [];
        $hasSecrets = collect($items)->contains(fn ($value) => is_array($value));
        $missingLabel = fn (string $entry) => WorkplaceRules::attributes()[explode('|', $entry)[0]] ?? match ($entry) {
            'company_id' => 'Şirket',
            default => $entry,
        };
    @endphp

    <x-panel.page-header
        :crumbs="['İşyerleri' => route('workplaces.index'), $workplace->company->short_name => route('companies.show', $workplace->company), $workplace->branch_name => null]"
        :back="route('companies.show', $workplace->company)"
        :initials="\App\Support\Text::initials($workplace->company->short_name)"
        :title="$workplace->branch_name"
        :subtitle="$workplace->company->title.' · İşyeri No '.$workplace->workplace_no.' · VKN '.$workplace->tax_number.' · '.$workplace->provinceLabel()">
        <x-slot:badge>
            <x-panel.badge :color="$workplace->workplace_type === \App\Enums\WorkplaceType::Headquarters ? 'navy' : 'gray'">{{ $workplace->workplace_type->label() }}</x-panel.badge>
            @if ($missing === [])
                <x-panel.badge color="green">Eksiksiz</x-panel.badge>
            @else
                <x-panel.badge color="amber">{{ count($missing) }} eksik alan</x-panel.badge>
            @endif
        </x-slot:badge>
        <x-slot:actions>
            @can('delete', $workplace)
                <flux:button icon="trash" wire:click="delete" wire:confirm="İşyeri silinsin mi?" class="!text-st-red">Sil</flux:button>
            @endcan
            @can('update', $workplace)
                <flux:button variant="primary" icon="pencil-square" :href="route('workplaces.edit', [$workplace, 'sekme' => $tab])" wire:navigate>Düzenle</flux:button>
            @endcan
        </x-slot:actions>
    </x-panel.page-header>

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_290px]">
        <div class="min-w-0 rounded-2xl border border-line bg-white shadow-[0_1px_3px_rgba(16,40,72,0.04)]">
            <x-tabs :active="$tab" :tabs="$this->tabLabels()" class="px-[22px]" />

            <div class="p-6">
                <h2 class="mb-5 text-[17px] font-extrabold tracking-tight text-ink">{{ $this->tabLabels()[$tab] ?? '' }}</h2>

                @if ($hasSecrets)
                    <x-panel.alert variant="info" icon="lock-closed" class="mb-5">Şifreler şifreli saklanır; her görüntüleme kayıt altına alınır.</x-panel.alert>
                @endif

                <dl class="grid gap-x-[22px] gap-y-5 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($items as $label => $value)
                        <div class="min-w-0">
                            <dt class="mb-1 text-[12px] font-bold tracking-wide text-muted-2">{{ $label }}</dt>
                            @if (is_array($value))
                                @php $field = $value['secret']; $stored = filled($workplace->getAttributes()[$field] ?? null); @endphp
                                <dd class="flex items-center gap-2">
                                    @if (! $stored)
                                        <span class="text-[14px] font-semibold text-faint">—</span>
                                    @elseif (array_key_exists($field, $revealed))
                                        <span class="font-mono text-[14px] text-ink select-all">{{ $revealed[$field] }}</span>
                                        <flux:button size="sm" variant="ghost" icon="eye-slash" wire:click="hide('{{ $field }}')" aria-label="Gizle" />
                                    @else
                                        <span class="font-mono text-[14px] tracking-wider text-ink">••••••••</span>
                                        @can('viewCredentials', $workplace)
                                            <flux:button size="sm" variant="ghost" icon="eye" wire:click="reveal('{{ $field }}')">Göster</flux:button>
                                        @endcan
                                    @endif
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
                <div class="mb-2.5 text-[12px] font-bold tracking-[0.04em] text-muted">KURULUM TAMAMLANMA</div>
                <div class="mb-2.5 flex items-baseline gap-2">
                    <span class="text-[28px] font-extrabold tracking-tight text-ink tabular-nums">%{{ $percent }}</span>
                    <span class="text-[12.5px] font-semibold text-muted-2">{{ $setupTotal - count($missing) }} / {{ $setupTotal }} zorunlu alan</span>
                </div>
                <div class="mb-4 h-[7px] overflow-hidden rounded bg-line-3">
                    <div @class(['h-full rounded transition-all', 'bg-mint' => $percent === 100, 'bg-linear-to-r from-brand to-st-blue-dot' => $percent < 100]) style="width: {{ max($percent, 2) }}%"></div>
                </div>
                <div class="flex flex-col gap-0.5">
                    @foreach (\App\Models\Workplace::SETUP_FIELDS as $group => $fields)
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
                    <p class="mt-3 text-xs leading-relaxed text-muted-2">
                        Eksik: {{ collect($missing)->map($missingLabel)->implode(', ') }}.
                    </p>
                @endif
            </div>

            <x-panel.record-meta :record="$workplace">
                <div class="flex justify-between gap-3">
                    <span class="text-muted-2">Şirket</span>
                    <a href="{{ route('companies.show', $workplace->company) }}" wire:navigate class="truncate font-bold text-brand hover:text-mint">{{ $workplace->company->short_name }}</a>
                </div>
            </x-panel.record-meta>
        </div>
    </div>
</div>
