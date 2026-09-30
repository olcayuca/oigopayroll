@props(['email' => null, 'password' => null, 'next' => null, 'nextLabel' => 'Devam'])

{{-- Shown once after creating a user or resetting a password. --}}
<flux:modal name="temporary-password" class="md:w-[28rem]">
    <div class="space-y-4">
        <flux:heading size="lg">Geçici şifre</flux:heading>
        <flux:text>
            Bu şifre yalnızca şimdi gösteriliyor. Kullanıcıya iletin; ilk girişten sonra
            <strong>Ayarlar → Güvenlik</strong> bölümünden değiştirmesini isteyin.
        </flux:text>

        <div class="space-y-2 rounded-lg bg-zinc-100 p-4 font-mono text-sm dark:bg-zinc-900">
            <div>E-posta: {{ $email }}</div>
            <div>Şifre: <span class="select-all font-semibold">{{ $password }}</span></div>
        </div>

        <div class="flex justify-end gap-2">
            @if ($next)
                <flux:modal.close><flux:button variant="filled">Kapat</flux:button></flux:modal.close>
                <flux:button variant="primary" :href="$next" wire:navigate>{{ $nextLabel }}</flux:button>
            @else
                <flux:modal.close><flux:button variant="primary">Tamam</flux:button></flux:modal.close>
            @endif
        </div>
    </div>
</flux:modal>
