<?php

use App\Actions\Definitions\SaveDefinition;
use App\Enums\DefinitionType;
use App\Livewire\PanelComponent;
use App\Models\Definition;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/*
 * Tanımlar: firm-level lists chosen on personnel records (birim, unvan, pozisyon, masraf grubu…).
 */
new #[Title('Tanımlar')] class extends PanelComponent {
    #[Url(as: 'tur', except: 'ust-birim')]
    public string $typeSlug = 'ust-birim';

    #[Url(except: '')]
    public string $search = '';

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = ['code' => '', 'name' => '', 'parent_id' => '', 'extra' => [], 'is_active' => true];

    public function mount(): void
    {
        $this->authorize('viewDefinitions', $this->firm);

        if (DefinitionType::fromSlug($this->typeSlug) === null) {
            $this->typeSlug = DefinitionType::UpperUnit->slug();
        }
    }

    #[Computed]
    public function type(): DefinitionType
    {
        return DefinitionType::fromSlug($this->typeSlug) ?? DefinitionType::UpperUnit;
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return Definition::query()->where('firm_id', $this->firm->id)
            ->selectRaw('type, count(*) as total')->groupBy('type')
            ->pluck('total', 'type')->map(fn ($total) => (int) $total)->all();
    }

    /**
     * @return Collection<int, Definition>
     */
    #[Computed]
    public function definitions(): Collection
    {
        return Definition::query()->ofType($this->firm, $this->type)
            ->with('parent')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', '%'.$this->search.'%')->orWhere('name', 'like', '%'.$this->search.'%')))
            ->orderBy('code')
            ->get();
    }

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function usage(): array
    {
        return SaveDefinition::usage($this->firm, $this->type);
    }

    /**
     * @return Collection<int, Definition>
     */
    #[Computed]
    public function parents(): Collection
    {
        $parentType = $this->type->parentType();

        return $parentType ? Definition::query()->ofType($this->firm, $parentType)->orderBy('name')->get() : new Collection;
    }

    public function selectType(string $slug): void
    {
        $this->typeSlug = DefinitionType::fromSlug($slug)?->slug() ?? $this->typeSlug;
        $this->reset('search');
        unset($this->definitions, $this->usage, $this->parents, $this->type);
    }

    public function create(): void
    {
        $this->authorize('manageDefinitions', $this->firm);
        $this->resetErrorBag();
        $this->editingId = null;
        $this->form = ['code' => '', 'name' => '', 'parent_id' => '', 'extra' => array_fill_keys(array_keys($this->type->extraFields()), ''), 'is_active' => true];
        Flux::modal('definition')->show();
    }

    public function edit(int $id): void
    {
        $this->authorize('manageDefinitions', $this->firm);
        $definition = Definition::query()->ofType($this->firm, $this->type)->findOrFail($id);

        $this->resetErrorBag();
        $this->editingId = $definition->id;
        $this->form = [
            'code' => $definition->code,
            'name' => $definition->name,
            'parent_id' => (string) ($definition->parent_id ?? ''),
            'extra' => array_merge(array_fill_keys(array_keys($this->type->extraFields()), ''), $definition->extra ?? []),
            'is_active' => $definition->is_active,
        ];
        Flux::modal('definition')->show();
    }

    public function save(SaveDefinition $saveDefinition): void
    {
        $this->authorize('manageDefinitions', $this->firm);
        $definition = $this->editingId ? Definition::query()->ofType($this->firm, $this->type)->findOrFail($this->editingId) : null;

        $input = $this->form;
        if ($this->type->parentType() === null) {
            unset($input['parent_id']);
        }

        try {
            $saveDefinition->save($this->firm, $this->type, $input, $definition);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError('form.'.$field, $messages[0]);
            }

            return;
        }

        Flux::modal('definition')->close();
        Flux::toast(variant: 'success', text: $this->type->singular().' kaydedildi.');
        unset($this->definitions, $this->counts);
    }

    public function delete(int $id, SaveDefinition $saveDefinition): void
    {
        $this->authorize('manageDefinitions', $this->firm);

        try {
            $saveDefinition->delete(Definition::query()->ofType($this->firm, $this->type)->findOrFail($id));
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: collect($e->errors())->flatten()->first());

            return;
        }

        Flux::modal('definition')->close();
        Flux::toast(variant: 'success', text: $this->type->singular().' silindi.');
        unset($this->definitions, $this->counts);
    }
}; ?>

