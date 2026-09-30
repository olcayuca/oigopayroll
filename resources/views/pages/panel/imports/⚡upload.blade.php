<?php

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Imports\ImportService;
use App\Livewire\PanelComponent;
use App\Models\Company;
use App\Models\DataImport;
use App\Models\Workplace;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Excel ile Aktarım')] class extends PanelComponent {
    use WithFileUploads;

    public string $type;

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    public ?int $importId = null;

    public bool $onlyErrors = false;

    public function mount(string $type): void
    {
        $this->type = (ImportType::fromSlug($type) ?? abort(404))->value;

        $this->authorize('import', [$this->modelClass(), $this->firm]);
    }

    #[Computed]
    public function importType(): ImportType
    {
        return ImportType::from($this->type);
    }

    #[Computed]
    public function import(): ?DataImport
    {
        return $this->importId
            ? DataImport::where('firm_id', $this->firm->id)->where('user_id', Auth::id())->find($this->importId)
            : null;
    }

    public function upload(ImportService $importService): void
    {
        $this->authorize('import', [$this->modelClass(), $this->firm]);

        $this->validate(
            ['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']],
            [], ['file' => 'Excel dosyası'],
        );

        try {
            $import = $importService->preview(
                $this->importType,
                $this->firm,
                $this->file->getRealPath(),
                $this->file->getClientOriginalName(),
                Auth::user(),
            );
        } catch (ValidationException $e) {
            $this->addError('file', collect($e->errors())->flatten()->first());

            return;
        }

        $this->importId = $import->id;
        $this->reset('file', 'onlyErrors');
        $this->onlyErrors = $import->error_rows > 0;
        unset($this->import);
    }

    public function confirm(ImportService $importService): void
    {
        $this->authorize('import', [$this->modelClass(), $this->firm]);

        $import = $this->import ?? abort(404);

        try {
            $importService->confirm($import, Auth::user());
        } catch (ValidationException $e) {
            unset($this->import);
            $this->onlyErrors = true;
            Flux::toast(variant: 'danger', text: collect($e->errors())->flatten()->first());

            return;
        }

        $label = $this->importType === ImportType::Company ? 'şirket' : 'işyeri';
        Flux::toast(variant: 'success', text: "{$import->total_rows} {$label} oluşturuldu.");

        $this->redirectRoute($this->importType === ImportType::Company ? 'companies.index' : 'workplaces.index', navigate: true);
    }

    public function startOver(ImportService $importService): void
    {
        if ($this->import) {
            $importService->cancel($this->import);
        }

        $this->reset('importId', 'file', 'onlyErrors');
        unset($this->import);
    }

    /**
     * Columns shown in the preview table: data key => header.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function previewColumns(): array
    {
        return $this->importType === ImportType::Company
            ? ['company_no' => 'Şirket No', 'title' => 'Unvan', 'tax_number' => 'Vergi No']
            : ['company_no' => 'Şirket No', 'workplace_no' => 'İşyeri No', 'branch_name' => 'Şube Adı', 'sgk_registry_no' => 'SGK Sicil No'];
    }

    /**
     * @return class-string<Company|Workplace>
     */
    private function modelClass(): string
    {
        return ImportType::from($this->type) === ImportType::Company ? Company::class : Workplace::class;
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <flux:breadcrumbs>
        @if ($this->importType === \App\Enums\ImportType::Company)
            <flux:breadcrumbs.item :href="route('companies.index')" wire:navigate>Şirketler</flux:breadcrumbs.item>
        @else
            <flux:breadcrumbs.item :href="route('workplaces.index')" wire:navigate>İşyerleri</flux:breadcrumbs.item>
        @endif
        <flux:breadcrumbs.item>Excel ile Aktarım</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div>
        <flux:heading size="xl">Excel ile Toplu {{ $this->importType->label() }} Oluşturma</flux:heading>
        <flux:text class="mt-1">
            Şablonu İndir → Excel'i Doldur → Yükle → Kontrol / Önizleme → Onayla.
            Onay verilmeden hiçbir kayıt oluşturulmaz.
        </flux:text>
    </div>

    @if (! $this->import || $this->import->status === \App\Enums\ImportStatus::Cancelled)
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-dashed border-zinc-300 p-6 dark:border-zinc-600">
                <flux:heading>1. Şablonu indirin</flux:heading>
                <flux:text class="mt-1">Zorunlu sütunlar sarı işaretlidir; açılır listeler ve "Açıklamalar" sayfası şablonun içindedir.</flux:text>
                <flux:button class="mt-4" icon="arrow-down-tray" :href="route('imports.template', $this->importType->slug())">Excel Şablonunu İndir</flux:button>
            </div>

            <form wire:submit="upload" class="rounded-xl border border-dashed border-zinc-300 p-6 dark:border-zinc-600">
                <flux:heading>2. Doldurduğunuz dosyayı yükleyin</flux:heading>
                <flux:text class="mt-1">.xlsx, .xls veya .csv — en fazla 10 MB.</flux:text>
                <div class="mt-4 space-y-3">
                    <input type="file" wire:model="file" accept=".xlsx,.xls,.csv"
                        class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-zinc-800 file:px-3 file:py-2 file:text-white dark:file:bg-zinc-200 dark:file:text-zinc-900" />
                    <flux:error name="file" />
                    <div wire:loading wire:target="file" class="text-sm text-zinc-500">Dosya yükleniyor...</div>
                    <flux:button type="submit" variant="primary" icon="magnifying-glass" wire:loading.attr="disabled" wire:target="file,upload">
                        Yükle ve Kontrol Et
                    </flux:button>
                </div>
            </form>
        </div>
    @else
        @php($import = $this->import)

        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="flex flex-wrap gap-8">
                <div><flux:text size="sm">Dosya</flux:text><div class="font-medium">{{ $import->original_filename }}</div></div>
                <div><flux:text size="sm">Satır</flux:text><div class="text-2xl font-semibold">{{ $import->total_rows }}</div></div>
                <div><flux:text size="sm">Hatasız</flux:text><div class="text-2xl font-semibold text-green-600">{{ $import->total_rows - $import->error_rows }}</div></div>
                <div><flux:text size="sm">Hatalı</flux:text><div class="text-2xl font-semibold {{ $import->error_rows ? 'text-red-500' : '' }}">{{ $import->error_rows }}</div></div>
            </div>
            <div class="flex gap-2">
                <flux:button icon="arrow-path" wire:click="startOver">Yeni Dosya Yükle</flux:button>
                @if ($import->canBeConfirmed())
                    <flux:button variant="primary" icon="check" wire:click="confirm"
                        wire:confirm="{{ $import->total_rows }} kayıt oluşturulsun mu?">Onayla ve Oluştur</flux:button>
                @endif
            </div>
        </div>

        @if ($import->file_errors)
            <flux:callout icon="x-circle" color="red" heading="Dosya okunamadı">
                <flux:callout.text>
                    <ul class="list-disc ps-5">
                        @foreach ($import->file_errors as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                </flux:callout.text>
            </flux:callout>
        @elseif ($import->error_rows > 0)
            <flux:callout icon="exclamation-triangle" color="amber" heading="{{ $import->error_rows }} satırda hata var"
                text="Hataları Excel dosyanızda düzeltip dosyayı yeniden yükleyin. Hatalar giderilmeden kayıt oluşturulmaz." />
        @else
            <flux:callout icon="check-circle" color="green" heading="Tüm satırlar geçerli"
                text="Kontrol edip onayladığınızda kayıtlar oluşturulacak." />
        @endif

        @if ($import->total_rows > 0)
            <flux:checkbox wire:model.live="onlyErrors" label="Yalnızca hatalı satırları göster" />

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Satır</flux:table.column>
                    @foreach ($this->previewColumns as $header)
                        <flux:table.column>{{ $header }}</flux:table.column>
                    @endforeach
                    <flux:table.column>Durum</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($import->rows as $row)
                        @continue($onlyErrors && ! $row->hasErrors())
                        <flux:table.row :key="$row->id">
                            <flux:table.cell>{{ $row->row_number }}</flux:table.cell>
                            @foreach (array_keys($this->previewColumns) as $key)
                                <flux:table.cell>{{ $row->data[$key] ?? '' }}</flux:table.cell>
                            @endforeach
                            <flux:table.cell class="whitespace-normal">
                                @if ($row->hasErrors())
                                    <ul class="space-y-0.5 text-sm text-red-500">
                                        @foreach (collect($row->errors)->flatten() as $message) <li>{{ $message }}</li> @endforeach
                                    </ul>
                                @else
                                    <flux:badge size="sm" color="green" inset="top bottom">Geçerli</flux:badge>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    @endif
</div>
