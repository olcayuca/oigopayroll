@props([
    'templateModel' => 'templateId',
    'permissionsModel' => 'permissions',
])

{{-- Optional template + individual permissions. Effective permissions = template ∪ selected. --}}
<div class="space-y-4">
    <flux:select wire:model="{{ $templateModel }}" label="Yetki şablonu (opsiyonel)">
        <flux:select.option value="">Şablon kullanma</flux:select.option>
        @foreach (\App\Models\PermissionTemplate::orderBy('name')->get() as $template)
            <flux:select.option value="{{ $template->id }}">{{ $template->name }} ({{ count($template->permissions) }} yetki)</flux:select.option>
        @endforeach
    </flux:select>

    <div>
        <flux:label>Ek / tek tek yetkiler</flux:label>
        <div class="mt-2 grid gap-4 sm:grid-cols-2">
            @foreach (\App\Enums\Permission::groups() as $group => $permissions)
                <flux:checkbox.group wire:model="{{ $permissionsModel }}" :label="$group">
                    @foreach ($permissions as $permission)
                        <flux:checkbox :value="$permission->value" :label="$permission->label()" />
                    @endforeach
                </flux:checkbox.group>
            @endforeach
        </div>
    </div>
</div>
