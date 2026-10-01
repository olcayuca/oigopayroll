<?php

use App\System\Backups;
use App\System\ErrorLog;
use App\System\HealthChecks;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Sistem Sağlığı')] class extends Component {
    #[Url(as: 'sekme')]
    public string $tab = 'durum';

    public function mount(): void
    {
        $this->authorize('manage-settings');
    }

    /**
     * @return array<string, list<array{label: string, status: string, value: string, hint: string|null}>>
     */
    #[Computed]
    public function checks(): array
    {
        return app(HealthChecks::class)->run();
    }

    /**
     * @return Collection<int, array{name: string, size: int, created_at: \Illuminate\Support\Carbon}>
     */
    #[Computed]
    public function backups(): Collection
    {
        return app(Backups::class)->all();
    }

    /**
     * @return list<array{at: \Illuminate\Support\Carbon|null, level: string, message: string}>
     */
    #[Computed]
    public function errors(): array
    {
        return app(ErrorLog::class)->recent();
    }

    public function backupNow(Backups $backups): void
    {
        $this->authorize('manage-settings');

        try {
            $result = $backups->create();
        } catch (Throwable $e) {
            unset($this->checks);
            Flux::toast(variant: 'danger', text: 'Yedek alınamadı: '.mb_substr($e->getMessage(), 0, 200));

            return;
        }

        unset($this->backups, $this->checks);
        Flux::toast(variant: 'success', text: "Yedek alındı: {$result['file']}");
    }

    public function refresh(): void
    {
        unset($this->checks, $this->errors, $this->backups);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Sistem Sağlığı</flux:heading>
            <flux:text class="mt-1">Sunucu, veritabanı, arka plan işleri ve yedeklerin durumu.</flux:text>
        </div>
        <flux:button icon="arrow-path" wire:click="refresh">Yenile</flux:button>
    </div>

    @php($overall = HealthChecks::overall($this->checks))
    @php($problems = collect($this->checks)->flatten(1)->where('status', '!=', HealthChecks::OK)->count())

    <x-tabs :active="$tab" :tabs="['durum' => 'Durum', 'yedekler' => 'Yedekler', 'hatalar' => 'Hatalar']"
        :counts="['durum' => $problems, 'hatalar' => count($this->errors)]" />

    @if ($tab === 'durum')
        @if ($overall === HealthChecks::OK)
            <flux:callout icon="check-circle" color="green" heading="Tüm kontroller başarılı" />
        @else
            <flux:callout icon="exclamation-triangle" :color="$overall === HealthChecks::FAIL ? 'red' : 'amber'"
                heading="{{ $problems }} kontrol dikkat gerektiriyor" text="Açıklamalar ilgili satırların altında." />
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ($this->checks as $group => $items)
                <flux:card class="space-y-3">
                    <flux:heading>{{ $group }}</flux:heading>
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                        @foreach ($items as $check)
                            <div class="py-2">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        @if ($check['status'] === HealthChecks::OK)
                                            <flux:icon.check-circle variant="mini" class="text-green-500" />
                                        @elseif ($check['status'] === HealthChecks::WARN)
                                            <flux:icon.exclamation-triangle variant="mini" class="text-amber-500" />
                                        @else
                                            <flux:icon.x-circle variant="mini" class="text-red-500" />
                                        @endif
                                        <span class="text-sm">{{ $check['label'] }}</span>
                                    </div>
                                    <span class="text-end text-sm font-medium">{{ $check['value'] }}</span>
                                </div>
                                @if ($check['hint'])
                                    <flux:text size="sm" class="mt-1 ps-7">{{ $check['hint'] }}</flux:text>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif

    @if ($tab === 'yedekler')
        <div class="flex flex-wrap items-center justify-between gap-4">
            <flux:text>
                Yedekler her gece 02:30'da otomatik alınır (zamanlayıcı çalışıyorsa) ve son {{ config('backup.keep') }} yedek saklanır.
                Dosyalar sunucuda <code>storage/app/private/{{ config('backup.path') }}</code> klasöründedir; kişisel veri içerdikleri için panelden indirilemez.
            </flux:text>
            <flux:button variant="primary" icon="circle-stack" wire:click="backupNow" wire:loading.attr="disabled">Şimdi yedek al</flux:button>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Dosya</flux:table.column>
                <flux:table.column>Tarih</flux:table.column>
                <flux:table.column align="end">Boyut</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->backups as $backup)
                    <flux:table.row :key="$backup['name']">
                        <flux:table.cell class="font-mono text-sm">{{ $backup['name'] }}</flux:table.cell>
                        <flux:table.cell>{{ $backup['created_at']->format('d.m.Y H:i') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ HealthChecks::bytes($backup['size']) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="py-10 text-center text-zinc-500">Henüz yedek yok.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    @if ($tab === 'hatalar')
        <flux:text>Uygulama günlüğündeki son hatalar (yalnızca ilk satır). Ayrıntılar sunucuda <code>storage/logs</code> klasöründedir.</flux:text>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Tarih</flux:table.column>
                <flux:table.column>Seviye</flux:table.column>
                <flux:table.column>Mesaj</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->errors as $index => $error)
                    <flux:table.row :key="$index">
                        <flux:table.cell class="whitespace-nowrap">{{ $error['at']?->format('d.m.Y H:i:s') }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" color="red" inset="top bottom">{{ $error['level'] }}</flux:badge></flux:table.cell>
                        <flux:table.cell class="whitespace-normal break-all font-mono text-xs">{{ $error['message'] }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="py-10 text-center text-zinc-500">Kayıtlı hata yok.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif
</div>
