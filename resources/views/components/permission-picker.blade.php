@props([
    'templateModel' => 'templateId',
    'permissionsModel' => 'permissions',
    // Restrict choices to these permission values (delegated user management); null = all.
    'allowed' => null,
])

@php
    $templates = \App\Models\PermissionTemplate::orderBy('name')->get()
        ->filter(fn ($template) => $allowed === null || array_diff($template->permissions, $allowed) === []);
@endphp

{{-- Optional template + individual permissions. Effective permissions = template ∪ selected. --}}
<div class="space-y-4">
    @if ($templates->isNotEmpty())
        <flux:select wire:model="{{ $templateModel }}" label="Yetki şablonu (opsiyonel)">
            <flux:select.option value="">Şablon kullanma</flux:select.option>
            @foreach ($templates as $template)
                <flux:select.option value="{{ $template->id }}">{{ $template->name }} ({{ count($template->permissions) }} yetki)</flux:select.option>
            @endforeach
        </flux:select>
    @endif

    <div>
        <flux:label>{{ $templates->isNotEmpty() ? 'Ek / tek tek yetkiler' : 'Yetkiler' }}</flux:label>
        <div class="mt-2 grid gap-4 sm:grid-cols-2">
            @foreach (\App\Enums\Permission::groups() as $group => $permissions)
                @php($permissions = array_filter($permissions, fn ($p) => $allowed === null || in_array($p->value, $allowed, true)))
                @if ($permissions)
                    <flux:checkbox.group wire:model="{{ $permissionsModel }}" :label="$group">
                        @foreach ($permissions as $permission)
                            <flux:checkbox :value="$permission->value" :label="$permission->label()" />
                        @endforeach
                    </flux:checkbox.group>
                @endif
            @endforeach
        </div>
        <flux:error name="{{ $permissionsModel }}" />
    </div>
</div>
