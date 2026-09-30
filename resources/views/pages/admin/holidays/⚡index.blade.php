<?php

use App\Enums\AuditEvent;
use App\Models\Holiday;
use App\Payroll\Calendar\WorkCalendar;
use App\Support\Audit;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Çalışma Takvimi')] class extends Component {
    #[Url(as: 'yil')]
    public string $tab = '';

    public ?int $editingId = null;

    public string $date = '';

    public string $name = '';

    public string $type = 'religious';

    public bool $isHalfDay = false;

    public function mount(): void
    {
        $this->authorize('manage-settings');
        $this->tab = $this->tab ?: (string) now()->year;
    }

    #[Computed]
    public function year(): int
    {
        return (int) $this->tab;
    }

    /**
     * Years shown as tabs: previous, current and next year, plus any year that has holidays.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function years(): array
    {
        $years = Holiday::query()->toBase()->selectRaw('distinct '.$this->yearExpression().' as y')->pluck('y')
            ->map(fn ($year) => (int) $year)
            ->merge([now()->year - 1, now()->year, now()->year + 1, $this->year])
            ->unique()->sort()->values();

        return $years->mapWithKeys(fn ($year) => [(string) $year => (string) $year])->all();
    }

    /**
     * @return Collection<int, Holiday>
     */
    #[Computed]
    public function holidays(): Collection
    {
        return app(WorkCalendar::class)->year($this->year);
    }

    public function generateFixed(WorkCalendar $calendar): void
    {
        $this->authorize('manage-settings');

        $added = $calendar->generateFixed($this->year);

        unset($this->holidays, $this->years);
        Flux::toast(variant: 'success', text: $added ? "{$added} sabit tatil eklendi." : 'Sabit tatiller zaten tanımlı.');
    }

    public function create(): void
    {
        $this->reset('editingId', 'name', 'isHalfDay');
        $this->type = 'religious';
        $this->date = Carbon::create($this->year)->toDateString();
        $this->resetValidation();

        Flux::modal('holiday')->show();
    }

    public function edit(int $id): void
    {
        $holiday = Holiday::findOrFail($id);

        $this->editingId = $holiday->id;
        $this->date = $holiday->date->toDateString();
        $this->name = $holiday->name;
        $this->type = $holiday->type;
        $this->isHalfDay = $holiday->is_half_day;
        $this->resetValidation();

        Flux::modal('holiday')->show();
    }

    public function save(): void
    {
        $this->authorize('manage-settings');

        $this->validate([
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255',
                Rule::unique('holidays', 'name')->where(fn ($query) => $query->whereDate('date', $this->date))->ignore($this->editingId)],
            'type' => ['required', Rule::in(array_keys(Holiday::TYPES))],
            'isHalfDay' => ['boolean'],
        ], ['name.unique' => 'Bu tarihte aynı adlı tatil zaten var.'], ['date' => 'Tarih', 'name' => 'Ad', 'type' => 'Tür']);

        $holiday = $this->editingId ? Holiday::findOrFail($this->editingId) : new Holiday;
        $holiday->fill(['date' => $this->date, 'name' => $this->name, 'type' => $this->type, 'is_half_day' => $this->isHalfDay])->save();

        Audit::log(AuditEvent::SystemSettingsChanged, "Resmi tatil kaydedildi: {$holiday->date->format('d.m.Y')} {$holiday->name}");

        $this->tab = (string) $holiday->date->year;
        unset($this->holidays, $this->years);
        Flux::modal('holiday')->close();
        Flux::toast(variant: 'success', text: 'Tatil kaydedildi.');
    }

    public function delete(int $id): void
    {
        $this->authorize('manage-settings');

        $holiday = Holiday::findOrFail($id);
        $holiday->delete();

        Audit::log(AuditEvent::SystemSettingsChanged, "Resmi tatil silindi: {$holiday->date->format('d.m.Y')} {$holiday->name}");
        unset($this->holidays);
        Flux::toast(variant: 'success', text: 'Tatil silindi.');
    }

    private function yearExpression(): string
    {
        return \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y', date)" : 'YEAR(date)';
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Çalışma Takvimi</flux:heading>
            <flux:text class="mt-1">
                Resmi tatiller (2429 s.K.). Bayram mesaisi, genel tatil ücreti ve eksik gün hesabında kullanılır.
                Yarım günler (arifeler ve 28 Ekim) saat 13:00'ten itibaren tatildir.
            </flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button icon="calendar-days" wire:click="generateFixed">{{ $this->year }} sabit tatillerini oluştur</flux:button>
            <flux:button variant="primary" icon="plus" wire:click="create">Tatil Ekle</flux:button>
        </div>
    </div>

    <x-tabs :active="$tab" :tabs="$this->years" />

    @unless ($this->holidays->contains('type', 'religious'))
        <flux:callout icon="exclamation-triangle" color="amber" heading="{{ $this->year }} için dini bayramlar girilmemiş"
            text="Ramazan ve Kurban Bayramı tarihleri her yıl değişir; Diyanet takvimine göre 'Tatil Ekle' ile girin." />
    @endunless

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Tarih</flux:table.column>
            <flux:table.column>Tatil</flux:table.column>
            <flux:table.column>Tür</flux:table.column>
            <flux:table.column>Süre</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->holidays as $holiday)
                <flux:table.row :key="$holiday->id">
                    <flux:table.cell class="whitespace-nowrap">
                        {{ $holiday->date->format('d.m.Y') }}
                        <span class="text-zinc-500">{{ $holiday->date->locale('tr')->dayName }}</span>
                    </flux:table.cell>
                    <flux:table.cell variant="strong">{{ $holiday->name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$holiday->type === 'religious' ? 'violet' : 'sky'" inset="top bottom">{{ Holiday::TYPES[$holiday->type] ?? $holiday->type }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $holiday->is_half_day ? 'Yarım gün (13:00)' : 'Tam gün' }}</flux:table.cell>
                    <flux:table.cell align="end" class="whitespace-nowrap">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $holiday->id }})" />
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $holiday->id }})" wire:confirm="{{ $holiday->name }} silinsin mi?" />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="py-10 text-center text-zinc-500">
                        {{ $this->year }} için tatil tanımlı değil. "Sabit tatilleri oluştur" ile başlayın.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="holiday" class="md:w-[32rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Tatili Düzenle' : 'Tatil Ekle' }}</flux:heading>
            <flux:input wire:model="date" type="date" label="Tarih" required />
            <flux:input wire:model="name" label="Ad" placeholder="Ör. Ramazan Bayramı 1. gün" required />
            <flux:select wire:model="type" label="Tür">
                @foreach (Holiday::TYPES as $key => $label)
                    <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:checkbox wire:model="isHalfDay" label="Yarım gün (13:00'ten itibaren)" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Kaydet</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
