<?php

use App\Enums\FirmStatus;
use App\Enums\HazardClass;
use App\Enums\UserType;
use App\Exports\ExportType;
use App\Models\Company;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Raporlar')] class extends Component {
    #[Url(as: 'sekme')]
    public string $tab = 'ozet';

    public string $auditFrom = '';

    public string $auditTo = '';

    public function mount(): void
    {
        $this->authorize('manage-settings');

        $this->auditFrom = now()->subDays(30)->toDateString();
        $this->auditTo = now()->toDateString();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function totals(): array
    {
        return [
            'firms' => Firm::where('status', FirmStatus::Active)->count(),
            'pending' => Firm::where('status', FirmStatus::Pending)->count(),
            'unassigned' => Firm::where('status', FirmStatus::Active)->whereNull('specialist_id')->count(),
            'companies' => Company::whereHas('firm', fn ($query) => $query->where('status', FirmStatus::Active))->count(),
            'workplaces' => Workplace::whereHas('company.firm', fn ($query) => $query->where('status', FirmStatus::Active))->count(),
            'clients' => User::where('type', UserType::ClientUser)->where('is_active', true)->count(),
            'staff' => User::where('type', '!=', UserType::ClientUser)->where('is_active', true)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function firmsByStatus(): array
    {
        $counts = Firm::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(FirmStatus::cases())->mapWithKeys(fn (FirmStatus $status) => [$status->label() => (int) ($counts[$status->value] ?? 0)])->all();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function workplacesByHazard(): array
    {
        $counts = Workplace::query()->toBase()->whereNull('deleted_at')->selectRaw('hazard_class, count(*) as total')->groupBy('hazard_class')->pluck('total', 'hazard_class');

        return collect(HazardClass::cases())->mapWithKeys(fn (HazardClass $class) => [$class->label() => (int) ($counts[$class->value] ?? 0)])->all();
    }

    /**
     * Top provinces by workplace count.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function workplacesByProvince(): array
    {
        return Workplace::query()->toBase()
            ->leftJoin('provinces', 'provinces.id', '=', 'workplaces.province_id')
            ->whereNull('workplaces.deleted_at')
            ->selectRaw('coalesce(provinces.name, workplaces.province_name, ?) as province, count(*) as total', ['Belirtilmemiş'])
            ->groupBy('province')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'province')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * New records per month over the last six months.
     *
     * @return list<array{month: string, firms: int, companies: int, workplaces: int}>
     */
    #[Computed]
    public function growth(): array
    {
        $start = now()->startOfMonth()->subMonths(5);
        $expression = DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";
        $count = fn (string $table) => DB::table($table)->where('created_at', '>=', $start)
            ->when($table !== 'firms', fn ($query) => $query->whereNull('deleted_at'))
            ->selectRaw("{$expression} as ym, count(*) as total")->groupBy('ym')->pluck('total', 'ym');

        [$firms, $companies, $workplaces] = [$count('firms'), $count('companies'), $count('workplaces')];

        return collect(range(0, 5))->map(function (int $offset) use ($start, $firms, $companies, $workplaces) {
            $month = $start->copy()->addMonths($offset);
            $key = $month->format('Y-m');

            return [
                'month' => $month->locale('tr')->translatedFormat('F Y'),
                'firms' => (int) ($firms[$key] ?? 0),
                'companies' => (int) ($companies[$key] ?? 0),
                'workplaces' => (int) ($workplaces[$key] ?? 0),
            ];
        })->all();
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">Raporlar</flux:heading>
        <flux:text class="mt-1">Sistem geneli özet ve Excel dışa aktarma. Her dışa aktarma işlem kayıtlarına yazılır.</flux:text>
    </div>

    <x-tabs :active="$tab" :tabs="['ozet' => 'Özet', 'disa-aktarma' => 'Dışa Aktarma']" />

    @if ($tab === 'ozet')
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Aktif firma', $this->totals['firms'], null],
                ['Onay bekleyen firma', $this->totals['pending'], route('admin.firms.index')],
                ['Şirket', $this->totals['companies'], null],
                ['İşyeri', $this->totals['workplaces'], null],
                ['Aktif müşteri kullanıcısı', $this->totals['clients'], null],
                ['Aktif HRD kullanıcısı', $this->totals['staff'], null],
                ['Sorumlu uzmanı olmayan firma', $this->totals['unassigned'], route('admin.specialists.index', ['sekme' => 'firmalar', 'filtre' => 'atanmamis'])],
            ] as [$label, $value, $link])
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:text>{{ $label }}</flux:text>
                    <div class="mt-2 text-3xl font-semibold">
                        @if ($link && $value > 0)
                            <a href="{{ $link }}" wire:navigate class="hover:underline">{{ number_format($value, 0, ',', '.') }}</a>
                        @else
                            {{ number_format($value, 0, ',', '.') }}
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            @foreach ([
                'Firma durumları' => $this->firmsByStatus,
                'İşyeri tehlike sınıfları' => $this->workplacesByHazard,
                'En çok işyeri olan iller' => $this->workplacesByProvince,
            ] as $heading => $rows)
                <flux:card class="space-y-3">
                    <flux:heading>{{ $heading }}</flux:heading>
                    @php($max = max([1, ...array_values($rows)]))
                    @forelse ($rows as $label => $value)
                        <div>
                            <div class="flex justify-between text-sm"><span>{{ $label }}</span><span class="font-medium">{{ $value }}</span></div>
                            <div class="mt-1 h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-700">
                                <div class="h-1.5 rounded-full bg-sky-500" style="width: {{ round($value / $max * 100) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <flux:text size="sm">Veri yok.</flux:text>
                    @endforelse
                </flux:card>
            @endforeach
        </div>

        <flux:card class="space-y-3">
            <flux:heading>Son 6 ayda yeni kayıtlar</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Ay</flux:table.column>
                    <flux:table.column align="end">Firma</flux:table.column>
                    <flux:table.column align="end">Şirket</flux:table.column>
                    <flux:table.column align="end">İşyeri</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->growth as $row)
                        <flux:table.row>
                            <flux:table.cell>{{ $row['month'] }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $row['firms'] }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $row['companies'] }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $row['workplaces'] }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    @if ($tab === 'disa-aktarma')
        <flux:callout icon="shield-check" text="Dışa aktarılan dosyalarda işyeri şifreleri ve kimlik bilgisi alanları yer almaz. Dosyalar kişisel veri içerebilir; paylaşırken dikkat edin." />

        <div class="grid gap-4 md:grid-cols-2">
            @foreach (ExportType::adminReports() as $type)
                <flux:card class="flex flex-col gap-3">
                    <div>
                        <flux:heading>{{ $type->label() }}</flux:heading>
                        <flux:text size="sm">{{ $type->description() }}</flux:text>
                    </div>
                    @if ($type === ExportType::AuditLogs)
                        <form method="GET" action="{{ route('admin.reports.download', $type->value) }}" class="mt-auto flex flex-wrap items-end gap-2">
                            <flux:input type="date" name="baslangic" wire:model="auditFrom" label="Başlangıç" size="sm" required />
                            <flux:input type="date" name="bitis" wire:model="auditTo" label="Bitiş" size="sm" required />
                            <flux:button type="submit" size="sm" icon="arrow-down-tray">Excel İndir</flux:button>
                        </form>
                    @else
                        <div class="mt-auto">
                            <flux:button size="sm" icon="arrow-down-tray" :href="route('admin.reports.download', $type->value)">Excel İndir</flux:button>
                        </div>
                    @endif
                </flux:card>
            @endforeach
        </div>
    @endif
</div>
