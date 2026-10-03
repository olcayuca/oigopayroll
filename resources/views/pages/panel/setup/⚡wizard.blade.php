<?php

use App\Enums\DefinitionType;
use App\Livewire\PanelComponent;
use App\Models\Company;
use App\Models\Definition;
use App\Models\Employee;
use App\Models\Workplace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/*
 * Kurulum Sihirbazı: the firm's setup in the order of the customer setup file —
 * şirket → işyeri (Firma Bilgileri) → tanımlar → personel (Personel Bilgileri) → özet.
 * Each step shows the real status and leads to the screen / import that completes it.
 */
new #[Title('Kurulum Sihirbazı')] class extends PanelComponent {
    #[Url(as: 'adim', except: 1)]
    public int $step = 1;

    public const STEPS = [1 => 'Şirket', 2 => 'İşyeri', 3 => 'Tanımlar', 4 => 'Personel', 5 => 'Özet'];

    public function mount(): void
    {
        $this->step = max(1, min(5, $this->step));
    }

    /**
     * @return Collection<int, Company>
     */
    #[Computed]
    public function companies(): Collection
    {
        return Company::visibleTo(Auth::user())->where('firm_id', $this->firm->id)->withCount('workplaces')->orderBy('company_no')->get();
    }

    /**
     * @return Collection<int, Workplace>
     */
    #[Computed]
    public function workplaces(): Collection
    {
        return Workplace::visibleTo(Auth::user())->whereIn('company_id', $this->companies->pluck('id'))->with('company:id,short_name')->get();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function definitionCounts(): array
    {
        return Definition::query()->where('firm_id', $this->firm->id)->where('is_active', true)
            ->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type')->map(fn ($total) => (int) $total)->all();
    }

    /**
     * @return array{total: int, active: int, incomplete: int}
     */
    #[Computed]
    public function personnel(): array
    {
        $query = fn () => Employee::viewableBy(Auth::user(), $this->firm);

        return [
            'total' => $query()->count(),
            'active' => $query()->where('status', Employee::ACTIVE)->count(),
            'incomplete' => $query()->incomplete()->count(),
        ];
    }

    /**
     * Step => [done, status text].
     *
     * @return array<int, array{0: bool, 1: string}>
     */
    #[Computed]
    public function status(): array
    {
        $withoutWorkplace = $this->companies->where('workplaces_count', 0)->count();
        $incompleteWorkplaces = $this->workplaces->filter(fn (Workplace $workplace) => $workplace->setupPercent() < 100)->count();
        $required = [DefinitionType::UpperUnit, DefinitionType::Title, DefinitionType::Position];
        $missingDefinitions = array_filter($required, fn (DefinitionType $type) => ($this->definitionCounts[$type->value] ?? 0) === 0);

        $status = [
            1 => [$this->companies->isNotEmpty(), $this->companies->count().' şirket'],
            2 => [$this->workplaces->isNotEmpty() && $withoutWorkplace === 0 && $incompleteWorkplaces === 0,
                $this->workplaces->count().' işyeri'.($incompleteWorkplaces ? ' · '.$incompleteWorkplaces.' eksik' : '')],
            3 => [$missingDefinitions === [], array_sum($this->definitionCounts).' tanım'],
            4 => [$this->personnel['active'] > 0 && $this->personnel['incomplete'] === 0,
                $this->personnel['active'].' personel'.($this->personnel['incomplete'] ? ' · '.$this->personnel['incomplete'].' eksik' : '')],
        ];
        $status[5] = [collect($status)->every(fn ($item) => $item[0]), collect($status)->filter(fn ($item) => $item[0])->count().' / 4 adım'];

        return $status;
    }

    public function go(int $step): void
    {
        $this->step = max(1, min(5, $step));
    }
}; ?>

