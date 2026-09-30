<?php

use App\Enums\AuditEvent;
use App\Enums\FirmStatus;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use App\Models\Company;
use App\Models\Firm;
use App\Models\Workplace;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Gösterge Paneli')] class extends Component {
    /**
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        $user = Auth::user();

        return [
            'pending' => Firm::visibleTo($user)->where('status', FirmStatus::Pending)->count(),
            'active' => Firm::visibleTo($user)->where('status', FirmStatus::Active)->count(),
            'companies' => Company::visibleTo($user)->count(),
            'workplaces' => Workplace::visibleTo($user)->count(),
            'companiesWithoutWorkplace' => Company::visibleTo($user)->withoutWorkplaces()->count(),
            'failedLogins' => AuditLog::where('event', AuditEvent::LoginFailed)->where('created_at', '>=', now()->subDay())->count(),
            'activeSessions' => DB::table('sessions')->whereNotNull('user_id')->count(),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, AuditLog>
     */
    #[Computed]
    public function recentEvents(): \Illuminate\Database\Eloquent\Collection
    {
        return AuditLog::with('user')->latest('id')->limit(8)->get();
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">Hoş geldiniz, {{ auth()->user()->name }}</flux:heading>
        <flux:text class="mt-1">{{ auth()->user()->type->label() }}</flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('admin.firms.index', ['status' => 'pending']) }}" wire:navigate
           class="rounded-xl border border-zinc-200 p-5 transition hover:border-amber-400 dark:border-zinc-700">
            <flux:text>Onay bekleyen firma</flux:text>
            <div class="mt-2 text-3xl font-semibold {{ $this->stats['pending'] ? 'text-amber-500' : '' }}">{{ $this->stats['pending'] }}</div>
        </a>

        <a href="{{ route('admin.firms.index', ['status' => 'active']) }}" wire:navigate
           class="rounded-xl border border-zinc-200 p-5 transition hover:border-zinc-400 dark:border-zinc-700">
            <flux:text>Aktif firma</flux:text>
            <div class="mt-2 text-3xl font-semibold">{{ $this->stats['active'] }}</div>
        </a>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>Şirket</flux:text>
            <div class="mt-2 text-3xl font-semibold">{{ $this->stats['companies'] }}</div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text>İşyeri</flux:text>
            <div class="mt-2 text-3xl font-semibold">{{ $this->stats['workplaces'] }}</div>
        </div>
    </div>

    @if ($this->stats['companiesWithoutWorkplace'] > 0)
        <flux:callout icon="exclamation-triangle" color="amber"
            heading="{{ $this->stats['companiesWithoutWorkplace'] }} şirketin henüz işyeri yok"
            text="Her şirketin altında en az bir işyeri tanımlanması zorunludur." />
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4">
            <a href="{{ route('admin.security.index', ['sekme' => 'basarisiz']) }}" wire:navigate
               class="block rounded-xl border border-zinc-200 p-5 transition hover:border-red-400 dark:border-zinc-700">
                <flux:text>Başarısız giriş (24 saat)</flux:text>
                <div class="mt-2 text-3xl font-semibold {{ $this->stats['failedLogins'] ? 'text-red-500' : '' }}">{{ $this->stats['failedLogins'] }}</div>
            </a>
            <a href="{{ route('admin.security.index', ['sekme' => 'oturumlar']) }}" wire:navigate
               class="block rounded-xl border border-zinc-200 p-5 transition hover:border-zinc-400 dark:border-zinc-700">
                <flux:text>Aktif oturum</flux:text>
                <div class="mt-2 text-3xl font-semibold">{{ $this->stats['activeSessions'] }}</div>
            </a>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 lg:col-span-2 dark:border-zinc-700">
            <div class="flex items-center justify-between">
                <flux:heading>Son olaylar</flux:heading>
                <flux:link :href="route('admin.security.index')" wire:navigate class="text-sm">Tümü</flux:link>
            </div>
            <ul class="mt-3 divide-y divide-zinc-100 text-sm dark:divide-zinc-700">
                @forelse ($this->recentEvents as $log)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <div class="min-w-0">
                            <flux:badge size="sm" :color="$log->event->color()">{{ $log->event->label() }}</flux:badge>
                            <span class="ms-1 truncate">{{ $log->description }}</span>
                        </div>
                        <span class="shrink-0 text-xs text-zinc-500">{{ $log->user->name ?? '' }} · {{ $log->created_at->diffForHumans() }}</span>
                    </li>
                @empty
                    <li class="py-4 text-center text-zinc-500">Henüz kayıt yok.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
