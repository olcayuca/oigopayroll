<?php

use App\Actions\Access\DelegatedGrantGuard;
use App\Actions\Firms\ManageFirmLink;
use App\Livewire\PanelComponent;
use App\Models\Firm;
use App\Models\FirmLink;
use App\Models\PermissionTemplate;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

new #[Title('Firma Erişimleri')] class extends PanelComponent {
    public ?int $editingLinkId = null;

    public string $taxNumber = '';

    public string $templateId = '';

    /** @var list<string> */
    public array $permissions = [];

    public function mount(): void
    {
        $this->authorize('manageUsers', $this->firm);
    }

    /**
     * Firms allowed to manage the active firm.
     *
     * @return Collection<int, FirmLink>
     */
    #[Computed]
    public function managerLinks(): Collection
    {
        return $this->firm->managerLinks()->with('manager')->get();
    }

    /**
     * Firms the active firm manages.
     *
     * @return Collection<int, FirmLink>
     */
    #[Computed]
    public function managedLinks(): Collection
    {
        return $this->firm->managedLinks()->with('managed')->get();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function grantable(): array
    {
        return app(DelegatedGrantGuard::class)->grantable(Auth::user(), $this->firm);
    }

    public function newLink(): void
    {
        $this->reset('editingLinkId', 'taxNumber', 'templateId', 'permissions');
        $this->resetValidation();

        Flux::modal('firm-link')->show();
    }

    public function editLink(int $linkId): void
    {
        $link = $this->managerLinks->firstWhere('id', $linkId) ?? abort(404);

        $this->editingLinkId = $link->id;
        $this->taxNumber = (string) $link->manager->tax_number;
        $this->templateId = '';
        $this->permissions = $link->permissions;
        $this->resetValidation();

        Flux::modal('firm-link')->show();
    }

    public function save(ManageFirmLink $manageFirmLink): void
    {
        $this->authorize('manageUsers', $this->firm);

        $manager = $this->editingLinkId
            ? ($this->managerLinks->firstWhere('id', $this->editingLinkId)?->manager ?? abort(404))
            : Firm::where('tax_number', trim($this->taxNumber))->first();

        if (! $manager) {
            $this->resetErrorBag();
            $this->addError('taxNumber', 'Bu vergi numarasıyla kayıtlı firma bulunamadı.');

            return;
        }

        $template = $this->templateId !== '' ? PermissionTemplate::find($this->templateId) : null;
        $permissions = [...$this->permissions, ...($template->permissions ?? [])];

        $saved = $this->mappingErrors('link', [], fn () => $manageFirmLink->link($manager, $this->firm, $permissions, Auth::user(), delegated: true));

        if (! $saved) {
            return;
        }

        unset($this->managerLinks);
        Flux::modal('firm-link')->close();
        Flux::toast(variant: 'success', text: "{$manager->name} artık firmanızı yönetebilir.");
    }

    public function remove(int $linkId, ManageFirmLink $manageFirmLink): void
    {
        $this->authorize('manageUsers', $this->firm);

        $link = $this->managerLinks->firstWhere('id', $linkId) ?? abort(404);

        try {
            $manageFirmLink->unlink($link, Auth::user(), delegated: true);
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: collect($e->errors())->flatten()->first());

            return;
        }

        unset($this->managerLinks);
        Flux::toast(variant: 'success', text: 'Erişim kaldırıldı.');
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Firma Erişimleri</flux:heading>
            <flux:text class="mt-1">
                Muhasebe, danışmanlık veya grup firmaları gibi başka bir firmanın <strong>{{ $this->firm->name }}</strong> üzerinde işlem yapmasına izin verin.
                O firmanın kullanıcıları, kendi yetkileri ile burada verdiğiniz yetkilerin kesişimi kadar işlem yapabilir.
            </flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="newLink">Yönetici Firma Ekle</flux:button>
    </div>

    <section class="space-y-3">
        <flux:heading size="lg">Firmamızı yönetebilen firmalar</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Firma</flux:table.column>
                <flux:table.column>İzin verilen yetkiler</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->managerLinks as $link)
                    <flux:table.row :key="$link->id">
                        <flux:table.cell variant="strong">
                            {{ $link->manager->name }}
                            <div class="text-xs font-normal text-zinc-500">{{ $link->manager->tax_number }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="max-w-md whitespace-normal text-xs text-zinc-500">
                            {{ collect($link->permissions)->map(fn ($p) => \App\Enums\Permission::from($p)->label())->join(', ') }}
                        </flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap">
                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editLink({{ $link->id }})" />
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="remove({{ $link->id }})" wire:confirm="{{ $link->manager->name }} firmasının erişimi kaldırılsın mı?" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="py-8 text-center text-zinc-500">Firmanızı yönetebilen başka firma yok.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </section>

    @if ($this->managedLinks->isNotEmpty())
        <section class="space-y-3">
            <flux:heading size="lg">Firmamızın yönettiği firmalar</flux:heading>
            <flux:text size="sm">Bu firmalara sol üstteki firma seçiciden geçebilirsiniz.</flux:text>
            <div class="flex flex-wrap gap-2">
                @foreach ($this->managedLinks as $link)
                    <flux:badge>{{ $link->managed->name }}</flux:badge>
                @endforeach
            </div>
        </section>
    @endif

    <flux:modal name="firm-link" class="md:w-[44rem]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingLinkId ? 'Erişimi Düzenle' : 'Yönetici Firma Ekle' }}</flux:heading>
                <flux:text class="mt-1">Firmanızı yönetecek firmanın vergi numarasını girin. Yalnızca kendi sahip olduğunuz yetkileri verebilirsiniz.</flux:text>
            </div>

            <flux:input wire:model="taxNumber" label="Yönetici firmanın vergi numarası" inputmode="numeric" maxlength="11" required :disabled="(bool) $editingLinkId" />
            <flux:error name="manager" />

            <x-permission-picker :allowed="$this->grantable" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Kaydet</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
