@php
    // Panel users reach these pages from Ayarlar (pages::panel.settings.index); the admin portal keeps its own list.
    $panelSettings = \App\Enums\Portal::fromHost(request()->getHost()) === \App\Enums\Portal::Panel && auth()->user()?->activeFirm();
@endphp

<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        @if ($panelSettings)
            <a href="{{ route('settings.index') }}" wire:navigate class="mb-3 inline-block text-[13px] font-bold text-brand hover:text-mint">‹ Ayarlar</a>
        @endif
        <flux:navlist aria-label="{{ __('Settings') }}">
            @unless ($panelSettings)
                <flux:navlist.item :href="route('profile.edit')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
            @endunless
            <flux:navlist.item :href="route('security.edit')" wire:navigate>{{ __('Security') }}</flux:navlist.item>
            @unless ($panelSettings)
                <flux:navlist.item :href="route('appearance.edit')" wire:navigate>{{ __('Appearance') }}</flux:navlist.item>
            @endunless
            <flux:navlist.item :href="route('kvkk.edit')" wire:navigate>KVKK</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
