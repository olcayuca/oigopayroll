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
        $label = mb_strtolower($this->importType->label());
        Flux::toast(variant: 'success', text: collect([
            $import->created_rows ? "{$import->created_rows} {$label} oluşturuldu" : null,
            $import->updated_rows ? "{$import->updated_rows} {$label} güncellendi" : null,
        ])->filter()->implode(', ') ?: 'Değişiklik yapılmadı.');

        $this->redirectRoute($this->importType->indexRoute(), navigate: true);
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
        return match ($this->importType) {
            ImportType::Company => ['company_no' => 'Şirket No', 'title' => 'Unvan', 'tax_number' => 'Vergi No'],
            ImportType::Employee => ['registry_no' => 'Sicil No', 'first_name' => 'Adı', 'last_name' => 'Soyadı', 'company_name' => 'Firma', 'workplace_name' => 'Şube'],
            ImportType::Definition => ['type' => 'Tür', 'name' => 'Ad', 'code' => 'Kod', 'parent' => 'Üst Tanım', 'is_active' => 'Durum'],
            default => ['company_no' => 'Şirket No', 'company_name' => 'Şirket', 'workplace_no' => 'İşyeri No', 'branch_name' => 'Şube Adı', 'sgk_registry_no' => 'SGK Sicil No'],
        };
    }

    /**
     * @return class-string
     */
    private function modelClass(): string
    {
        return ImportType::from($this->type)->modelClass();
    }

    /**
     * How existing rows are matched, for the help text.
     */
    public function matchText(): string
    {
        return match ($this->importType) {
            ImportType::Company => 'şirket numarası',
            ImportType::Employee => 'sicil numarası',
            ImportType::Definition => 'tür + kod (kod yoksa tür + ad)',
            default => 'şirket + işyeri numarası',
        };
    }
}; ?>

<div>
    <x-panel.page-header
        :crumbs="[match ($this->importType) {
            \App\Enums\ImportType::Company => 'Şirketler',
            \App\Enums\ImportType::Employee => 'Personel',
            \App\Enums\ImportType::Definition => 'Tanımlar',
            default => 'İşyerleri',
        } => route($this->importType->indexRoute()), 'Excel ile Aktarım' => null]"
        :back="route($this->importType->indexRoute())"
        :title="'Excel ile Toplu '.$this->importType->label().' Ekleme / Güncelleme'"
        subtitle="Şablonu indir → Excel'i doldur → Yükle → Kontrol / önizleme → Onayla. Onay verilmeden hiçbir kayıt oluşturulmaz veya değiştirilmez." />

    <x-panel.alert variant="info" class="mb-5">
        @if ($this->importType === \App\Enums\ImportType::Employee || $this->importType === \App\Enums\ImportType::Workplace)
            Müşterinin <strong>KURULUM DOSYASI</strong> olduğu gibi yüklenebilir; dosyada birden fazla sayfa varsa ilgili sayfa ({{ $this->importType === \App\Enums\ImportType::Employee ? 'Personel Bilgileri' : 'Firma Bilgileri' }}) otomatik seçilir.
        @endif
        {{ ucfirst($this->matchText()) }} eşleşen satırlar güncellenir, diğerleri yeni kayıt olur. Yalnızca dosyadaki sütunlar değişir.
        @unless ($this->importType === \App\Enums\ImportType::Definition)
            Şifreli alanların (şifreler{{ $this->importType === \App\Enums\ImportType::Employee ? ', TCKN, IBAN, hesap no' : '' }}) sütunları boş veya yoksa mevcut değerler korunur.
        @endunless
        @if ($this->importType === \App\Enums\ImportType::Definition)
            Tüm tanım türleri tek sayfada yüklenir. Birimin üst birimi, pozisyonun birimi "Üst Tanım" sütununda verilir; listede yoksa onayda oluşturulur.
        @endif
        @if ($this->importType === \App\Enums\ImportType::Employee)
            Birim, üst birim, unvan, pozisyon gibi tanımlar listede yoksa onayda otomatik oluşturulur.
        @endif
    </x-panel.alert>

    @if (! $this->import || $this->import->status === \App\Enums\ImportStatus::Cancelled)
        <x-import-upload :template-url="route('imports.template', $this->importType->slug())" />
    @else
        <x-import-preview :import="$this->import" :columns="$this->previewColumns" :only-errors="$onlyErrors" />
    @endif
</div>