<div>
    @php $canManage = auth()->user()->can('manageDefinitions', $this->firm); @endphp

    <x-panel.page-header :crumbs="['Kurulum' => null, 'Tanımlar' => null, $this->firm->name => null]" title="Tanımlar"
        subtitle="Personel kayıtlarında seçilen organizasyon listeleri buradan yönetilir. Excel ile personel aktarırken listede olmayanlar otomatik eklenir." />

    <div class="grid items-start gap-5 lg:grid-cols-[240px_minmax(0,1fr)]">
        <nav class="rounded-2xl border border-line bg-white p-2" aria-label="Tanım türleri">
            @foreach (['ORGANİZASYON' => [DefinitionType::UpperUnit, DefinitionType::Unit, DefinitionType::JobFamily, DefinitionType::Title, DefinitionType::Position, DefinitionType::Level], 'BORDRO & FİNANS' => [DefinitionType::CostGroup]] as $group => $types)
                <div class="px-2.5 pt-2.5 pb-1.5 text-[10.5px] font-bold tracking-[0.12em] text-muted-2">{{ $group }}</div>
                @foreach ($types as $definitionType)
                    @php $active = $definitionType === $this->type; $count = $this->counts[$definitionType->value] ?? 0; @endphp
                    <button type="button" wire:click="selectType('{{ $definitionType->slug() }}')" @class([
                        'flex w-full items-center gap-2 rounded-[9px] px-2.5 py-2 text-start text-[13.5px] font-semibold transition',
                        'bg-[#F1F5FA] text-ink shadow-[inset_3px_0_0_0_var(--color-mint)]' => $active,
                        'text-ink-2 hover:bg-row-hover' => ! $active,
                    ])>
                        <span class="flex-1">{{ $definitionType->label() }}</span>
                        <span @class(['text-xs font-bold tabular-nums', 'text-st-amber' => $count === 0, 'text-muted-2' => $count > 0])>{{ $count }}</span>
                    </button>
                @endforeach
            @endforeach
        </nav>

        <x-panel.table :paginate="$this->definitions" min-width="640px">
            <x-slot:toolbar>
                <div class="me-auto">
                    <div class="text-[15px] font-extrabold text-ink">{{ $this->type->label() }}</div>
                    <div class="text-[12.5px] text-muted">{{ $this->type->description() }}</div>
                </div>
                <x-panel.search wire:model.live.debounce.300ms="search" placeholder="Kod veya ad ara..." class="!max-w-[220px]" />
                @if ($canManage)
                    <flux:button variant="primary" icon="plus" size="sm" wire:click="create">Yeni ekle</flux:button>
                @endif
            </x-slot:toolbar>

            <x-slot:head>
                <x-panel.th>Kod</x-panel.th>
                <x-panel.th>Ad</x-panel.th>
                @if ($this->type->parentType())
                    <x-panel.th>{{ $this->type->parentType()->singular() }}</x-panel.th>
                @endif
                @foreach ($this->type->extraFields() as $label)
                    <x-panel.th>{{ $label }}</x-panel.th>
                @endforeach
                <x-panel.th>Kullanım</x-panel.th>
                <x-panel.th>Durum</x-panel.th>
                <x-panel.th class="w-10"></x-panel.th>
            </x-slot:head>

            @foreach ($this->definitions as $definition)
                <tr wire:key="def-{{ $definition->id }}" x-on:click="{{ $canManage ? '$wire.edit('.$definition->id.')' : '' }}"
                    @class(['border-t border-line-4 transition-colors', 'cursor-pointer hover:bg-row-hover' => $canManage])>
                    <x-panel.td strong>{{ $definition->code }}</x-panel.td>
                    <x-panel.td strong>{{ $definition->name }}</x-panel.td>
                    @if ($this->type->parentType())
                        <x-panel.td>{{ $definition->parent?->name ?? '—' }}</x-panel.td>
                    @endif
                    @foreach (array_keys($this->type->extraFields()) as $key)
                        <x-panel.td>{{ $definition->extra[$key] ?? '—' }}</x-panel.td>
                    @endforeach
                    <x-panel.td>
                        @if ($used = $this->usage[$definition->id] ?? 0)
                            {{ $used }} personel
                        @else
                            <span class="text-faint">Kullanılmıyor</span>
                        @endif
                    </x-panel.td>
                    <x-panel.td>
                        <x-panel.badge :color="$definition->is_active ? 'green' : 'gray'">{{ $definition->is_active ? 'Aktif' : 'Pasif' }}</x-panel.badge>
                    </x-panel.td>
                    <td class="pe-4 text-faint"><flux:icon.chevron-right variant="micro" class="size-4" /></td>
                </tr>
            @endforeach

            <x-slot:empty>
                @if ($this->definitions->isEmpty())
                    <x-panel.empty icon="rectangle-stack" :title="$search !== '' ? 'Sonuç bulunamadı' : 'Henüz '.mb_strtolower($this->type->singular()).' tanımlanmadı'">
                        {{ $search !== '' ? 'Aramaya uyan kayıt yok.' : '"Yeni ekle" ile ekleyin veya Excel ile personel aktarırken otomatik oluşturulmasını bekleyin.' }}
                    </x-panel.empty>
                @endif
            </x-slot:empty>
        </x-panel.table>
    </div>

    <flux:modal name="definition" variant="flyout" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $editingId ? $this->type->singular().' Düzenle' : 'Yeni '.$this->type->singular() }}</flux:heading>
                <flux:text class="mt-1">{{ $this->type->description() }}</flux:text>
            </div>

            <flux:input wire:model="form.code" label="Kod" required maxlength="50" />
            <flux:input wire:model="form.name" label="Ad" required />
            @if ($this->type->parentType())
                <flux:select wire:model="form.parent_id" :label="$this->type->parentType()->singular()">
                    <flux:select.option value="">Seçiniz</flux:select.option>
                    @foreach ($this->parents as $parent)
                        <flux:select.option value="{{ $parent->id }}">{{ $parent->code }} · {{ $parent->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            @foreach ($this->type->extraFields() as $key => $label)
                <flux:input wire:model="form.extra.{{ $key }}" :label="$label" />
            @endforeach
            <flux:switch wire:model="form.is_active" label="Aktif" description="Pasif tanımlar yeni personel kayıtlarında seçilemez." />

            <div class="flex items-center gap-2 pt-2">
                @if ($editingId)
                    <flux:button variant="ghost" icon="trash" class="!text-st-red" wire:click="delete({{ $editingId }})"
                        wire:confirm="Bu tanım silinsin mi?">Sil</flux:button>
                @endif
                <flux:spacer />
                <flux:modal.close><flux:button>Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Kaydet</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
