<?php

use App\Enums\AuditEvent;
use App\Enums\CodeList;
use App\Models\PayrollCode;
use App\Payroll\Codes\CodeListImporter;
use App\Support\Audit;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Title('Bordro Kodları')] class extends Component {
    use WithFileUploads, WithPagination;

    #[Url(as: 'sekme', except: 'belge-turleri')]
    public string $tab = 'belge-turleri';

    #[Url(as: 'ara', except: '')]
    public string $search = '';

    public bool $showInactive = true;

    // Editor
    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    /** @var array{created: int, updated: int, errors: list<string>}|null */
    public ?array $importResult = null;

    public function mount(): void
    {
        $this->authorize('manage-settings');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['tab', 'search', 'showInactive'], true)) {
            $this->resetPage();
            $this->importResult = null;
        }
    }

    #[Computed]
    public function list(): CodeList
    {
        return CodeList::fromSlug($this->tab) ?? CodeList::DocumentTypes;
    }

    /**
     * @return LengthAwarePaginator<int, PayrollCode>
     */
    #[Computed]
    public function codes(): LengthAwarePaginator
    {
        return PayrollCode::where('list', $this->list)
            ->when(! $this->showInactive, fn ($query) => $query->where('is_active', true))
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', $this->search.'%')
                ->orWhere('name', 'like', '%'.$this->search.'%')))
            ->orderByRaw('length(code)')
            ->orderBy('code')
            ->paginate(50);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        $counts = PayrollCode::toBase()->selectRaw('list, count(*) as total')->groupBy('list')->pluck('total', 'list');

        return collect(CodeList::cases())->mapWithKeys(fn (CodeList $list) => [$list->slug() => (int) ($counts[$list->value] ?? 0)])->all();
    }

    public function create(): void
    {
        $this->reset('editingId', 'code', 'name', 'description');
        $this->resetValidation();

        Flux::modal('code')->show();
    }

    public function edit(int $id): void
    {
        $entry = PayrollCode::where('list', $this->list)->findOrFail($id);

        $this->editingId = $entry->id;
        $this->code = $entry->code;
        $this->name = $entry->name;
        $this->description = (string) $entry->description;
        $this->resetValidation();

        Flux::modal('code')->show();
    }

    public function save(): void
    {
        $this->authorize('manage-settings');
        [$hint, $pattern] = $this->list->format();

        $data = $this->validate([
            'code' => ['required', 'string', 'regex:'.$pattern,
                Rule::unique('payroll_codes', 'code')->where('list', $this->list->value)->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], ['code.regex' => "Kod biçimi: {$hint}."], ['code' => 'Kod', 'name' => 'Ad', 'description' => 'Açıklama']);

        $entry = $this->editingId ? PayrollCode::where('list', $this->list)->findOrFail($this->editingId) : new PayrollCode(['list' => $this->list]);
        $entry->fill([...$data, 'description' => $data['description'] ?: null])->save();

        Audit::log(AuditEvent::SystemSettingsChanged, "{$this->list->label()}: {$entry->code} {$entry->name} kaydedildi");

        unset($this->codes, $this->counts);
        Flux::modal('code')->close();
        Flux::toast(variant: 'success', text: 'Kod kaydedildi.');
    }

    public function toggle(int $id): void
    {
        $this->authorize('manage-settings');

        $entry = PayrollCode::where('list', $this->list)->findOrFail($id);
        $entry->update(['is_active' => ! $entry->is_active]);

        Audit::log(AuditEvent::SystemSettingsChanged, "{$this->list->label()}: {$entry->code} ".($entry->is_active ? 'aktifleştirildi' : 'pasife alındı'));
        unset($this->codes);
    }

    public function delete(int $id): void
    {
        $this->authorize('manage-settings');

        $entry = PayrollCode::where('list', $this->list)->findOrFail($id);
        $entry->delete();

        Audit::log(AuditEvent::SystemSettingsChanged, "{$this->list->label()}: {$entry->code} silindi");
        unset($this->codes, $this->counts);
        Flux::toast(variant: 'success', text: 'Kod silindi.');
    }

    public function import(CodeListImporter $importer): void
    {
        $this->authorize('manage-settings');
        $this->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']], [], ['file' => 'Excel dosyası']);

        $this->importResult = $importer->import($this->list, $this->file->getRealPath(), $this->file->getClientOriginalName(), Auth::user());

        $this->reset('file');
        unset($this->codes, $this->counts);
        Flux::modal('import')->close();
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Bordro Kodları</flux:heading>
            <flux:text class="mt-1">Bordro ve SGK bildirgelerinde kullanılan resmi kod listeleri. Pasif kodlar yeni kayıtlarda seçilemez, geçmiş kayıtlarda korunur.</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:modal.trigger name="import">
                <flux:button icon="table-cells">Excel ile Yükle</flux:button>
            </flux:modal.trigger>
            <flux:button variant="primary" icon="plus" wire:click="create">Yeni Kod</flux:button>
        </div>
    </div>

    <x-tabs :active="$tab" :tabs="collect(CodeList::cases())->mapWithKeys(fn ($l) => [$l->slug() => $l->label()])->all()" :counts="$this->counts" />

    @if ($importResult)
        <flux:callout :icon="$importResult['errors'] ? 'exclamation-triangle' : 'check-circle'" :color="$importResult['errors'] ? 'amber' : 'green'"
            heading="Yükleme tamamlandı: {{ $importResult['created'] }} yeni, {{ $importResult['updated'] }} güncellendi{{ $importResult['errors'] ? ', '.count($importResult['errors']).' satır atlandı' : '' }}">
            @if ($importResult['errors'])
                <flux:callout.text>
                    <ul class="list-disc ps-5 text-sm">
                        @foreach (array_slice($importResult['errors'], 0, 20) as $error) <li>{{ $error }}</li> @endforeach
                        @if (count($importResult['errors']) > 20) <li>… ve {{ count($importResult['errors']) - 20 }} satır daha</li> @endif
                    </ul>
                </flux:callout.text>
            @endif
        </flux:callout>
    @endif

    @if ($this->counts[$tab] === 0 && in_array($this->list, [CodeList::Occupations, CodeList::Banks], true))
        <flux:callout icon="information-circle" heading="{{ $this->list->label() }} henüz yüklenmedi"
            text="{{ $this->list === CodeList::Occupations ? 'SGK\'nın yayımladığı meslek kodları listesini (Excel) \'Excel ile Yükle\' ile aktarın.' : 'Banka listesini Excel ile yükleyin veya \'Yeni Kod\' ile tek tek ekleyin.' }}" />
    @endif

    <div class="flex flex-wrap items-center gap-4">
        <div class="w-full sm:w-80">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Kod veya ad ara..." clearable />
        </div>
        <flux:checkbox wire:model.live="showInactive" label="Pasif kodları göster" />
    </div>

    <flux:table :paginate="$this->codes">
        <flux:table.columns>
            <flux:table.column>Kod</flux:table.column>
            <flux:table.column>Ad</flux:table.column>
            <flux:table.column>Durum</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->codes as $entry)
                <flux:table.row :key="$entry->id">
                    <flux:table.cell class="font-mono whitespace-nowrap">{{ $entry->code }}</flux:table.cell>
                    <flux:table.cell class="whitespace-normal">
                        {{ $entry->name }}
                        @if ($entry->description) <div class="text-xs text-zinc-500">{{ $entry->description }}</div> @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$entry->is_active ? 'green' : 'zinc'" inset="top bottom">{{ $entry->is_active ? 'Aktif' : 'Pasif' }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end" class="whitespace-nowrap">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $entry->id }})" />
                        <flux:button size="sm" variant="ghost" :icon="$entry->is_active ? 'pause-circle' : 'play-circle'" wire:click="toggle({{ $entry->id }})" />
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $entry->id }})" wire:confirm="{{ $entry->code }} silinsin mi?" />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="py-10 text-center text-zinc-500">Kayıt yok.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="code" class="md:w-[36rem]">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? 'Kodu Düzenle' : 'Yeni Kod' }} · {{ $this->list->label() }}</flux:heading>
            <flux:input wire:model="code" label="Kod" :description="$this->list->format()[0]" required />
            <flux:textarea wire:model="name" label="Ad" rows="2" required />
            <flux:textarea wire:model="description" label="Açıklama (opsiyonel)" rows="2" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Kaydet</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="import" class="md:w-[36rem]">
        <form wire:submit="import" class="space-y-6">
            <div>
                <flux:heading size="lg">Excel ile Yükle · {{ $this->list->label() }}</flux:heading>
                <flux:text class="mt-1">
                    İlk satırda "Kod" ve "Ad" (ör. "Meslek Kodu", "Meslek Adı") başlıkları olmalı; "Açıklama" sütunu isteğe bağlıdır.
                    Başlık yoksa A sütunu kod, B sütunu ad kabul edilir. Var olan kodlar güncellenir, yeniler eklenir.
                    Kod biçimi: {{ $this->list->format()[0] }}.
                </flux:text>
            </div>
            <input type="file" wire:model="file" accept=".xlsx,.xls,.csv"
                class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-zinc-800 file:px-3 file:py-2 file:text-white dark:file:bg-zinc-200 dark:file:text-zinc-900" />
            <flux:error name="file" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="file,import">Yükle</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
