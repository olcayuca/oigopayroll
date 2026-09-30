{{-- admin.siteadi.com: system management, super admins only. --}}
<flux:sidebar.nav>
    <flux:sidebar.group class="grid">
        <flux:sidebar.item icon="home" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>
            Gösterge Paneli
        </flux:sidebar.item>
    </flux:sidebar.group>

    <flux:sidebar.group heading="Yönetim" class="grid">
        <flux:sidebar.item
            icon="building-office-2"
            :href="route('admin.firms.index')"
            :current="request()->routeIs('admin.firms.*')"
            :badge="\App\Models\Firm::where('status', \App\Enums\FirmStatus::Pending)->count() ?: null"
            wire:navigate
        >
            Firmalar
        </flux:sidebar.item>
        <flux:sidebar.item icon="users" :href="route('admin.users.index')" :current="request()->routeIs('admin.users.*')" wire:navigate>
            Kullanıcılar
        </flux:sidebar.item>
        <flux:sidebar.item icon="shield-check" :href="route('admin.permission-templates.index')" :current="request()->routeIs('admin.permission-templates.*')" wire:navigate>
            Yetki Şablonları
        </flux:sidebar.item>
    </flux:sidebar.group>

    <flux:sidebar.group heading="Sistem" class="grid">
        <flux:sidebar.item icon="globe-alt" :href="route('admin.website.index')" :current="request()->routeIs('admin.website.*')" wire:navigate>
            Web Sitesi
        </flux:sidebar.item>
        <flux:sidebar.item icon="adjustments-horizontal" :href="route('admin.settings.index')" :current="request()->routeIs('admin.settings.*')" wire:navigate>
            Sistem Ayarları
        </flux:sidebar.item>
        <flux:sidebar.item
            icon="shield-exclamation"
            :href="route('admin.security.index')"
            :current="request()->routeIs('admin.security.*')"
            :badge="\App\Models\AuditLog::where('event', \App\Enums\AuditEvent::LoginFailed)->where('created_at', '>=', now()->subDay())->count() ?: null"
            wire:navigate
        >
            Güvenlik
        </flux:sidebar.item>
    </flux:sidebar.group>
</flux:sidebar.nav>
