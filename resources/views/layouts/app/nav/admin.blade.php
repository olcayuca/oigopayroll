{{-- admin.siteadi.com: system management, super admins only. --}}
<flux:sidebar.nav>
    <flux:sidebar.group class="grid">
        <flux:sidebar.item icon="home" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>
            Gösterge Paneli
        </flux:sidebar.item>
        <flux:sidebar.item icon="chart-bar" :href="route('admin.reports.index')" :current="request()->routeIs('admin.reports.*')" wire:navigate>
            Raporlar
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
        <flux:sidebar.item
            icon="user-group"
            :href="route('admin.specialists.index')"
            :current="request()->routeIs('admin.specialists.*')"
            :badge="\App\Models\Firm::where('status', \App\Enums\FirmStatus::Active)->whereNull('specialist_id')->count() ?: null"
            wire:navigate
        >
            Uzman Dağılımı
        </flux:sidebar.item>
        <flux:sidebar.item
            icon="document-text"
            :href="route('admin.documents.index')"
            :current="request()->routeIs('admin.documents.*')"
            :badge="\App\Models\FirmDocument::whereNotNull('valid_until')->whereDate('valid_until', '<', today())->count() ?: null"
            wire:navigate
        >
            Belge Takibi
        </flux:sidebar.item>
        <flux:sidebar.item icon="shield-check" :href="route('admin.permission-templates.index')" :current="request()->routeIs('admin.permission-templates.*')" wire:navigate>
            Yetki Şablonları
        </flux:sidebar.item>
    </flux:sidebar.group>

    <flux:sidebar.group heading="Bordro Tanımları" class="grid">
        <flux:sidebar.item icon="scale" :href="route('admin.parameters.index')" :current="request()->routeIs('admin.parameters.*')" wire:navigate>
            Yasal Parametreler
        </flux:sidebar.item>
        <flux:sidebar.item icon="list-bullet" :href="route('admin.codes.index')" :current="request()->routeIs('admin.codes.*')" wire:navigate>
            Bordro Kodları
        </flux:sidebar.item>
        <flux:sidebar.item icon="calendar-days" :href="route('admin.holidays.index')" :current="request()->routeIs('admin.holidays.*')" wire:navigate>
            Çalışma Takvimi
        </flux:sidebar.item>
    </flux:sidebar.group>

    <flux:sidebar.group heading="Sistem" class="grid">
        <flux:sidebar.item icon="globe-alt" :href="route('admin.website.index')" :current="request()->routeIs('admin.website.*')" wire:navigate>
            Web Sitesi
        </flux:sidebar.item>
        <flux:sidebar.item icon="trash" :href="route('admin.trash.index')" :current="request()->routeIs('admin.trash.*')" wire:navigate>
            Çöp Kutusu
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
        <flux:sidebar.item icon="heart" :href="route('admin.system.health')" :current="request()->routeIs('admin.system.*')" wire:navigate>
            Sistem Sağlığı
        </flux:sidebar.item>
        <flux:sidebar.item
            icon="finger-print"
            :href="route('admin.kvkk.index')"
            :current="request()->routeIs('admin.kvkk.*')"
            :badge="\App\Models\KvkkRequest::whereIn('status', [\App\Enums\KvkkRequestStatus::Open, \App\Enums\KvkkRequestStatus::InProgress])->count() ?: null"
            wire:navigate
        >
            KVKK
        </flux:sidebar.item>
    </flux:sidebar.group>
</flux:sidebar.nav>
