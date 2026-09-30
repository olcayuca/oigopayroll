{{-- panel.siteadi.com: day-to-day work, in the context of the selected firm. --}}
<livewire:firm-switcher />

<flux:sidebar.nav>
    <flux:sidebar.group class="grid">
        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
            Gösterge Paneli
        </flux:sidebar.item>
    </flux:sidebar.group>

    <flux:sidebar.group heading="Firma Bilgileri" class="grid">
        <flux:sidebar.item icon="building-office" :href="route('companies.index')"
            :current="request()->routeIs('companies.*') || request()->routeIs('imports.*') && request()->route('type') === 'sirket'" wire:navigate>
            Şirketler
        </flux:sidebar.item>
        <flux:sidebar.item icon="map-pin" :href="route('workplaces.index')"
            :current="request()->routeIs('workplaces.*') || request()->routeIs('imports.*') && request()->route('type') === 'isyeri'" wire:navigate>
            İşyerleri
        </flux:sidebar.item>
        <flux:sidebar.item icon="trash" :href="route('trash.index')" :current="request()->routeIs('trash.*')" wire:navigate>
            Çöp Kutusu
        </flux:sidebar.item>
    </flux:sidebar.group>

    @php($activeFirm = auth()->user()->activeFirm())
    @if ($activeFirm && auth()->user()->can('manageUsers', $activeFirm))
        <flux:sidebar.group heading="Firma Yönetimi" class="grid">
            <flux:sidebar.item icon="users" :href="route('users.index')" :current="request()->routeIs('users.*')" wire:navigate>
                Kullanıcılar
            </flux:sidebar.item>
            <flux:sidebar.item icon="link" :href="route('firm-access.index')" :current="request()->routeIs('firm-access.*')" wire:navigate>
                Firma Erişimleri
            </flux:sidebar.item>
        </flux:sidebar.group>
    @endif

    <flux:sidebar.group heading="Bordro" class="grid">
        <flux:sidebar.item icon="identification" badge="Yakında">Çalışanlar</flux:sidebar.item>
        <flux:sidebar.item icon="banknotes" badge="Yakında">Bordrolar</flux:sidebar.item>
    </flux:sidebar.group>
</flux:sidebar.nav>
