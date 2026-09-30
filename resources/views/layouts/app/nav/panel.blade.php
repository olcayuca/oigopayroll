{{-- panel.siteadi.com: day-to-day work, in the context of the selected firm. --}}
<livewire:firm-switcher />

<flux:sidebar.nav>
    <flux:sidebar.group class="grid">
        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
            Gösterge Paneli
        </flux:sidebar.item>
    </flux:sidebar.group>

    <flux:sidebar.group heading="Firma Bilgileri" class="grid">
        <flux:sidebar.item icon="building-office" badge="Yakında">Şirketler</flux:sidebar.item>
        <flux:sidebar.item icon="map-pin" badge="Yakında">İşyerleri</flux:sidebar.item>
    </flux:sidebar.group>

    <flux:sidebar.group heading="Bordro" class="grid">
        <flux:sidebar.item icon="identification" badge="Yakında">Çalışanlar</flux:sidebar.item>
        <flux:sidebar.item icon="banknotes" badge="Yakında">Bordrolar</flux:sidebar.item>
    </flux:sidebar.group>
</flux:sidebar.nav>
