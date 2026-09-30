<?php

use App\Enums\ImportStatus;
use App\Enums\ImportType;
use App\Imports\ImportService;
use App\Models\DataImport;
use App\Models\Firm;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Title('Excel ile Firma Aktarımı')] class extends Component {
    use WithFileUploads;

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    public ?int $importId = null;

    public bool $onlyErrors = false;

    public function mount(): void
    {
        $this->authorize('create', Firm::class);
    }

    #[Computed]
    public function import(): ?DataImport
    {
        return $this->importId
            ? DataImport::where('type', ImportType::Firm)->where('user_id', Auth::id())->find($this->importId)
            : null;
    }

    public function upload(ImportService $importService): void
    {
        $this->authorize('create', Firm::class);

        $this->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']], [], ['file' => 'Excel dosyası']);

        $import = $importService->preview(
            ImportType::Firm, null, $this->file->getRealPath(), $this->file->getClientOriginalName(), Auth::user(),
        );

        $this->importId = $import->id;
        $this->reset('file');
        $this->onlyErrors = $import->error_rows > 0;
        unset($this->import);
    }

    public function confirm(ImportService $importService): void
    {
        $this->authorize('create', Firm::class);

        $import = $this->import ?? abort(404);

        try {
            $importService->confirm($import, Auth::user());
        } catch (ValidationException $e) {
            unset($this->import);
            $this->onlyErrors = true;
            Flux::toast(variant: 'danger', text: collect($e->errors())->flatten()->first());

            return;
        }

        Flux::toast(variant: 'success', text: "{$import->total_rows} firma oluşturuldu ve aktif edildi.");
        $this->redirectRoute('admin.firms.index', navigate: true);
    }

    public function startOver(ImportService $importService): void
    {
        if ($this->import && $this->import->status === ImportStatus::Validated) {
            $importService->cancel($this->import);
        }

        $this->reset('importId', 'file', 'onlyErrors');
        unset($this->import);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('admin.firms.index')" wire:navigate>Firmalar</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Excel ile Aktarım</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div>
        <flux:heading size="xl">Excel ile Toplu Firma Oluşturma</flux:heading>
        <flux:text class="mt-1">
            Şablonu İndir → Excel'i Doldur → Yükle → Kontrol / Önizleme → Onayla.
            HRD tarafından oluşturulan firmalar onay beklemeden aktif olur.
        </flux:text>
    </div>

    @if (! $this->import || $this->import->status === ImportStatus::Cancelled)
        <x-import-upload :template-url="route('admin.firms.template')" />
    @else
        <x-import-preview :import="$this->import" :only-errors="$onlyErrors"
            :columns="['name' => 'Firma Adı', 'title' => 'Unvan', 'tax_number' => 'Vergi No', 'contact_name' => 'Yetkili']" />
    @endif
</div>
