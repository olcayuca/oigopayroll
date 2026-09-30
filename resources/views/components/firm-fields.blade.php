@props(['model' => 'firmForm'])

{{-- Firma fields bound to a Livewire array property (default: $firmForm). Only the name is required. --}}
<div class="grid gap-4 sm:grid-cols-2">
    <flux:input wire:model="{{ $model }}.name" label="Firma Adı" required />
    <flux:input wire:model="{{ $model }}.title" label="Unvan" />
    <flux:input wire:model="{{ $model }}.tax_number" label="Vergi Numarası" inputmode="numeric" maxlength="11"
        description="10 haneli VKN veya şahıs firmasında 11 haneli TCKN." />
    <flux:input wire:model="{{ $model }}.tax_office" label="Vergi Dairesi" />
    <flux:input wire:model="{{ $model }}.contact_name" label="Yetkili Kişi" />
    <flux:input wire:model="{{ $model }}.phone" label="Telefon" />
    <flux:input wire:model="{{ $model }}.email" type="email" label="E-posta" />
    <div class="sm:col-span-2">
        <flux:textarea wire:model="{{ $model }}.address" label="Adres" rows="2" />
    </div>
</div>