<div class="mx-auto max-w-4xl">
    @php
        $status = $this->status;
        $canCompany = auth()->user()->can('create', [\App\Models\Company::class, $this->firm]);
        $canImportWorkplaces = auth()->user()->can('import', [\App\Models\Workplace::class, $this->firm]);
        $canImportEmployees = auth()->user()->can('import', [\App\Models\Employee::class, $this->firm]);
        $canEmployee = auth()->user()->can('create', [\App\Models\Employee::class, $this->firm]);
    @endphp

    <div class="mb-6 text-center">
        <div class="text-[11px] font-bold tracking-[0.14em] text-mint-strong">KURULUM SİHİRBAZI</div>
        <h1 class="mt-1.5 text-[26px] font-extrabold tracking-[-0.02em] text-ink">{{ $this->firm->name }} kurulumu</h1>
        <p class="mt-1 text-sm text-muted">Müşteri kurulum dosyasıyla 5 adımda bordroya hazır hale gelin.</p>
    </div>

    {{-- Stepper --}}
    <ol class="mb-6 flex items-start">
        @foreach ($this::STEPS as $number => $label)
            @php [$done] = $status[$number]; $current = $number === $step; @endphp
            <li class="relative flex flex-1 flex-col items-center">
                @if (! $loop->last)
                    <span @class(['absolute top-4 start-1/2 h-0.5 w-full', 'bg-mint' => $done, 'bg-line-2' => ! $done])></span>
                @endif
                <button type="button" wire:click="go({{ $number }})" aria-current="{{ $current ? 'step' : 'false' }}"
                    @class([
                        'relative z-10 flex size-8 items-center justify-center rounded-full border-2 text-[13px] font-extrabold transition',
                        'border-brand bg-brand text-white' => $current,
                        'border-mint bg-mint text-white' => $done && ! $current,
                        'border-line-2 bg-white text-muted hover:border-brand' => ! $done && ! $current,
                    ])>
                    @if ($done && ! $current)
                        <flux:icon.check variant="micro" class="size-4" />
                    @else
                        {{ $number }}
                    @endif
                </button>
                <span @class(['mt-2 text-[12.5px] font-bold', 'text-ink' => $current, 'text-muted' => ! $current])>{{ $label }}</span>
                <span class="text-[11px] font-semibold text-faint">{{ $status[$number][1] }}</span>
            </li>
        @endforeach
    </ol>

    <div class="rounded-2xl border border-line bg-white shadow-[0_1px_3px_rgba(16,40,72,0.04)]">
        <div class="p-6">
            @if ($step === 1)
                <h2 class="text-[17px] font-extrabold text-ink">Şirketler</h2>
                <p class="mt-1 mb-5 text-[13px] text-muted">Bordro ve resmi bildirgelerde kullanılacak şirket kimlikleri. Kurulum dosyasındaki "Şirket Adı" ve personeldeki "Firma" bu şirketlerle eşleşir.</p>

                @if ($this->companies->isEmpty())
                    <x-panel.alert variant="warning" title="Henüz şirket yok">Önce en az bir şirket oluşturun; işyerleri ve personel şirkete bağlanır.</x-panel.alert>
                @else
                    <div class="divide-y divide-line-4 rounded-xl border border-line">
                        @foreach ($this->companies as $company)
                            <div class="flex items-center gap-3 px-4 py-3">
                                <x-panel.avatar :initials="\App\Support\Text::initials($company->short_name)" tone="navy" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-[13.5px] font-bold text-ink">{{ $company->title }}</div>
                                    <div class="text-xs text-muted-2">No {{ $company->company_no }} · VKN {{ $company->tax_number }}</div>
                                </div>
                                <x-panel.badge :color="$company->workplaces_count ? 'green' : 'amber'">{{ $company->workplaces_count }} işyeri</x-panel.badge>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-5 flex flex-wrap gap-2">
                    @if ($canCompany)
                        <flux:button icon="plus" :href="route('companies.create')" wire:navigate>Yeni şirket</flux:button>
                        <flux:button icon="table-cells" :href="route('imports.create', 'sirket')" wire:navigate>Excel ile şirket aktar</flux:button>
                    @endif
                </div>
            @elseif ($step === 2)
                <h2 class="text-[17px] font-extrabold text-ink">İşyerleri</h2>
                <p class="mt-1 mb-5 text-[13px] text-muted">Kurulum dosyasının <strong>Firma Bilgileri</strong> sayfası (36 zorunlu alan): SGK, vergi, İŞKUR, emniyet & BES ve adres bilgileri.</p>

                <div class="grid gap-3 sm:grid-cols-3">
                    <x-panel.stat :value="$this->workplaces->count()" label="İşyeri" color="navy" />
                    <x-panel.stat :value="$this->companies->where('workplaces_count', 0)->count()" label="İşyeri olmayan şirket" color="amber" />
                    <x-panel.stat :value="$this->workplaces->filter(fn ($w) => $w->setupPercent() < 100)->count()" label="Eksik bilgili işyeri" color="red" :href="route('workplaces.index', ['durum' => 'eksik'])" />
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    @if ($canImportWorkplaces)
                        <flux:button variant="primary" icon="arrow-up-tray" :href="route('imports.create', 'isyeri')" wire:navigate>Kurulum dosyasını yükle (Firma Bilgileri)</flux:button>
                    @endif
                    <flux:button :href="route('workplaces.index')" wire:navigate>İşyerlerine git</flux:button>
                </div>
            @elseif ($step === 3)
                <h2 class="text-[17px] font-extrabold text-ink">Tanımlar</h2>
                <p class="mt-1 mb-5 text-[13px] text-muted">Personel kartında seçilen organizasyon listeleri. Personel kurulum dosyası yüklenirken listede olmayan birim, unvan ve pozisyonlar otomatik oluşturulur; bu adımı atlayabilirsiniz.</p>

                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach (\App\Enums\DefinitionType::cases() as $type)
                        @php $count = $this->definitionCounts[$type->value] ?? 0; $isRequired = in_array($type, [\App\Enums\DefinitionType::UpperUnit, \App\Enums\DefinitionType::Title, \App\Enums\DefinitionType::Position], true); @endphp
                        <a href="{{ route('definitions.index', ['tur' => $type->slug()]) }}" wire:navigate class="flex items-center gap-3 rounded-xl border border-line px-4 py-3 transition hover:border-brand">
                            <span @class(['size-2 rounded-full', 'bg-mint' => $count > 0, 'bg-st-amber-dot' => $count === 0 && $isRequired, 'bg-faint' => $count === 0 && ! $isRequired])></span>
                            <span class="flex-1 text-[13.5px] font-bold text-ink">{{ $type->label() }}</span>
                            <span class="text-xs font-semibold text-muted-2">{{ $count }} {{ $isRequired ? '· zorunlu' : '' }}</span>
                        </a>
                    @endforeach
                </div>
            @elseif ($step === 4)
                <h2 class="text-[17px] font-extrabold text-ink">Personel</h2>
                <p class="mt-1 mb-5 text-[13px] text-muted">Kurulum dosyasının <strong>Personel Bilgileri</strong> sayfası: kimlik, SGK, ücret, banka ve çalışma düzeni. Aynı dosyayı yükleyebilirsiniz; ilgili sayfa otomatik seçilir.</p>

                <div class="grid gap-3 sm:grid-cols-3">
                    <x-panel.stat :value="$this->personnel['total']" label="Toplam personel" color="navy" />
                    <x-panel.stat :value="$this->personnel['active']" label="Aktif" color="mint" />
                    <x-panel.stat :value="$this->personnel['incomplete']" label="Eksik bordro verisi" color="amber" :href="route('employees.index', ['durum' => 'eksik'])" />
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    @if ($canImportEmployees)
                        <flux:button variant="primary" icon="arrow-up-tray" :href="route('imports.create', 'personel')" wire:navigate>Kurulum dosyasını yükle (Personel Bilgileri)</flux:button>
                    @endif
                    @if ($canEmployee)
                        <flux:button icon="plus" :href="route('employees.create')" wire:navigate>Yeni personel</flux:button>
                    @endif
                    <flux:button :href="route('employees.index')" wire:navigate>Personel listesi</flux:button>
                </div>
            @else
                <h2 class="text-[17px] font-extrabold text-ink">Özet</h2>
                <p class="mt-1 mb-5 text-[13px] text-muted">Tüm adımlar tamamlandığında firma bordro dönemine hazırdır.</p>

                @if ($status[5][0])
                    <x-panel.alert variant="success" title="Kurulum tamamlandı">Şirket, işyeri, tanım ve personel bilgileri eksiksiz. Bordro modülü açıldığında ilk dönem bu verilerle hesaplanacak.</x-panel.alert>
                @endif

                <div class="mt-4 divide-y divide-line-4 rounded-xl border border-line">
                    @foreach (array_slice($this::STEPS, 0, 4, true) as $number => $label)
                        @php [$done, $text] = $status[$number]; @endphp
                        <button type="button" wire:click="go({{ $number }})" class="flex w-full items-center gap-3 px-4 py-3 text-start hover:bg-row-hover">
                            <span @class(['flex size-6 items-center justify-center rounded-full text-white', 'bg-mint' => $done, 'bg-st-amber-dot' => ! $done])>
                                @if ($done) <flux:icon.check variant="micro" class="size-3.5" /> @else <span class="text-[11px] font-extrabold">!</span> @endif
                            </span>
                            <span class="flex-1 text-[13.5px] font-bold text-ink">{{ $label }}</span>
                            <span class="text-xs font-semibold text-muted-2">{{ $text }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex items-center justify-between border-t border-line-3 px-6 py-4">
            <flux:button wire:click="go({{ $step - 1 }})" :disabled="$step === 1">‹ Geri</flux:button>
            <span class="text-[12.5px] font-semibold text-muted-2">Adım {{ $step }} / 5</span>
            @if ($step < 5)
                <flux:button variant="primary" wire:click="go({{ $step + 1 }})">Devam ›</flux:button>
            @else
                <flux:button variant="primary" :href="route('dashboard')" wire:navigate>Gösterge paneline dön</flux:button>
            @endif
        </div>
    </div>
</div>
