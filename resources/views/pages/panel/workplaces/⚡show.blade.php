<?php

use App\Actions\Workplaces\RevealWorkplaceCredential;
use App\Actions\Workplaces\SaveWorkplace;
use App\Livewire\PanelComponent;
use App\Models\Workplace;
use App\Validation\WorkplaceRules;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;

new #[Title('İşyeri')] class extends PanelComponent {
    public Workplace $workplace;

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

        $this->workplace = $workplace->load(['company', 'province', 'district', 'riskClass', 'laborSector']);
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
     * @return array<string, array<string, string|null>>
     */
    public function sections(): array
    {
        $w = $this->workplace;
        $date = fn ($value) => $value?->format('d.m.Y');

        return [
            'Temel Bilgiler' => [
                'İşyeri Tipi' => $w->workplace_type->label(),
                'İşyeri Türü' => $w->workplace_kind->label(),
                'Ünvan' => $w->title,
                'Tescil Tipi' => $w->registration_type?->label(),
                'Vergi Numarası' => $w->tax_number,
                'Vergi Dairesi' => $w->tax_office,
                'MERSİS Numarası' => $w->mersis_no,
                'Risk Sınıfı' => $w->riskClass?->name,
                'Tehlike Sınıfı' => $w->hazard_class->label(),
                'ÇSGB İş Kolu' => $w->laborSector ? $w->laborSector->id.' · '.$w->laborSector->name : null,
            ],
            'Adres ve İletişim' => [
                'İl / İlçe' => $w->provinceLabel().' / '.$w->districtLabel(),
                'Mahalle' => $w->neighborhood,
                'Cadde / Sokak' => $w->street,
                'Dış / İç Kapı' => trim(($w->outer_door_no ?? '').' / '.($w->inner_door_no ?? ''), ' /') ?: null,
                'Posta Kodu' => $w->postal_code,
                'Açık Adres' => $w->address,
                'Telefon' => $w->phone,
                'Cep Telefonu' => $w->mobile_phone,
                'E-Posta' => $w->email,
                'KEP Adresi' => $w->kep_address,
                'E-İmza Yetkilisi' => $w->e_signature_officer,
            ],
            'SGK' => [
                'SGK Sicil Numarası' => $w->sgk_registry_no,
                'Bağlı SGK Müdürlüğü' => $w->sgk_directorate,
                'SGK İşyeri Yetkilisi' => $w->sgk_officer_name,
                'SGK İşyeri Kodu' => $w->sgk_workplace_code,
                'e-Bildirge Yetkilisi' => $w->ebildirge_officer_name,
                'Açılış Tarihi' => $date($w->opening_date),
                'Kapanış Tarihi' => $date($w->closing_date),
                'Mahiyet' => trim(($w->mahiyet_code ?? '').' '.($w->mahiyet_name ?? '')) ?: null,
            ],
            'İŞKUR / TÜİK / Vergi' => [
                'İŞKUR Kullanıcı Adı Soyadı' => $w->iskur_user_name,
                'İŞKUR Sicil Numarası' => $w->iskur_registry_no,
                'TÜİK Kullanıcı Adı Soyadı' => $w->tuik_user_full_name,
                'TÜİK Kullanıcı Adı' => $w->tuik_username,
                'Vergi Dairesi Kullanıcı Kodu' => $w->tax_office_user_code,
            ],
            'Sendika / TİS' => $w->has_union ? [
                'Sendika' => $w->union_name,
                'TİS Başlangıç' => $date($w->cba_start_date),
                'TİS Bitiş' => $date($w->cba_end_date),
                'TİS İmza' => $date($w->cba_signed_date),
            ] : ['Sendikalı İşyeri' => 'Hayır'],
        ];
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('companies.show', $workplace->company)" wire:navigate>{{ $workplace->company->short_name }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $workplace->branch_name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ $workplace->branch_name }}</flux:heading>
            <flux:text class="mt-1">{{ $workplace->company->title }} · İşyeri No {{ $workplace->workplace_no }}</flux:text>
        </div>
        <div class="flex gap-2">
            @can('update', $workplace)
                <flux:button icon="pencil-square" :href="route('workplaces.edit', $workplace)" wire:navigate>Düzenle</flux:button>
            @endcan
            @can('delete', $workplace)
                <flux:button icon="trash" variant="ghost" wire:click="delete" wire:confirm="İşyeri silinsin mi?" />
            @endcan
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($this->sections() as $heading => $rows)
            <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading size="lg">{{ $heading }}</flux:heading>
                <dl class="mt-4 grid gap-x-6 gap-y-3 sm:grid-cols-2">
                    @foreach ($rows as $label => $value)
                        <div>
                            <dt class="text-sm text-zinc-500">{{ $label }}</dt>
                            <dd class="mt-0.5 break-words">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach

        <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <div class="flex items-center gap-2">
                <flux:icon name="lock-closed" class="size-5 text-zinc-400" />
                <flux:heading size="lg">Kullanıcı Bilgileri ve Şifreler</flux:heading>
            </div>
            <flux:text size="sm" class="mt-1">Şifreli saklanır. Her görüntüleme kaydedilir.</flux:text>

            <dl class="mt-4 space-y-3">
                @foreach (\App\Models\Workplace::SECRET_FIELDS as $field)
                    @php($stored = filled($workplace->getAttribute($field)))
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <dt class="text-sm text-zinc-500">{{ WorkplaceRules::attributes()[$field] }}</dt>
                            <dd class="mt-0.5 font-mono">
                                @if (! $stored)
                                    —
                                @elseif (array_key_exists($field, $revealed))
                                    <span class="select-all">{{ $revealed[$field] }}</span>
                                @else
                                    ••••••••
                                @endif
                            </dd>
                        </div>
                        @if ($stored)
                            @can('viewCredentials', $workplace)
                                @if (array_key_exists($field, $revealed))
                                    <flux:button size="sm" variant="ghost" icon="eye-slash" wire:click="hide('{{ $field }}')">Gizle</flux:button>
                                @else
                                    <flux:button size="sm" variant="ghost" icon="eye" wire:click="reveal('{{ $field }}')">Göster</flux:button>
                                @endif
                            @endcan
                        @endif
                    </div>
                @endforeach
            </dl>
        </section>
    </div>
</div>
