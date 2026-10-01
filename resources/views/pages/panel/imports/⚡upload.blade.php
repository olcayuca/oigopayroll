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
        $importType = ImportType::fromSlug($type);
        abort_if($importType === null || $importType === ImportType::Firm, 404);
        $this->type = $importType->value;

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

        $import->refresh();
        $label = $this->importType === ImportType::Company ? 'şirket' : 'işyeri';
        Flux::toast(variant: 'success', text: collect([
            $import->created_rows ? "{$import->created_rows} {$label} oluşturuldu" : null,
            $import->updated_rows ? "{$import->updated_rows} {$label} güncellendi" : null,
        ])->filter()->implode(', ') ?: 'Değişiklik yapılmadı.');

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
        <flux:heading size="xl">Excel ile Toplu {{ $this->importType->label() }} Ekleme / Güncelleme</flux:heading>
        <flux:text class="mt-1">
            Şablonu İndir → Excel'i Doldur → Yükle → Kontrol / Önizleme → Onayla.
            Onay verilmeden hiçbir kayıt oluşturulmaz veya değiştirilmez.
        </flux:text>
        <flux:text class="mt-1">
            Mevcut kayıtları toplu güncellemek için listedeki <strong>Excel İndir</strong> ile aldığınız dosyayı düzenleyip yükleyin:
            {{ $this->importType === \App\Enums\ImportType::Company ? 'şirket numarası' : 'şirket numarası + işyeri numarası' }} eşleşen satırlar güncellenir,
            diğerleri yeni kayıt olur. Yalnızca dosyadaki sütunlar değişir; şifre sütunları boş veya yoksa mevcut şifreler korunur.
        </flux:text>
    </div>

    @if (! $this->import || $this->import->status === \App\Enums\ImportStatus::Cancelled)
        <x-import-upload :template-url="route('imports.template', $this->importType->slug())" />
    @else
        <x-import-preview :import="$this->import" :columns="$this->previewColumns" :only-errors="$onlyErrors" />
    @endif
</div>
