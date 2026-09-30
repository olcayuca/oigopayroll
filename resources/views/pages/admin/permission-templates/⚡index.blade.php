<?php

use App\Enums\Permission;
use App\Models\AccessGrant;
use App\Models\PermissionTemplate;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Yetki Şablonları')] class extends Component {
    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    /** @var list<string> */
    public array $permissions = [];

    public function mount(): void
    {
        $this->authorize('viewAny', PermissionTemplate::class);
    }

    /**
     * @return Collection<int, PermissionTemplate>
     */
    #[Computed]
    public function templates(): Collection
    {
        return PermissionTemplate::orderBy('name')->get();
    }

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function usage(): array
    {
        return AccessGrant::query()
            ->whereNotNull('permission_template_id')
            ->toBase()
            ->selectRaw('permission_template_id, count(*) as total')
            ->groupBy('permission_template_id')
            ->pluck('total', 'permission_template_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function create(): void
    {
        $this->authorize('create', PermissionTemplate::class);

        $this->reset('editingId', 'name', 'description', 'permissions');
        $this->resetValidation();

        Flux::modal('template')->show();
    }

    public function edit(int $templateId): void
    {
        $template = PermissionTemplate::findOrFail($templateId);
        $this->authorize('update', $template);

        $this->editingId = $template->id;
        $this->name = $template->name;
        $this->description = (string) $template->description;
        $this->permissions = $template->permissions;
        $this->resetValidation();

        Flux::modal('template')->show();
    }

    public function save(): void
    {
        $template = $this->editingId ? PermissionTemplate::findOrFail($this->editingId) : new PermissionTemplate;
        $this->authorize($template->exists ? 'update' : 'create', $template->exists ? $template : PermissionTemplate::class);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('permission_templates', 'name')->ignore($template)],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => [Rule::enum(Permission::class)],
        ], [], ['name' => 'Şablon adı', 'description' => 'Açıklama', 'permissions' => 'Yetkiler']);

        $template->fill($data)->save();

        unset($this->templates);
        Flux::modal('template')->close();
        Flux::toast(variant: 'success', text: 'Şablon kaydedildi.');
    }

    public function delete(int $templateId): void
    {
        $template = PermissionTemplate::findOrFail($templateId);
        $this->authorize('delete', $template);

        // Grants keep their individually selected permissions; the template part is dropped.
        $template->delete();

        unset($this->templates, $this->usage);
        Flux::toast(variant: 'success', text: 'Şablon silindi.');
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Yetki Şablonları</flux:heading>
            <flux:text class="mt-1">Sık kullanılan yetki gruplarını şablon olarak kaydedin. Şablon kullanmak zorunlu değildir; şablondaki değişiklik onu kullanan tüm kullanıcılara yansır.</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">Yeni Şablon</flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Şablon</flux:table.column>
            <flux:table.column>Yetkiler</flux:table.column>
            <flux:table.column align="end">Kullanım</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->templates as $template)
                <flux:table.row :key="$template->id">
                    <flux:table.cell variant="strong">
                        {{ $template->name }}
                        <div class="text-xs font-normal text-zinc-500">{{ $template->description }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="max-w-lg whitespace-normal text-xs text-zinc-500">
                        {{ collect($template->permissions)->map(fn ($p) => \App\Enums\Permission::from($p)->label())->join(', ') }}
                    </flux:table.cell>
                    <flux:table.cell align="end">{{ $this->usage[$template->id] ?? 0 }}</flux:table.cell>
                    <flux:table.cell align="end" class="whitespace-nowrap">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $template->id }})" />
                        <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $template->id }})"
                            wire:confirm="Şablon silinsin mi? Kullanan {{ $this->usage[$template->id] ?? 0 }} yetki kaydında yalnızca tek tek seçilmiş yetkiler kalır." />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="py-8 text-center text-zinc-500">Şablon yok.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="template" class="md:w-[44rem]">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? 'Şablonu Düzenle' : 'Yeni Şablon' }}</flux:heading>

            <flux:input wire:model="name" label="Şablon adı" required />
            <flux:input wire:model="description" label="Açıklama" />

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (\App\Enums\Permission::groups() as $group => $groupPermissions)
                    <flux:checkbox.group wire:model="permissions" :label="$group">
                        @foreach ($groupPermissions as $permission)
                            <flux:checkbox :value="$permission->value" :label="$permission->label()" />
                        @endforeach
                    </flux:checkbox.group>
                @endforeach
            </div>
            <flux:error name="permissions" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Kaydet</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
