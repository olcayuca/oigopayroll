<?php

use App\Actions\Firms\RegisterFirm;
use App\Enums\FirmStatus;
use App\Enums\WorkplaceType;
use App\Livewire\Concerns\MapsValidationErrors;
use App\Models\Company;
use App\Models\Firm;
use App\Models\FirmDocument;
use App\Models\Holiday;
use App\Models\Workplace;
use App\Support\SetupStatus;
use App\Validation\FirmRules;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Gösterge Paneli')] class extends Component {
    use MapsValidationErrors;

    /** @var array<string, string> */
    public array $firmForm = [];

    #[Computed]
    public function firm(): ?Firm
    {
        return Auth::user()->activeFirm();
    }

    /**
     * @return Collection<int, Company>
     */
    #[Computed]
    public function companies(): Collection
    {
        return Company::visibleTo(Auth::user())
            ->where('firm_id', $this->firm?->id)
            ->withCount('workplaces')
            ->orderBy('company_no')
            ->get();
    }

    /**
     * @return Collection<int, Workplace>
     */
    #[Computed]
    public function workplaces(): Collection
    {
        return Workplace::visibleTo(Auth::user())
            ->whereIn('company_id', $this->companies->pluck('id'))
            ->with('company:id,short_name')
            ->orderBy('company_id')
            ->orderBy('workplace_no')
            ->get();
    }

    /**
     * Firm documents that are expired or expire soon (only when the user may see documents).
     *
     * @return Collection<int, FirmDocument>
     */
    #[Computed]
    public function documentsNeedingAttention(): Collection
    {
        if (! $this->firm || ! Auth::user()->can('viewDocuments', $this->firm)) {
            return new Collection;
        }

        return $this->firm->documents()->needsAttention()->orderBy('valid_until')->limit(5)->get();
    }

    /**
     * Active personnel the user may view, and how many of them miss required data.
     *
     * @return array{active: int, incomplete: int}
     */
    #[Computed]
    public function personnel(): array
    {
        if (! $this->firm) {
            return ['active' => 0, 'incomplete' => 0];
        }

        $query = fn () => \App\Models\Employee::viewableBy(Auth::user(), $this->firm)->where('status', \App\Models\Employee::ACTIVE);

        return ['active' => $query()->count(), 'incomplete' => $query()->incomplete()->count()];
    }

    #[Computed]
    public function documentCount(): ?int
    {
        return $this->firm && Auth::user()->can('viewDocuments', $this->firm) ? $this->firm->documents()->count() : null;
    }

    /**
     * Average setup completion of the workplaces, in percent.
     */
    #[Computed]
    public function setupPercent(): int
    {
        return $this->workplaces->isEmpty() ? 0 : (int) round($this->workplaces->avg(fn (Workplace $workplace) => $workplace->setupPercent()));
    }

    /**
     * All wizard steps done but the payroll specialist has not approved yet.
     */
    #[Computed]
    public function awaitingApproval(): bool
    {
        return $this->firm !== null && $this->firm->setup_approved_at === null && (new SetupStatus(Auth::user(), $this->firm))->complete();
    }

    /**
     * Upcoming public holidays (next 60 days).
     *
     * @return Collection<int, Holiday>
     */
    #[Computed]
    public function holidays(): Collection
    {
        return Holiday::query()
            ->whereBetween('date', [today(), today()->addDays(60)])
            ->orderBy('date')
            ->limit(4)
            ->get();
    }

    /**
     * A client without any firm creates one here; it then waits for HRD approval.
     */
    public function registerFirm(RegisterFirm $registerFirm): void
    {
        $this->authorize('register', Firm::class);

        $firm = $this->mappingErrors('firmForm', FirmRules::FIELDS, fn () => $registerFirm->handle(Auth::user(), $this->firmForm));

        if (! $firm) {
            return;
        }

        Auth::user()->switchFirm($firm);

        $this->reset('firmForm');
        unset($this->firm);

        Flux::toast(variant: 'success', text: 'Firmanız oluşturuldu ve HRD onayına gönderildi.');
    }

    public function greeting(): string
    {
        return match (true) {
            now()->hour < 6 => 'İyi geceler',
            now()->hour < 12 => 'Günaydın',
            now()->hour < 18 => 'İyi günler',
            default => 'İyi akşamlar',
        };
    }
}; ?>

