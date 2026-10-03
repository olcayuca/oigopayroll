<?php

use App\Enums\AuditEvent;
use App\Livewire\PanelComponent;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/*
 * İşlem Geçmişi: the firm's audit trail, filterable by company (şirket), workplace (şube), user,
 * module, date and record; each row opens the field-level changes and the request details.
 */
new #[Title('İşlem Geçmişi')] class extends PanelComponent {
    use WithPagination;

    #[Url(except: '')]
    public string $q = '';

    #[Url(as: 'kullanici', except: '')]
    public string $user = '';

    #[Url(as: 'sirket', except: '')]
    public string $company = '';

    #[Url(as: 'sube', except: '')]
    public string $workplace = '';

    #[Url(as: 'modul', except: '')]
    public string $module = '';

    #[Url(as: 'baslangic', except: '')]
    public string $from = '';

    #[Url(as: 'bitis', except: '')]
    public string $to = '';

    #[Url(as: 'kayit', except: '')]
    public string $record = '';

    public ?int $expanded = null;

    /** Modules shown on the panel (event groups). */
    public const MODULES = [
        'company' => 'Şirket', 'workplace' => 'İşyeri', 'employee' => 'Personel', 'definition' => 'Tanımlar',
        'import' => 'Aktarım', 'firm' => 'Firma', 'access' => 'Yetki', 'security' => 'Güvenlik', 'system' => 'Dışa aktarma', 'auth' => 'Oturum',
    ];

    public function mount(): void
    {
        $this->authorize('viewAudit', $this->firm);
    }

    public function updated(string $property): void
    {
        if ($property === 'company') {
            $this->workplace = '';
        }

        if (in_array($property, ['q', 'user', 'company', 'workplace', 'module', 'from', 'to', 'record'], true)) {
            $this->resetPage();
            $this->expanded = null;
        }
    }

    public function clearFilters(): void
    {
        $this->reset('q', 'user', 'company', 'workplace', 'module', 'from', 'to', 'record', 'expanded');
        $this->resetPage();
    }

    public function toggle(int $id): void
    {
        $this->expanded = $this->expanded === $id ? null : $id;
    }

    /**
     * @return array<string, string>
     */
    public function filters(): array
    {
        return ['q' => $this->q, 'user' => $this->user, 'company' => $this->company, 'workplace' => $this->workplace,
            'module' => $this->module, 'from' => $this->from, 'to' => $this->to, 'record' => $this->record];
    }

    /**
     * @return Builder<AuditLog>
     */
    protected function baseQuery(): Builder
    {
        return AuditLog::query()->visibleInFirm(Auth::user(), $this->firm);
    }

    /**
     * @return LengthAwarePaginator<int, AuditLog>
     */
    #[Computed]
    public function logs(): LengthAwarePaginator
    {
        return $this->baseQuery()->filter($this->filters())
            ->with(['user:id,name,email', 'company:id,short_name', 'workplace:id,branch_name'])
            ->latest('created_at')->latest('id')
            ->paginate(50);
    }

    /**
     * @return array{total: int, changes: int, users: int, last: string|null}
     */
    #[Computed]
    public function stats(): array
    {
        $query = fn () => $this->baseQuery()->filter($this->filters());
        $last = $query()->max('created_at');

        return [
            'total' => $query()->count(),
            'changes' => $query()->where(fn ($query) => $query->where('event', 'like', '%.updated')->orWhere('event', 'like', '%.created')
                ->orWhere('event', 'like', '%.deleted')->orWhere('event', 'definition.changed'))->count(),
            'users' => $query()->whereNotNull('user_id')->distinct()->count('user_id'),
            'last' => $last ? \Illuminate\Support\Carbon::parse($last)->diffForHumans() : null,
        ];
    }

    /**
     * Users that appear in the visible history.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function users(): Collection
    {
        return User::query()->whereIn('id', $this->baseQuery()->whereNotNull('user_id')->select('user_id')->distinct())
            ->orderBy('name')->get(['id', 'name', 'email']);
    }

    /**
     * @return Collection<int, Company>
     */
    #[Computed]
    public function companies(): Collection
    {
        $reach = AuditLog::reach(Auth::user(), $this->firm);

        return Company::withTrashed()->where('firm_id', $this->firm->id)
            ->when(! $reach['firm'], fn ($query) => $query->whereIn('id', [...$reach['companies'],
                ...Workplace::withTrashed()->whereIn('id', $reach['workplaces'])->pluck('company_id')->all()]))
            ->orderBy('company_no')->get(['id', 'company_no', 'short_name', 'deleted_at']);
    }

    /**
     * @return Collection<int, Workplace>
     */
    #[Computed]
    public function workplaces(): Collection
    {
        $reach = AuditLog::reach(Auth::user(), $this->firm);

        return Workplace::withTrashed()->whereIn('company_id', $this->companies->pluck('id'))
            ->when($this->company !== '', fn ($query) => $query->where('company_id', (int) $this->company))
            ->when(! $reach['firm'], fn ($query) => $query->where(fn ($query) => $query
                ->whereIn('company_id', $reach['companies'])->orWhereIn('id', $reach['workplaces'])))
            ->with('company:id,short_name')->orderBy('company_id')->orderBy('workplace_no')->get();
    }

    /**
     * Title of the record filter (?kayit=personel:12), if any.
     */
    #[Computed]
    public function recordLabel(): ?string
    {
        if (! str_contains($this->record, ':')) {
            return null;
        }

        [$type, $id] = explode(':', $this->record, 2);

        return match ($type) {
            'personel' => ($employee = Employee::withTrashed()->where('firm_id', $this->firm->id)->find((int) $id)) ? 'Personel: '.$employee->fullName() : null,
            'isyeri' => ($workplace = Workplace::withTrashed()->find((int) $id)) ? 'İşyeri: '.$workplace->branch_name : null,
            'sirket' => ($company = Company::withTrashed()->where('firm_id', $this->firm->id)->find((int) $id)) ? 'Şirket: '.$company->short_name : null,
            default => null,
        };
    }

    public static function moduleColor(string $group): string
    {
        return match ($group) {
            'employee' => 'blue', 'workplace', 'company' => 'navy', 'definition' => 'green', 'import', 'system' => 'amber',
            'access', 'security' => 'red', default => 'gray',
        };
    }

    /**
     * "Chrome · Windows" from a user agent.
     */
    public static function browser(?string $agent): string
    {
        if (blank($agent)) {
            return '—';
        }

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge', str_contains($agent, 'OPR/') => 'Opera', str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome', str_contains($agent, 'Safari/') => 'Safari', default => 'Tarayıcı',
        };
        $system = match (true) {
            str_contains($agent, 'Windows') => 'Windows', str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS', str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux', default => '',
        };

        return trim($browser.($system ? ' · '.$system : ''));
    }

    /**
     * Panel link of the record a log is about (if it still exists and is a panel record).
     */
    public static function recordUrl(AuditLog $log): ?string
    {
        $subject = $log->subject_id ? $log->subject : null;

        return match (true) {
            $subject instanceof Employee && ! $subject->trashed() => route('employees.show', $subject),
            $subject instanceof Workplace && ! $subject->trashed() => route('workplaces.show', $subject),
            $subject instanceof Company && ! $subject->trashed() => route('companies.show', $subject),
            default => null,
        };
    }
}; ?>

