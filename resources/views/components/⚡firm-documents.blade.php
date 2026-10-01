<?php

use App\Actions\Firms\ManageFirmDocuments;
use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\Firm;
use App\Models\FirmDocument;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
 * Documents of one firm, used in the panel (Belgeler) and in Admin → Firma detayı → Belgeler.
 */
new class extends Component {
    use WithFileUploads;

    #[Locked]
    public int $firmId;

    public string $typeFilter = '';

    public ?int $editingId = null;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $file = null;

    /** @var array<string, string> */
    public array $form = [];

    public function mount(Firm $firm): void
    {
        $this->authorize('viewDocuments', $firm);
        $this->firmId = $firm->id;
    }

    #[Computed]
    public function firm(): Firm
    {
        return Firm::findOrFail($this->firmId);
    }

    /**
     * @return Collection<int, FirmDocument>
     */
    #[Computed]
    public function documents(): Collection
    {
        return $this->firm->documents()->with(['company', 'uploader'])
            ->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter))
            ->orderByRaw('valid_until is null')->orderBy('valid_until')->latest('id')
            ->get();
    }

    /**
     * @return Collection<int, Company>
     */
    #[Computed]
    public function companies(): Collection
    {
        return $this->firm->companies()->orderBy('company_no')->get(['id', 'firm_id', 'company_no', 'short_name', 'title']);
    }

    #[Computed]
    public function canManage(): bool
    {
        return Auth::user()->can('manageDocuments', $this->firm);
    }

    public function create(): void
    {
        $this->authorize('manageDocuments', $this->firm);

        $this->reset('editingId', 'file');
        $this->form = ['type' => DocumentType::TaxCertificate->value, 'title' => '', 'company_id' => '', 'valid_until' => '', 'notes' => ''];
        $this->resetValidation();

        Flux::modal('document')->show();
    }

    public function edit(int $id): void
    {
        $this->authorize('manageDocuments', $this->firm);

        $document = $this->find($id);
        $this->editingId = $document->id;
        $this->file = null;
        $this->form = [
            'type' => $document->type->value,
            'title' => $document->title,
            'company_id' => (string) $document->company_id,
            'valid_until' => (string) $document->valid_until?->toDateString(),
            'notes' => (string) $document->notes,
        ];
        $this->resetValidation();

        Flux::modal('document')->show();
    }

    public function updatedFormType(string $type): void
    {
        // Suggest the type as the title until the user types their own.
        if (($this->form['title'] ?? '') === '' || in_array($this->form['title'], array_values(DocumentType::options()), true)) {
            $this->form['title'] = DocumentType::tryFrom($type)?->label() ?? '';
        }
    }

    public function save(ManageFirmDocuments $documents): void
    {
        $this->authorize('manageDocuments', $this->firm);
        $this->resetValidation();

        $input = array_map(fn ($value) => $value === '' ? null : $value, $this->form);

        try {
            $this->editingId
                ? $documents->update($this->find($this->editingId), $input, Auth::user())
                : $documents->store($this->firm, $this->file, $input, Auth::user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field === 'file' ? 'file' : 'form.'.$field, $messages[0]);
            }

            return;
        }

        $this->reset('editingId', 'file');
        unset($this->documents);
        Flux::modal('document')->close();
        Flux::toast(variant: 'success', text: 'Belge kaydedildi.');
    }

    public function delete(int $id, ManageFirmDocuments $documents): void
    {
        $this->authorize('manageDocuments', $this->firm);

        $documents->delete($this->find($id), Auth::user());

        unset($this->documents);
        Flux::toast(variant: 'success', text: 'Belge silindi.');
    }

    private function find(int $id): FirmDocument
    {
        return FirmDocument::where('firm_id', $this->firmId)->findOrFail($id);
    }
}; ?>

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <flux:select wire:model.live="typeFilter" class="max-w-xs">
            <flux:select.option value="">Tüm belge türleri</flux:select.option>
            @foreach (DocumentType::cases() as $type)
                <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        @if ($this->canManage)
            <flux:button variant="primary" icon="arrow-up-tray" wire:click="create">Belge Yükle</flux:button>
        @endif
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Belge</flux:table.column>
            <flux:table.column>Şirket</flux:table.column>
            <flux:table.column>Geçerlilik</flux:table.column>
            <flux:table.column>Yükleyen</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->documents as $document)
                <flux:table.row :key="$document->id">
                    <flux:table.cell>
                        <div class="font-medium">{{ $document->title }}</div>
                        <div class="text-xs text-zinc-500">{{ $document->type->label() }} · {{ \App\System\HealthChecks::bytes($document->size) }}</div>
                        @if ($document->notes) <div class="mt-1 max-w-sm whitespace-normal text-xs text-zinc-500">{{ $document->notes }}</div> @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $document->company->short_name ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">
                        @switch ($document->validity())
                            @case (FirmDocument::EXPIRED)
                                <flux:badge size="sm" color="red" inset="top bottom">{{ $document->valid_until?->format('d.m.Y') }} · süresi doldu</flux:badge>
                                @break
                            @case (FirmDocument::EXPIRING)
                                <flux:badge size="sm" color="amber" inset="top bottom">{{ $document->valid_until?->format('d.m.Y') }} · yaklaşıyor</flux:badge>
                                @break
                            @default
                                {{ $document->valid_until?->format('d.m.Y') ?? 'Süresiz' }}
                        @endswitch
                    </flux:table.cell>
                    <flux:table.cell>
                        <div>{{ $document->uploader->name ?? '—' }}</div>
                        <div class="text-xs text-zinc-500">{{ $document->created_at?->format('d.m.Y') }}</div>
                    </flux:table.cell>
                    <flux:table.cell align="end" class="whitespace-nowrap">
                        <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('documents.download', $document)" />
                        @if ($this->canManage)
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $document->id }})" />
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $document->id }})"
                                wire:confirm="{{ $document->title }} silinsin mi? Dosya kalıcı olarak silinir." />
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="py-10 text-center text-zinc-500">Belge yok.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($this->canManage)
    <flux:modal name="document" class="md:w-[36rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Belgeyi Düzenle' : 'Belge Yükle' }}</flux:heading>
            <flux:select wire:model.live="form.type" label="Belge türü">
                @foreach (DocumentType::cases() as $type)
                    <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="form.title" label="Başlık" required />
            <flux:select wire:model="form.company_id" label="Şirket" description="Belge belirli bir şirkete aitse seçin.">
                <flux:select.option value="">Firma geneli</flux:select.option>
                @foreach ($this->companies as $company)
                    <flux:select.option value="{{ $company->id }}">{{ $company->company_no }} · {{ $company->short_name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input type="date" wire:model="form.valid_until" label="Geçerlilik tarihi" description="Süresi olan belgelerde 30 gün kala uyarı verilir." />
            <flux:textarea wire:model="form.notes" label="Not" rows="2" />
            @unless ($editingId)
                <flux:input type="file" wire:model="file" label="Dosya" description="PDF, JPG veya PNG; en fazla 10 MB." accept=".pdf,.jpg,.jpeg,.png" />
            @endunless
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="file,save">Kaydet</flux:button>
            </div>
        </form>
    </flux:modal>
    @endif
</div>
