<?php

use App\Enums\AuditEvent;
use App\Support\Audit;
use App\Models\Company;
use App\Models\RiskClass;
use App\Models\Sector;
use App\Models\Setting;
use App\Models\Workplace;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Sistem Ayarları')] class extends Component {
    /**
     * Editable reference lists: key => [model, label, usage model, usage column].
     */
    private const LISTS = [
        'sectors' => [Sector::class, 'Sektör', Company::class, 'sector_id'],
        'risk-classes' => [RiskClass::class, 'Risk Sınıfı', Workplace::class, 'risk_class_id'],
    ];

    /**
     * Keys stored in the settings table, with labels and validation.
     */
    private const GENERAL = [
        'site.name' => ['Site / uygulama adı', ['nullable', 'string', 'max:100']],
        'company.title' => ['HRD şirket unvanı', ['nullable', 'string', 'max:255']],
        'contact.phone' => ['Telefon', ['nullable', 'string', 'max:30']],
        'contact.email' => ['E-posta', ['nullable', 'email', 'max:255']],
        'contact.address' => ['Adres', ['nullable', 'string', 'max:500']],
        'support.email' => ['Destek e-posta adresi (yeni talepler bu adrese de gönderilir)', ['nullable', 'email', 'max:255']],
    ];

    #[Url(as: 'sekme', except: 'genel')]
    public string $tab = 'genel';

    /** @var array<string, string|null> */
    public array $general = [];

    public string $newItem = '';

    public ?int $editingItemId = null;

    public string $editingItemName = '';

    public function mount(): void
    {
        $this->authorize('manage-settings');

        foreach (array_keys(self::GENERAL) as $key) {
            $value = Setting::get($key);
            $this->general[$this->field($key)] = is_scalar($value) ? (string) $value : null;
        }
    }

    public function updatedTab(): void
    {
        $this->reset('newItem', 'editingItemId', 'editingItemName');
        $this->resetValidation();
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    #[Computed]
    public function generalFields(): array
    {
        $fields = [];

        foreach (self::GENERAL as $key => $definition) {
            $fields[$this->field($key)] = $definition;
        }

        return $fields;
    }

    public function saveGeneral(): void
    {
        $this->authorize('manage-settings');

        $rules = $attributes = [];

        foreach (self::GENERAL as $key => [$label, $keyRules]) {
            $rules['general.'.$this->field($key)] = $keyRules;
            $attributes['general.'.$this->field($key)] = $label;
        }

        $this->validate($rules, [], $attributes);

        $values = [];

        foreach (array_keys(self::GENERAL) as $key) {
            $value = trim((string) ($this->general[$this->field($key)] ?? ''));
            $values[$key] = $value === '' ? null : $value;
        }

        Setting::putMany($values);
        Audit::log(AuditEvent::SystemSettingsChanged, 'Genel ayarlar güncellendi', null, ['keys' => array_keys($values)]);

        Flux::toast(variant: 'success', text: 'Genel ayarlar kaydedildi.');
    }

    /**
     * @return Collection<int, Sector|RiskClass>|null
     */
    #[Computed]
    public function items(): ?Collection
    {
        if (! isset(self::LISTS[$this->tab])) {
            return null;
        }

        [$model, , $usageModel, $usageColumn] = self::LISTS[$this->tab];

        $usage = $usageModel::query()->toBase()
            ->selectRaw("{$usageColumn} as ref, count(*) as total")
            ->whereNotNull($usageColumn)
            ->groupBy($usageColumn)
            ->pluck('total', 'ref');

        return $model::query()->orderBy('name')->get()
            ->each(fn ($item) => $item->setAttribute('usage', (int) ($usage[$item->id] ?? 0)));
    }

    public function addItem(): void
    {
        $this->authorize('manage-settings');
        [$model, $label] = self::LISTS[$this->tab];

        $this->validate(
            ['newItem' => ['required', 'string', 'max:255', Rule::unique((new $model)->getTable(), 'name')]],
            [], ['newItem' => $label],
        );

        $model::create(['name' => trim($this->newItem), 'is_active' => true]);
        Audit::log(AuditEvent::SystemSettingsChanged, "{$label} eklendi: ".trim($this->newItem));

        $this->reset('newItem');
        unset($this->items);
        Flux::toast(variant: 'success', text: "{$label} eklendi.");
    }

    public function startEditing(int $id): void
    {
        [$model] = self::LISTS[$this->tab];

        $this->editingItemId = $id;
        $this->editingItemName = $model::findOrFail($id)->name;
    }

    public function saveItem(): void
    {
        $this->authorize('manage-settings');
        [$model, $label] = self::LISTS[$this->tab];

        $this->validate(
            ['editingItemName' => ['required', 'string', 'max:255', Rule::unique((new $model)->getTable(), 'name')->ignore($this->editingItemId)]],
            [], ['editingItemName' => $label],
        );

        $model::findOrFail($this->editingItemId)->update(['name' => trim($this->editingItemName)]);
        Audit::log(AuditEvent::SystemSettingsChanged, "{$label} adı değişti: ".trim($this->editingItemName));

        $this->reset('editingItemId', 'editingItemName');
        unset($this->items);
    }

    public function toggleItem(int $id): void
    {
        $this->authorize('manage-settings');
        [$model] = self::LISTS[$this->tab];

        $item = $model::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        Audit::log(AuditEvent::SystemSettingsChanged, $item->name.($item->is_active ? ' aktifleştirildi' : ' pasife alındı'));

        unset($this->items);
    }

    public function deleteItem(int $id): void
    {
        $this->authorize('manage-settings');
        [$model, $label, $usageModel, $usageColumn] = self::LISTS[$this->tab];

        if ($usageModel::withTrashed()->where($usageColumn, $id)->exists()) {
            Flux::toast(variant: 'danger', text: "Kullanımdaki {$label} silinemez; pasife alabilirsiniz.");

            return;
        }

        $deleted = $model::findOrFail($id);
        $deleted->delete();
        Audit::log(AuditEvent::SystemSettingsChanged, "{$label} silindi: {$deleted->name}");

        unset($this->items);
        Flux::toast(variant: 'success', text: "{$label} silindi.");
    }


    private function field(string $key): string
    {
        return str_replace('.', '_', $key);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">Sistem Ayarları</flux:heading>
        <flux:text class="mt-1">Genel bilgiler ve seçim listeleri. Güvenlik kayıtları Güvenlik sayfasındadır.</flux:text>
    </div>

    <x-tabs :active="$tab"
        :tabs="['genel' => 'Genel', 'sectors' => 'Sektörler', 'risk-classes' => 'Risk Sınıfları']"
        :invalid="$errors->hasAny(array_map(fn ($f) => 'general.'.$f, array_keys($this->generalFields))) ? ['genel'] : []" />

    @if ($tab === 'genel')
        <form wire:submit="saveGeneral" class="max-w-2xl space-y-4">
            @foreach ($this->generalFields as $field => [$label])
                @if ($field === 'contact_address')
                    <flux:textarea wire:model="general.{{ $field }}" :label="$label" rows="2" />
                @else
                    <flux:input wire:model="general.{{ $field }}" :label="$label" />
                @endif
            @endforeach
            <flux:text size="sm">İletişim bilgileri web sitesinde (landing) de gösterilir.</flux:text>
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">Kaydet</flux:button>
            </div>
        </form>
    @elseif ($this->items !== null)
        <div class="max-w-3xl space-y-4">
            <form wire:submit="addItem" class="flex items-start gap-2">
                <div class="flex-1">
                    <flux:input wire:model="newItem" placeholder="Yeni kayıt adı" />
                </div>
                <flux:button type="submit" icon="plus">Ekle</flux:button>
            </form>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Ad</flux:table.column>
                    <flux:table.column>Durum</flux:table.column>
                    <flux:table.column align="end">Kullanım</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->items as $item)
                        <flux:table.row :key="$tab.'-'.$item->id">
                            <flux:table.cell variant="strong">
                                @if ($editingItemId === $item->id)
                                    <form wire:submit="saveItem" class="flex gap-2">
                                        <flux:input wire:model="editingItemName" size="sm" />
                                        <flux:button type="submit" size="sm" variant="primary" icon="check" />
                                    </form>
                                @else
                                    {{ $item->name }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$item->is_active ? 'green' : 'zinc'" inset="top bottom">{{ $item->is_active ? 'Aktif' : 'Pasif' }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ $item->usage }}</flux:table.cell>
                            <flux:table.cell align="end" class="whitespace-nowrap">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="startEditing({{ $item->id }})" />
                                <flux:button size="sm" variant="ghost" :icon="$item->is_active ? 'pause-circle' : 'play-circle'" wire:click="toggleItem({{ $item->id }})" />
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteItem({{ $item->id }})" wire:confirm="Silinsin mi?" />
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500">Liste boş.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
            <flux:text size="sm">Pasif kayıtlar yeni seçimlerde listelenmez; mevcut kayıtlardaki değer korunur.</flux:text>
        </div>
    @endif
</div>