<div>
    @php
        $hasFilters = collect($this->filters())->filter(fn ($value) => $value !== '')->isNotEmpty();
        $portals = ['panel' => 'Panel', 'admin' => 'Admin', 'landing' => 'Web sitesi'];
    @endphp

    <x-panel.page-header :crumbs="['Yönetim' => null, 'İşlem Geçmişi' => null, $this->firm->name => null]" title="İşlem Geçmişi"
        subtitle="Panelde yapılan tüm işlemlerin değiştirilemez kaydı. Şirket, şube, kullanıcı ve kayıt bazında süzülebilir; KVKK ve iç denetim için dışa aktarılabilir.">
        <x-slot:actions>
            <flux:button icon="arrow-down-tray" :href="route('audit.export', array_filter([
                'q' => $q, 'kullanici' => $user, 'sirket' => $company, 'sube' => $workplace, 'modul' => $module,
                'baslangic' => $from, 'bitis' => $to, 'kayit' => $record,
            ]))">Excel olarak indir</flux:button>
        </x-slot:actions>
    </x-panel.page-header>

    @if ($this->recordLabel)
        <x-panel.alert variant="info" class="mb-4" icon="funnel">
            Yalnızca <strong>{{ $this->recordLabel }}</strong> kaydının geçmişi gösteriliyor.
            <x-slot:actions>
                <flux:button size="sm" wire:click="$set('record', '')">Tüm kayıtlar</flux:button>
            </x-slot:actions>
        </x-panel.alert>
    @endif

    <div class="mb-[18px] grid grid-cols-2 gap-3 sm:gap-3.5 xl:grid-cols-4">
        <x-panel.stat :value="number_format($this->stats['total'], 0, ',', '.')" label="İşlem" color="navy" />
        <x-panel.stat :value="number_format($this->stats['changes'], 0, ',', '.')" label="Kayıt değişikliği" color="blue" />
        <x-panel.stat :value="$this->stats['users']" label="Kullanıcı" color="mint" />
        <x-panel.stat :value="$this->stats['last'] ?? '—'" label="Son işlem" color="gray" />
    </div>

    <x-panel.table :paginate="$this->logs" min-width="980px">
        <x-slot:toolbar>
            <div class="grid w-full gap-2.5 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.4fr)_repeat(4,minmax(0,1fr))]">
                <x-panel.search wire:model.live.debounce.400ms="q" placeholder="İşlem, kayıt, değer veya IP ara..." class="!max-w-none" />
                @php $select = 'h-[38px] w-full rounded-[9px] border-[1.5px] border-[#E8EDF3] bg-field ps-3 pe-8 text-[13px] font-semibold text-ink-2 outline-none focus:border-brand'; @endphp
                <select wire:model.live="company" aria-label="Şirket" class="{{ $select }}">
                    <option value="">Tüm şirketler</option>
                    @foreach ($this->companies as $option)
                        <option value="{{ $option->id }}">{{ $option->company_no }} · {{ $option->short_name }}{{ $option->deleted_at ? ' (silindi)' : '' }}</option>
                    @endforeach
                </select>
                <select wire:model.live="workplace" aria-label="Şube" class="{{ $select }}">
                    <option value="">Tüm şubeler</option>
                    @foreach ($this->workplaces as $option)
                        <option value="{{ $option->id }}">{{ $company === '' ? $option->company?->short_name.' · ' : '' }}{{ $option->branch_name }}{{ $option->deleted_at ? ' (silindi)' : '' }}</option>
                    @endforeach
                </select>
                <select wire:model.live="user" aria-label="Kullanıcı" class="{{ $select }}">
                    <option value="">Tüm kullanıcılar</option>
                    @foreach ($this->users as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="module" aria-label="Modül" class="{{ $select }}">
                    <option value="">Tüm modüller</option>
                    @foreach ($this::MODULES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex w-full flex-wrap items-center gap-2.5">
                <label class="flex items-center gap-2 text-[12.5px] font-semibold text-muted">
                    Başlangıç <input type="date" wire:model.live="from" class="h-[34px] rounded-[9px] border-[1.5px] border-[#E8EDF3] bg-field px-2.5 text-[13px] text-ink-2 outline-none focus:border-brand" />
                </label>
                <label class="flex items-center gap-2 text-[12.5px] font-semibold text-muted">
                    Bitiş <input type="date" wire:model.live="to" class="h-[34px] rounded-[9px] border-[1.5px] border-[#E8EDF3] bg-field px-2.5 text-[13px] text-ink-2 outline-none focus:border-brand" />
                </label>
                @if ($hasFilters)
                    <button type="button" wire:click="clearFilters" class="text-[12.5px] font-bold text-brand hover:text-mint">Filtreleri temizle</button>
                @endif
                <span class="ms-auto text-[12.5px] font-semibold text-muted-2">{{ number_format($this->logs->total(), 0, ',', '.') }} kayıt</span>
            </div>
        </x-slot:toolbar>

        <x-slot:head>
            <x-panel.th>Zaman</x-panel.th>
            <x-panel.th>Kullanıcı</x-panel.th>
            <x-panel.th>Modül</x-panel.th>
            <x-panel.th>İşlem</x-panel.th>
            <x-panel.th>Şirket / Şube</x-panel.th>
            <x-panel.th>Detay</x-panel.th>
            <x-panel.th class="w-10"></x-panel.th>
        </x-slot:head>

        @foreach ($this->logs as $log)
            @php
                $group = $log->event->group();
                $changes = $log->changes();
                $open = $expanded === $log->id;
                $source = ($log->properties['source'] ?? null) === 'excel' ? 'Excel: '.($log->properties['import_file'] ?? '') : null;
            @endphp
            <tr wire:key="log-{{ $log->id }}" wire:click="toggle({{ $log->id }})" @class(['cursor-pointer border-t border-line-4 transition-colors hover:bg-row-hover', 'bg-[#F7FAFD]' => $open])>
                <x-panel.td :sub="$log->created_at->format('H:i:s')">{{ $log->created_at->format('d.m.Y') }}</x-panel.td>
                <x-panel.td strong :sub="$log->user?->email">{{ $log->user?->name ?? 'Sistem' }}</x-panel.td>
                <x-panel.td><x-panel.badge :color="$this::moduleColor($group)" :dot="false">{{ $this::MODULES[$group] ?? (AuditEvent::groups()[$group] ?? $group) }}</x-panel.badge></x-panel.td>
                <td class="max-w-[320px] px-3.5 py-3.5">
                    <div class="text-[13px] font-bold text-ink">{{ $log->event->label() }}</div>
                    <div class="truncate text-xs text-muted-2" title="{{ $log->description }}">{{ $log->description }}</div>
                </td>
                <x-panel.td :sub="$log->workplace?->branch_name">{{ $log->company?->short_name ?? '—' }}</x-panel.td>
                <td class="max-w-[280px] px-3.5 py-3.5 text-[12.5px] text-ink-2">
                    @if ($changes !== [])
                        <span class="font-bold">{{ $changes[0]['label'] }}:</span>
                        <span class="text-muted-2">{{ \Illuminate\Support\Str::limit($changes[0]['old'], 24) }}</span> →
                        <span class="font-semibold text-ink">{{ \Illuminate\Support\Str::limit($changes[0]['new'], 24) }}</span>
                        @if (count($changes) > 1)
                            <span class="ms-1 rounded-md bg-seg px-1.5 py-0.5 text-[11px] font-bold text-muted">+{{ count($changes) - 1 }}</span>
                        @endif
                    @elseif ($source)
                        <span class="text-muted">{{ $source }}</span>
                    @else
                        <span class="text-faint">{{ $this::browser($log->user_agent) }} · {{ $log->ip_address ?? '—' }}</span>
                    @endif
                </td>
                <td class="pe-4 text-faint">
                    <flux:icon.chevron-down variant="micro" :class="$open ? 'size-4 rotate-180 transition' : 'size-4 transition'" />
                </td>
            </tr>

            @if ($open)
                <tr wire:key="log-detail-{{ $log->id }}" class="bg-[#F7FAFD]">
                    <td colspan="7" class="px-[18px] pt-1 pb-5">
                        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_280px]">
                            <div class="overflow-hidden rounded-xl border border-line bg-white">
                                <div class="border-b border-line-3 px-4 py-2.5 text-[12px] font-bold tracking-[0.04em] text-muted">
                                    {{ $changes !== [] ? 'DEĞİŞİKLİKLER ('.count($changes).')' : 'AYRINTI' }}
                                </div>
                                @if ($changes !== [])
                                    <table class="w-full text-[13px]">
                                        <thead>
                                            <tr class="bg-head text-start text-[11px] font-bold tracking-[0.06em] text-muted-2 uppercase">
                                                <th class="px-4 py-2 text-start">Alan</th><th class="px-4 py-2 text-start">Önceki</th><th class="px-4 py-2 text-start">Yeni</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($changes as $change)
                                                <tr class="border-t border-line-4 align-top">
                                                    <td class="px-4 py-2 font-bold text-ink-2">{{ $change['label'] }}</td>
                                                    <td class="px-4 py-2 break-words text-st-red/80 line-through decoration-st-red/30">{{ $change['old'] }}</td>
                                                    <td class="px-4 py-2 font-semibold break-words text-st-green">{{ $change['new'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <p class="px-4 py-3 text-[13px] text-ink-2">{{ $log->description }}</p>
                                @endif
                            </div>

                            <dl class="space-y-2 rounded-xl border border-line bg-white px-4 py-3 text-[12.5px]">
                                @foreach (array_filter([
                                    'Tarih' => $log->created_at->format('d.m.Y H:i:s'),
                                    'Kullanıcı' => $log->user ? $log->user->name.' ('.$log->user->email.')' : 'Sistem',
                                    'Kaynak' => $source ?? (isset($log->properties['impersonated_by']) ? null : ($portals[$log->portal] ?? $log->portal)),
                                    'Destek görünümü' => isset($log->properties['impersonated_by']) ? (User::find($log->properties['impersonated_by'])?->name ?? 'HRD').' (kullanıcı adına)' : null,
                                    'IP adresi' => $log->ip_address,
                                    'Tarayıcı' => $this::browser($log->user_agent),
                                    'Şirket' => $log->company?->short_name,
                                    'Şube' => $log->workplace?->branch_name,
                                ]) as $label => $value)
                                    <div class="flex justify-between gap-3">
                                        <dt class="shrink-0 text-muted-2">{{ $label }}</dt>
                                        <dd class="text-end font-semibold break-words text-ink">{{ $value }}</dd>
                                    </div>
                                @endforeach
                                @if ($url = $this::recordUrl($log))
                                    <a href="{{ $url }}" wire:navigate class="mt-1 block text-end font-bold text-brand hover:text-mint">Kayda git ›</a>
                                @endif
                            </dl>
                        </div>
                    </td>
                </tr>
            @endif
        @endforeach

        <x-slot:empty>
            @if ($this->logs->isEmpty())
                <x-panel.empty icon="clock" :title="$hasFilters ? 'Sonuç bulunamadı' : 'Henüz işlem yok'">
                    {{ $hasFilters ? 'Filtrelere uyan işlem kaydı yok.' : 'Şirket, işyeri, personel ve tanımlarda yapılan işlemler burada listelenir.' }}
                </x-panel.empty>
            @endif
        </x-slot:empty>
    </x-panel.table>
</div>