<div>
    @php $firstName = \Illuminate\Support\Str::before(auth()->user()->name, ' '); @endphp
    @if (! $this->firm)
        <x-panel.page-header :title="$this->greeting().' '.$firstName" :subtitle="auth()->user()->type->label()" />

        @can('register', \App\Models\Firm::class)
            <x-panel.card class="max-w-2xl" title="Firmanızı oluşturun"
                description="Firmanız HRD tarafından onaylandıktan sonra şirket ve işyeri bilgilerinizi girmeye başlayabilirsiniz.">
                <form wire:submit="registerFirm" class="space-y-4">
                    <x-firm-fields />
                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary">Firmayı Oluştur</flux:button>
                    </div>
                </form>
            </x-panel.card>
        @else
            <x-panel.alert variant="info" title="Yetkili olduğunuz firma yok" class="max-w-2xl">
                Çalışabilmeniz için bir süper adminin size firma, şirket veya işyeri yetkisi vermesi gerekiyor.
            </x-panel.alert>
        @endcan
    @elseif ($this->firm->status !== FirmStatus::Active)
        <x-panel.page-header :crumbs="['Genel Bakış' => null, $this->firm->name => null]" :title="$this->greeting().' '.$firstName" />

        @switch($this->firm->status)
            @case(FirmStatus::Pending)
                <x-panel.alert variant="warning" icon="clock" title="Firmanız onay bekliyor" class="max-w-2xl">
                    HRD ekibi firmanızı inceliyor. Onaylandığında şirket ve işyeri işlemlerini yapabileceksiniz.
                </x-panel.alert>
                @break
            @case(FirmStatus::Rejected)
                <x-panel.alert variant="danger" icon="x-circle" title="Firma başvurunuz reddedildi" class="max-w-2xl">
                    Gerekçe: {{ $this->firm->rejection_reason }}
                </x-panel.alert>
                @break
            @default
                <x-panel.alert variant="info" icon="pause-circle" title="Firma pasif durumda" class="max-w-2xl">
                    Bu firma üzerinde işlem yapılamaz. Ayrıntılar için HRD ile iletişime geçin.
                </x-panel.alert>
        @endswitch
    @else
        @php
            $companies = $this->companies;
            $workplaces = $this->workplaces;
            $withoutWorkplace = $companies->where('workplaces_count', 0);
            $incomplete = $workplaces->filter(fn ($workplace) => $workplace->setupPercent() < 100)->sortBy(fn ($workplace) => $workplace->setupPercent());
            $headquarters = $workplaces->where('workplace_type', WorkplaceType::Headquarters)->count();

            $steps = [
                ['title' => 'Şirketler', 'text' => $companies->count().' şirket tanımlı', 'done' => $companies->isNotEmpty(), 'href' => route('companies.index')],
                ['title' => 'İşyerleri', 'text' => $withoutWorkplace->isEmpty() ? $workplaces->count().' işyeri' : $withoutWorkplace->count().' şirketin işyeri yok', 'done' => $companies->isNotEmpty() && $withoutWorkplace->isEmpty(), 'href' => route('workplaces.index')],
                ['title' => 'İşyeri bilgileri', 'text' => 'Ortalama %'.$this->setupPercent.' tamam', 'done' => $workplaces->isNotEmpty() && $this->setupPercent === 100, 'href' => route('workplaces.index', ['durum' => 'eksik'])],
                ['title' => 'Personel', 'text' => $this->personnel['active'] ? $this->personnel['active'].' aktif personel' : 'Kurulum dosyasını yükleyin', 'done' => $this->personnel['active'] > 0 && $this->personnel['incomplete'] === 0, 'href' => route('employees.index')],
                ['title' => 'Firma belgeleri', 'text' => $this->documentCount ? $this->documentCount.' belge yüklü' : 'Vergi levhası, imza sirküleri…', 'done' => (bool) $this->documentCount, 'href' => route('documents.index')],
            ];
            $doneSteps = collect($steps)->where('done', true)->count();
            $warnings = $withoutWorkplace->count() + $incomplete->count() + $this->documentsNeedingAttention->count() + ($this->personnel['incomplete'] > 0 ? 1 : 0);
        @endphp

        <x-panel.page-header :crumbs="['Genel Bakış' => null, $this->firm->name => null]" :title="$this->greeting().' '.$firstName"
            :subtitle="$this->firm->name.' · '.$companies->count().' şirket · '.$workplaces->count().' işyeri'">
            <x-slot:actions>
                <flux:button :href="route('companies.index')" wire:navigate>Şirketler</flux:button>
                @can('create', [\App\Models\Company::class, $this->firm])
                    <flux:button variant="primary" icon="plus" :href="route('workplaces.create')" wire:navigate>Yeni İşyeri</flux:button>
                @endcan
            </x-slot:actions>
        </x-panel.page-header>

        <div class="mb-[18px] grid grid-cols-2 gap-3 sm:gap-3.5 xl:grid-cols-4">
            <x-panel.kpi label="Şirket" :value="$companies->count()" color="navy"
                :hint="$withoutWorkplace->isEmpty() ? 'Tümünün işyeri var' : $withoutWorkplace->count().' şirketin işyeri yok'" />
            <x-panel.kpi label="İşyeri" :value="$workplaces->count()" color="mint"
                :hint="$headquarters.' merkez · '.($workplaces->count() - $headquarters).' şube / diğer'" />
            <x-panel.kpi label="İşyeri kurulumu" :value="'%'.$this->setupPercent" color="blue"
                :hint="$incomplete->isEmpty() ? 'Tüm işyerleri eksiksiz' : $incomplete->count().' işyerinde eksik bilgi'" />
            <x-panel.kpi label="Aktif personel" :value="$this->personnel['active']" color="amber"
                :hint="$this->personnel['incomplete'] ? $this->personnel['incomplete'].' personelde eksik bordro verisi' : 'Bordro verileri eksiksiz'" />
        </div>

        {{-- Setup checklist --}}
        @if ($doneSteps < count($steps))
            <x-panel.card class="mb-[18px]" :title="'Kurulumu tamamlayın'" :description="$this->firm->name.' bordroya hazır olmadan önce tamamlanması gereken adımlar.'">
                <x-slot:actions>
                    <span class="text-[13px] font-extrabold text-ink tabular-nums">%{{ (int) round($doneSteps * 100 / count($steps)) }}</span>
                    <a href="{{ route('setup.wizard') }}" wire:navigate class="flex h-9 items-center rounded-[10px] bg-mint px-3.5 text-[13px] font-bold text-white hover:bg-mint-strong">Sihirbazla kur</a>
                </x-slot:actions>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
                    @foreach ($steps as $step)
                        <a href="{{ $step['href'] }}" wire:navigate @class([
                            'flex items-center gap-3 rounded-xl border-[1.5px] px-3.5 py-3 transition',
                            'border-[#CFE9E3] bg-mint-soft/60 hover:border-mint' => $step['done'],
                            'border-line hover:border-brand' => ! $step['done'],
                        ])>
                            @if ($step['done'])
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-mint text-white"><flux:icon.check variant="micro" class="size-4" /></span>
                            @else
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-seg text-[12px] font-extrabold text-ink-2">{{ $loop->iteration }}</span>
                            @endif
                            <span class="min-w-0">
                                <span class="block truncate text-[13.5px] font-bold text-ink">{{ $step['title'] }}</span>
                                <span class="block truncate text-xs text-muted-2">{{ $step['text'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </x-panel.card>
        @endif

        @if ($this->awaitingApproval)
            <x-panel.alert variant="info" class="mb-[18px]" title="Kurulum adımları tamamlandı" data-test="setup-awaiting-approval">
                Sorumlu bordro uzmanının kontrolü ve onayı bekleniyor.
                @can('approveSetup', $this->firm)
                    <a href="{{ route('setup.wizard', ['adim' => 5]) }}" wire:navigate class="font-bold underline">Kurulumu onayla</a>
                @endcan
            </x-panel.alert>
        @endif

        <div class="grid items-start gap-[18px] xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
            <div class="flex min-w-0 flex-col gap-[18px]">
                {{-- Companies overview (dark hero card) --}}
                <div class="overflow-hidden rounded-2xl bg-linear-160 from-side to-[#143A5E] p-5 text-white shadow-[0_10px_30px_rgba(16,40,72,0.18)] sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="text-[11px] font-bold tracking-[0.12em] text-mint-light">FİRMA YAPISI</div>
                            <div class="mt-1 text-[22px] font-extrabold tracking-tight">{{ $this->firm->name }}</div>
                            <div class="mt-0.5 text-[13px] text-side-text">
                                {{ $this->firm->tax_number ? 'VKN '.$this->firm->tax_number.' · ' : '' }}{{ $this->firm->specialist ? 'Sorumlu uzman: '.$this->firm->specialist->name : 'Sorumlu uzman atanmadı' }}
                            </div>
                        </div>
                        @can('create', [\App\Models\Company::class, $this->firm])
                            <a href="{{ route('companies.create') }}" wire:navigate class="flex h-10 items-center gap-2 rounded-[10px] bg-mint px-4 text-[13px] font-bold text-white hover:bg-mint-strong">
                                <flux:icon.plus variant="micro" class="size-4" /> Yeni şirket
                            </a>
                        @endcan
                    </div>

                    <div class="mt-5 space-y-2">
                        @forelse ($companies->take(6) as $company)
                            @php $companyWorkplaces = $workplaces->where('company_id', $company->id); @endphp
                            @php $percent = $companyWorkplaces->isEmpty() ? 0 : (int) round($companyWorkplaces->avg(fn ($workplace) => $workplace->setupPercent())); @endphp
                            <a href="{{ route('companies.show', $company) }}" wire:navigate class="flex items-center gap-3 rounded-xl bg-white/5 px-3.5 py-2.5 transition hover:bg-white/10">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/10 text-[11px] font-extrabold">{{ \App\Support\Text::initials($company->short_name) }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[13.5px] font-bold">{{ $company->short_name }}</span>
                                    <span class="block truncate text-[11.5px] text-side-text">No {{ $company->company_no }} · {{ $company->workplaces_count }} işyeri</span>
                                </span>
                                @if ($company->workplaces_count === 0)
                                    <span class="rounded-md bg-st-amber-dot/20 px-2 py-0.5 text-[11px] font-bold text-[#F5C27A]">İşyeri yok</span>
                                @else
                                    <span class="hidden w-28 items-center gap-2 sm:flex">
                                        <span class="h-1.5 flex-1 overflow-hidden rounded bg-white/10"><span class="block h-full rounded {{ $percent === 100 ? 'bg-mint' : 'bg-st-blue-dot' }}" style="width: {{ $percent }}%"></span></span>
                                        <span class="w-9 text-end text-[11.5px] font-bold tabular-nums">%{{ $percent }}</span>
                                    </span>
                                @endif
                            </a>
                        @empty
                            <div class="rounded-xl bg-white/5 px-4 py-6 text-center text-[13px] text-side-text">
                                Henüz şirket yok. İlk adım olarak firmanıza bağlı şirket(ler)i oluşturun.
                            </div>
                        @endforelse
                        @if ($companies->count() > 6)
                            <a href="{{ route('companies.index') }}" wire:navigate class="block pt-1 text-center text-[12.5px] font-bold text-mint-light hover:text-white">Tüm şirketler ({{ $companies->count() }}) ›</a>
                        @endif
                    </div>
                </div>

                {{-- Quick actions --}}
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @php
                        $quick = array_filter([
                            auth()->user()->can('create', [\App\Models\Company::class, $this->firm]) ? ['YŞ', 'Yeni şirket', 'Manuel kayıt', route('companies.create'), 'soft'] : null,
                            auth()->user()->can('create', [\App\Models\Company::class, $this->firm]) ? ['Yİ', 'Yeni işyeri', 'Şube / merkez', route('workplaces.create'), 'mint-soft'] : null,
                            auth()->user()->can('import', [\App\Models\Workplace::class, $this->firm]) ? ['XL', 'Excel ile aktar', 'Toplu işyeri', route('imports.create', 'isyeri'), 'amber'] : null,
                            ['BL', 'Belgeler', 'Yükle / görüntüle', route('documents.index'), 'soft'],
                        ]);
                    @endphp
                    @foreach ($quick as [$initials, $title, $text, $href, $tone])
                        <a href="{{ $href }}" wire:navigate class="flex items-center gap-3 rounded-[14px] border border-line bg-white px-4 py-3.5 transition hover:border-brand">
                            <x-panel.avatar :initials="$initials" :tone="$tone" />
                            <span class="min-w-0">
                                <span class="block truncate text-[13.5px] font-bold text-ink">{{ $title }}</span>
                                <span class="block truncate text-xs text-muted-2">{{ $text }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="flex min-w-0 flex-col gap-[18px]">
                {{-- Warnings --}}
                <x-panel.card title="Uyarılar & eksik veri">
                    <x-slot:actions>
                        <x-panel.badge :color="$warnings ? 'red' : 'green'" :dot="false">{{ $warnings }}</x-panel.badge>
                    </x-slot:actions>

                    <div class="space-y-2.5">
                        @foreach ($withoutWorkplace as $company)
                            <a href="{{ route('companies.show', $company) }}" wire:navigate class="block rounded-xl border border-[#F3D3D1] bg-st-red-bg px-3.5 py-2.5 hover:border-st-red-dot">
                                <div class="flex items-center gap-2 text-[13px] font-bold text-st-red"><span class="size-1.5 rounded-full bg-st-red-dot"></span>{{ $company->short_name }} — işyeri yok</div>
                                <div class="mt-0.5 ps-3.5 text-xs text-[#B5615E]">Her şirketin altında en az bir işyeri tanımlanması zorunludur.</div>
                            </a>
                        @endforeach
                        @foreach ($this->documentsNeedingAttention as $document)
                            @php $expired = $document->validity() === \App\Models\FirmDocument::EXPIRED; @endphp
                            <a href="{{ route('documents.index') }}" wire:navigate @class([
                                'block rounded-xl border px-3.5 py-2.5',
                                'border-[#F3D3D1] bg-st-red-bg' => $expired,
                                'border-[#F2E2C4] bg-st-amber-bg' => ! $expired,
                            ])>
                                <div @class(['flex items-center gap-2 text-[13px] font-bold', 'text-st-red' => $expired, 'text-st-amber' => ! $expired])>
                                    <span @class(['size-1.5 rounded-full', 'bg-st-red-dot' => $expired, 'bg-st-amber-dot' => ! $expired])></span>{{ $document->title }}
                                </div>
                                <div class="mt-0.5 ps-3.5 text-xs text-muted">
                                    {{ $expired ? 'Süresi doldu' : 'Süresi doluyor' }}: {{ $document->valid_until->format('d.m.Y') }}
                                </div>
                            </a>
                        @endforeach
                        @foreach ($incomplete->take(5) as $workplace)
                            <a href="{{ route('workplaces.show', $workplace) }}" wire:navigate class="block rounded-xl border border-[#F2E2C4] bg-st-amber-bg px-3.5 py-2.5 hover:border-st-amber-dot">
                                <div class="flex items-center gap-2 text-[13px] font-bold text-st-amber"><span class="size-1.5 rounded-full bg-st-amber-dot"></span>{{ $workplace->branch_name }} işyeri kurulumu eksik</div>
                                <div class="mt-0.5 ps-3.5 text-xs text-[#8A5A14]/80">{{ $workplace->company->short_name }} · {{ count($workplace->missingSetupFields()) }} alan boş</div>
                            </a>
                        @endforeach
                        @if ($this->personnel['incomplete'] > 0)
                            <a href="{{ route('employees.index', ['durum' => 'eksik']) }}" wire:navigate class="block rounded-xl border border-[#F3D3D1] bg-st-red-bg px-3.5 py-2.5 hover:border-st-red-dot">
                                <div class="flex items-center gap-2 text-[13px] font-bold text-st-red"><span class="size-1.5 rounded-full bg-st-red-dot"></span>{{ $this->personnel['incomplete'] }} personelde eksik bordro verisi</div>
                                <div class="mt-0.5 ps-3.5 text-xs text-[#B5615E]">Banka, SGK veya ücret bilgileri tamamlanmadan bordro hesaplanamaz.</div>
                            </a>
                        @endif
                        @if ($incomplete->count() > 5)
                            <a href="{{ route('workplaces.index', ['durum' => 'eksik']) }}" wire:navigate class="block text-center text-[12.5px] font-bold text-brand hover:text-mint">+{{ $incomplete->count() - 5 }} işyeri daha ›</a>
                        @endif
                        @if ($warnings === 0)
                            <div class="flex items-center gap-3 rounded-xl border border-[#CFE9E3] bg-st-green-bg px-3.5 py-3 text-[13px] font-bold text-st-green">
                                <flux:icon.check-circle variant="mini" class="size-[18px]" /> Her şey yolunda, eksik veri yok.
                            </div>
                        @endif
                    </div>
                </x-panel.card>

                {{-- Dates --}}
                <x-panel.card title="Önemli tarihler">
                    @php
                        $declaration = today()->day > 26 ? today()->addMonthNoOverflow()->day(26) : today()->day(26);
                        $dates = collect([[$declaration, 'Muhtasar ve prim hizmet beyannamesi', 'Beyan ve ödeme son günü', 'gray']])
                            ->merge($this->holidays->map(fn ($holiday) => [$holiday->date, $holiday->name, $holiday->is_half_day ? 'Yarım gün tatil' : 'Resmi tatil', 'blue']))
                            ->sortBy(fn ($date) => $date[0])
                            ->take(4);
                    @endphp
                    <div class="-my-2 divide-y divide-line-4">
                        @foreach ($dates as [$date, $title, $text, $color])
                            @php $days = (int) today()->diffInDays($date); @endphp
                            <div class="flex items-center gap-3.5 py-2.5">
                                <div class="w-11 shrink-0 text-center">
                                    <div class="text-lg leading-none font-extrabold tabular-nums">{{ $date->format('d') }}</div>
                                    <div class="text-[11px] font-bold text-muted-2 uppercase">{{ $date->translatedFormat('M') }}</div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-[13.5px] font-bold text-ink">{{ $title }}</div>
                                    <div class="truncate text-xs text-muted-2">{{ $text }}</div>
                                </div>
                                <x-panel.badge :color="$days <= 7 ? 'amber' : $color" :dot="false" class="!text-[11px]">{{ $days === 0 ? 'Bugün' : $days.' gün' }}</x-panel.badge>
                            </div>
                        @endforeach
                    </div>
                </x-panel.card>
            </div>
        </div>
    @endif
</div>
