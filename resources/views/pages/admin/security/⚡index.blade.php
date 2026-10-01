<?php

use App\Enums\AuditEvent;
use App\Listeners\AuditAuthEvents;
use App\Models\AuditLog;
use App\Models\CredentialAccessLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\Audit;
use App\Support\SecuritySettings;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Güvenlik')] class extends Component {
    use WithPagination;

    #[Url(as: 'sekme', except: 'olaylar')]
    public string $tab = 'olaylar';

    // Event filters
    #[Url(as: 'grup', except: '')]
    public string $group = '';

    #[Url(as: 'ara', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $ip = '';

    #[Url(as: 'baslangic', except: '')]
    public string $from = '';

    #[Url(as: 'bitis', except: '')]
    public string $to = '';

    // Settings form
    public bool $twoFactorRequired = false;

    public string $ipAllowlist = '';

    public string $idleMinutes = '30';

    public string $attemptsPerMinute = '5';

    public string $attemptsPerHour = '30';

    public string $retentionDays = '365';

    public function mount(): void
    {
        $this->authorize('manage-settings');
        $this->fillSettings();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['tab', 'group', 'search', 'ip', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    // ---- Events ----------------------------------------------------------------

    /**
     * @return LengthAwarePaginator<int, AuditLog>
     */
    #[Computed]
    public function events(): LengthAwarePaginator
    {
        return $this->filtered(AuditLog::query())
            ->when($this->group !== '', fn (Builder $query) => $query->where('event', 'like', $this->group.'.%'))
            ->with('user')
            ->latest('id')
            ->paginate(30);
    }

    // ---- Failed logins ---------------------------------------------------------------

    /**
     * @return LengthAwarePaginator<int, AuditLog>
     */
    #[Computed]
    public function failures(): LengthAwarePaginator
    {
        return $this->filtered(AuditLog::query())
            ->whereIn('event', [AuditEvent::LoginFailed, AuditEvent::Lockout, AuditEvent::IpBlocked])
            ->latest('id')
            ->paginate(30);
    }

    /**
     * @return array{day: int, week: int, lockouts: int, blocked: int, topIps: Collection<int, object>, topEmails: Collection<int, object>}
     */
    #[Computed]
    public function failureStats(): array
    {
        $week = AuditLog::where('event', AuditEvent::LoginFailed)->where('created_at', '>=', now()->subDays(7));

        return [
            'day' => AuditLog::where('event', AuditEvent::LoginFailed)->where('created_at', '>=', now()->subDay())->count(),
            'week' => (clone $week)->count(),
            'lockouts' => AuditLog::where('event', AuditEvent::Lockout)->where('created_at', '>=', now()->subDays(7))->count(),
            'blocked' => AuditLog::where('event', AuditEvent::IpBlocked)->where('created_at', '>=', now()->subDays(7))->count(),
            'topIps' => (clone $week)->toBase()->selectRaw('ip_address, count(*) as total')
                ->groupBy('ip_address')->orderByDesc('total')->limit(5)->get(),
            'topEmails' => (clone $week)->get(['properties'])
                ->countBy(fn (AuditLog $log) => $log->properties['email'] ?? '—')
                ->sortDesc()->take(5)
                ->map(fn ($total, $email) => (object) ['email' => $email, 'total' => $total])->values(),
        ];
    }

    // ---- Sessions ----------------------------------------------------------------------

    /**
     * @return Collection<int, object>
     */
    #[Computed]
    public function sessions(): Collection
    {
        $rows = DB::table('sessions')->whereNotNull('user_id')->orderByDesc('last_activity')->get(['id', 'user_id', 'ip_address', 'user_agent', 'last_activity']);
        $users = User::whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');

        return $rows->map(fn ($row) => (object) [
            'id' => $row->id,
            'user' => $users[$row->user_id] ?? null,
            'ip' => $row->ip_address,
            'agent' => $this->describeAgent((string) $row->user_agent),
            'lastActivity' => \Illuminate\Support\Carbon::createFromTimestamp($row->last_activity),
            'current' => $row->id === session()->getId(),
        ]);
    }

    public function terminateSession(string $sessionId): void
    {
        $this->authorize('manage-settings');

        if ($sessionId === session()->getId()) {
            Flux::toast(variant: 'danger', text: 'Kendi oturumunuzu buradan kapatamazsınız; çıkış yapın.');

            return;
        }

        $userId = DB::table('sessions')->where('id', $sessionId)->value('user_id');
        DB::table('sessions')->where('id', $sessionId)->delete();

        $user = $userId ? User::find($userId) : null;
        Audit::log(AuditEvent::SessionTerminated, 'Oturum sonlandırıldı: '.($user->email ?? '?'), $user);

        unset($this->sessions);
        Flux::toast(variant: 'success', text: 'Oturum sonlandırıldı.');
    }

    public function terminateUserSessions(int $userId): void
    {
        $this->authorize('manage-settings');

        $count = DB::table('sessions')->where('user_id', $userId)->where('id', '!=', session()->getId())->delete();
        $user = User::find($userId);
        Audit::log(AuditEvent::SessionTerminated, "Tüm oturumlar sonlandırıldı ({$count}): ".($user->email ?? '?'), $user, ['count' => $count]);

        unset($this->sessions);
        Flux::toast(variant: 'success', text: "{$count} oturum sonlandırıldı.");
    }

    // ---- Credential reveals --------------------------------------------------------------

    /**
     * @return LengthAwarePaginator<int, CredentialAccessLog>
     */
    #[Computed]
    public function credentialLogs(): LengthAwarePaginator
    {
        return CredentialAccessLog::query()->with(['user', 'workplace.company'])->latest('created_at')->paginate(30);
    }

    // ---- Settings -------------------------------------------------------------------

    public function saveSettings(): void
    {
        $this->authorize('manage-settings');

        $this->validate([
            'idleMinutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'attemptsPerMinute' => ['required', 'integer', 'min:1', 'max:100'],
            'attemptsPerHour' => ['required', 'integer', 'min:1', 'max:1000'],
            'retentionDays' => ['required', 'integer', 'min:30', 'max:3650'],
            'ipAllowlist' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'idleMinutes' => 'Hareketsizlik süresi',
            'attemptsPerMinute' => 'Dakikalık deneme sınırı',
            'attemptsPerHour' => 'Saatlik deneme sınırı',
            'retentionDays' => 'Kayıt saklama süresi',
            'ipAllowlist' => 'İzinli IP listesi',
        ]);

        $entries = SecuritySettings::parseIpList($this->ipAllowlist);
        $invalid = SecuritySettings::invalidIpEntries($entries);

        if ($invalid !== []) {
            throw ValidationException::withMessages(['ipAllowlist' => 'Geçersiz IP / aralık: '.implode(', ', $invalid)]);
        }

        // Never let an admin lock themselves out.
        if ($entries !== [] && ! \Symfony\Component\HttpFoundation\IpUtils::checkIp((string) request()->ip(), $entries)) {
            throw ValidationException::withMessages([
                'ipAllowlist' => 'Şu anki IP adresiniz ('.request()->ip().') listede yok. Kaydederseniz yönetim paneline erişiminiz kesilir.',
            ]);
        }

        $values = [
            SecuritySettings::TWO_FACTOR_REQUIRED => $this->twoFactorRequired,
            SecuritySettings::IP_ALLOWLIST => implode("\n", $entries),
            SecuritySettings::IDLE_MINUTES => (int) $this->idleMinutes,
            SecuritySettings::ATTEMPTS_PER_MINUTE => (int) $this->attemptsPerMinute,
            SecuritySettings::ATTEMPTS_PER_HOUR => (int) $this->attemptsPerHour,
            SecuritySettings::AUDIT_RETENTION_DAYS => (int) $this->retentionDays,
        ];

        Setting::putMany($values);
        Audit::log(AuditEvent::SettingsChanged, 'Güvenlik ayarları güncellendi', null, [
            'two_factor_required' => $this->twoFactorRequired,
            'ip_allowlist_entries' => count($entries),
            'idle_minutes' => (int) $this->idleMinutes,
            'attempts_per_minute' => (int) $this->attemptsPerMinute,
            'attempts_per_hour' => (int) $this->attemptsPerHour,
            'retention_days' => (int) $this->retentionDays,
        ]);

        $this->fillSettings();
        Flux::toast(variant: 'success', text: 'Güvenlik ayarları kaydedildi.');

        if ($this->twoFactorRequired && auth()->user()->two_factor_confirmed_at === null) {
            $this->redirectRoute('security.edit');
        }
    }

    public function reasonLabel(?string $reason): string
    {
        return AuditAuthEvents::REASONS[$reason] ?? '—';
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    private function filtered(Builder $query): Builder
    {
        return $query
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('description', 'like', '%'.$this->search.'%')
                ->orWhereHas('user', fn (Builder $query) => $query
                    ->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%'))))
            ->when($this->ip !== '', fn (Builder $query) => $query->where('ip_address', 'like', $this->ip.'%'))
            ->when($this->from !== '', fn (Builder $query) => $query->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($this->to !== '', fn (Builder $query) => $query->where('created_at', '<=', $this->to.' 23:59:59'));
    }

    private function fillSettings(): void
    {
        $this->twoFactorRequired = SecuritySettings::adminTwoFactorRequired();
        $this->ipAllowlist = implode("\n", SecuritySettings::adminIpAllowlist());
        $this->idleMinutes = (string) SecuritySettings::adminIdleMinutes();
        $this->attemptsPerMinute = (string) SecuritySettings::loginAttemptsPerMinute();
        $this->attemptsPerHour = (string) SecuritySettings::loginAttemptsPerHour();
        $this->retentionDays = (string) SecuritySettings::auditRetentionDays();
    }

    private function describeAgent(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Bilinmeyen tarayıcı',
        };
        $os = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => '',
        };

        return trim($browser.' · '.$os, ' ·');
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">Güvenlik</flux:heading>
        <flux:text class="mt-1">Olay kayıtları, başarısız girişler, aktif oturumlar ve güvenlik ayarları.</flux:text>
    </div>

    @if ($this->failureStats['day'] >= 10)
        <flux:callout icon="shield-exclamation" color="red" heading="Son 24 saatte {{ $this->failureStats['day'] }} başarısız giriş denemesi"
            text="Başarısız Girişler sekmesinden kaynak IP ve hesapları inceleyin; gerekirse IP kısıtı tanımlayın." />
    @endif

    <x-tabs :active="$tab"
        :tabs="['olaylar' => 'Olay Kayıtları', 'basarisiz' => 'Başarısız Girişler', 'oturumlar' => 'Aktif Oturumlar', 'sifreler' => 'Şifre Görüntülemeleri', 'ayarlar' => 'Ayarlar']"
        :counts="['basarisiz' => $this->failureStats['day'], 'oturumlar' => $this->sessions->count()]"
        :invalid="$errors->any() ? ['ayarlar'] : []" />

    @if (in_array($tab, ['olaylar', 'basarisiz'], true))
        <div class="flex flex-wrap items-end gap-3">
            <div class="w-full sm:w-64">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Açıklama, kullanıcı, e-posta..." clearable />
            </div>
            @if ($tab === 'olaylar')
                <div class="w-44">
                    <flux:select wire:model.live="group">
                        <flux:select.option value="">Tüm olaylar</flux:select.option>
                        @foreach (AuditEvent::groups() as $key => $label)
                            <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endif
            <div class="w-40"><flux:input wire:model.live.debounce.300ms="ip" placeholder="IP adresi" /></div>
            <div class="w-40"><flux:input wire:model.live="from" type="date" /></div>
            <div class="w-40"><flux:input wire:model.live="to" type="date" /></div>
        </div>
    @endif

    @if ($tab === 'olaylar')
        <flux:table :paginate="$this->events">
            <flux:table.columns>
                <flux:table.column>Zaman</flux:table.column>
                <flux:table.column>Kullanıcı</flux:table.column>
                <flux:table.column>Olay</flux:table.column>
                <flux:table.column>Açıklama</flux:table.column>
                <flux:table.column>IP / Panel</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->events as $log)
                    <flux:table.row :key="$log->id">
                        <flux:table.cell class="whitespace-nowrap">{{ $log->created_at->format('d.m.Y H:i:s') }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $log->user->name ?? '—' }}
                            @if ($log->user) <div class="text-xs text-zinc-500">{{ $log->user->email }}</div> @endif
                        </flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$log->event->color()" inset="top bottom">{{ $log->event->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell class="max-w-md whitespace-normal">
                            {{ $log->description }}
                            @if (isset($log->properties['impersonated_by']))
                                <flux:badge size="sm" color="amber" inset="top bottom">Destek görünümü · {{ \App\Models\User::find($log->properties['impersonated_by'])->name ?? '#'.$log->properties['impersonated_by'] }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">
                            {{ $log->ip_address ?? '—' }}
                            <div class="text-xs text-zinc-500">{{ $log->portal }}</div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-10 text-center text-zinc-500">Kayıt bulunamadı.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    @if ($tab === 'basarisiz')
        @php($stats = $this->failureStats)
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:text>Son 24 saat</flux:text>
                <div class="mt-2 text-3xl font-semibold {{ $stats['day'] ? 'text-red-500' : '' }}">{{ $stats['day'] }}</div>
            </div>
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:text>Son 7 gün</flux:text>
                <div class="mt-2 text-3xl font-semibold">{{ $stats['week'] }}</div>
            </div>
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:text>Kilitlenme (7 gün)</flux:text>
                <div class="mt-2 text-3xl font-semibold">{{ $stats['lockouts'] }}</div>
            </div>
            <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:text>Engellenen IP erişimi (7 gün)</flux:text>
                <div class="mt-2 text-3xl font-semibold">{{ $stats['blocked'] }}</div>
            </div>
        </div>

        @if ($stats['week'] > 0)
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:heading>En çok deneme yapan IP'ler (7 gün)</flux:heading>
                    <ul class="mt-3 space-y-1 text-sm">
                        @foreach ($stats['topIps'] as $row)
                            <li class="flex justify-between">
                                <button type="button" class="font-mono hover:underline" wire:click="$set('ip', '{{ $row->ip_address }}')">{{ $row->ip_address }}</button>
                                <span>{{ $row->total }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                    <flux:heading>En çok denenen hesaplar (7 gün)</flux:heading>
                    <ul class="mt-3 space-y-1 text-sm">
                        @foreach ($stats['topEmails'] as $row)
                            <li class="flex justify-between"><span>{{ $row->email }}</span><span>{{ $row->total }}</span></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <flux:table :paginate="$this->failures">
            <flux:table.columns>
                <flux:table.column>Zaman</flux:table.column>
                <flux:table.column>Olay</flux:table.column>
                <flux:table.column>E-posta</flux:table.column>
                <flux:table.column>Sebep</flux:table.column>
                <flux:table.column>IP / Panel</flux:table.column>
                <flux:table.column>Tarayıcı</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->failures as $log)
                    <flux:table.row :key="$log->id">
                        <flux:table.cell class="whitespace-nowrap">{{ $log->created_at->format('d.m.Y H:i:s') }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$log->event->color()" inset="top bottom">{{ $log->event->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell>{{ $log->properties['email'] ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $log->event === AuditEvent::LoginFailed ? $this->reasonLabel($log->properties['reason'] ?? null) : '—' }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $log->ip_address }} <div class="text-xs text-zinc-500">{{ $log->portal }}</div></flux:table.cell>
                        <flux:table.cell class="max-w-xs truncate text-xs text-zinc-500" title="{{ $log->user_agent }}">{{ $log->user_agent }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-10 text-center text-zinc-500">Başarısız giriş kaydı yok.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    @if ($tab === 'oturumlar')
        <flux:text>Oturum açık olan kullanıcılar (admin ve panel). Sonlandırılan oturumun kullanıcısı bir sonraki işleminde giriş sayfasına yönlendirilir.</flux:text>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Kullanıcı</flux:table.column>
                <flux:table.column>Cihaz</flux:table.column>
                <flux:table.column>IP</flux:table.column>
                <flux:table.column>Son işlem</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->sessions as $session)
                    <flux:table.row :key="$session->id">
                        <flux:table.cell variant="strong">
                            {{ $session->user->name ?? '(silinmiş kullanıcı)' }}
                            @if ($session->current) <flux:badge size="sm" color="green" inset="top bottom">Bu oturum</flux:badge> @endif
                            @if ($session->user) <div class="text-xs font-normal text-zinc-500">{{ $session->user->email }} · {{ $session->user->type->label() }}</div> @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $session->agent }}</flux:table.cell>
                        <flux:table.cell class="font-mono">{{ $session->ip }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $session->lastActivity->diffForHumans() }}</flux:table.cell>
                        <flux:table.cell align="end" class="whitespace-nowrap">
                            @unless ($session->current)
                                <flux:button size="sm" variant="ghost" icon="x-circle" wire:click="terminateSession('{{ $session->id }}')"
                                    wire:confirm="Bu oturum sonlandırılsın mı?">Sonlandır</flux:button>
                                @if ($session->user)
                                    <flux:button size="sm" variant="ghost" icon="no-symbol" wire:click="terminateUserSessions({{ $session->user->id }})"
                                        wire:confirm="{{ $session->user->name }} kullanıcısının tüm oturumları sonlandırılsın mı?">Tümü</flux:button>
                                @endif
                            @endunless
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-10 text-center text-zinc-500">Aktif oturum yok.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    @if ($tab === 'sifreler')
        <flux:table :paginate="$this->credentialLogs">
            <flux:table.columns>
                <flux:table.column>Zaman</flux:table.column>
                <flux:table.column>Kullanıcı</flux:table.column>
                <flux:table.column>İşyeri</flux:table.column>
                <flux:table.column>Görüntülenen alan</flux:table.column>
                <flux:table.column>IP</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->credentialLogs as $log)
                    <flux:table.row :key="$log->id">
                        <flux:table.cell class="whitespace-nowrap">{{ $log->created_at->format('d.m.Y H:i:s') }}</flux:table.cell>
                        <flux:table.cell>{{ $log->user?->name ?? '(silinmiş)' }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $log->workplace->branch_name }}
                            <div class="text-xs text-zinc-500">{{ $log->workplace->company->title }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ \App\Validation\WorkplaceRules::attributes()[$log->field] ?? $log->field }}</flux:table.cell>
                        <flux:table.cell>{{ $log->ip_address }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-8 text-center text-zinc-500">Henüz şifre görüntüleme kaydı yok.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    @if ($tab === 'ayarlar')
        <form wire:submit="saveSettings" class="max-w-3xl space-y-6">
            <section class="space-y-3 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading>İki adımlı doğrulama</flux:heading>
                <flux:switch wire:model="twoFactorRequired" label="Yönetim paneli için iki adımlı doğrulama zorunlu" />
                <flux:text size="sm">
                    Açıkken iki adımlı doğrulaması olmayan süper adminler, kurulumu tamamlayana kadar yalnızca güvenlik ayarları
                    sayfasına erişebilir.
                    @if (! auth()->user()->two_factor_confirmed_at)
                        <strong>Sizin hesabınızda iki adımlı doğrulama henüz kurulu değil.</strong>
                    @endif
                </flux:text>
            </section>

            <section class="space-y-3 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading>IP kısıtı</flux:heading>
                <flux:textarea wire:model="ipAllowlist" rows="4" placeholder="Boş bırakılırsa her IP'den erişilebilir.&#10;Örnek:&#10;85.105.12.34&#10;192.168.1.0/24"
                    label="Yönetim paneline erişebilecek IP adresleri / aralıkları" />
                <flux:text size="sm">Her satıra bir IP veya CIDR aralığı. Şu anki IP adresiniz: <span class="font-mono">{{ request()->ip() }}</span></flux:text>
            </section>

            <section class="space-y-3 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading>Oturum ve giriş denemeleri</flux:heading>
                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:input wire:model="idleMinutes" type="number" min="0" label="Hareketsizlikte çıkış (dk)" description="0 = kapalı. Yalnızca yönetim paneli." />
                    <flux:input wire:model="attemptsPerMinute" type="number" min="1" label="Deneme / dakika" description="E-posta + IP başına." />
                    <flux:input wire:model="attemptsPerHour" type="number" min="1" label="Deneme / saat" description="Hesap başına, tüm IP'lerden." />
                </div>
            </section>

            <section class="space-y-3 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading>Kayıtlar</flux:heading>
                <div class="w-64">
                    <flux:input wire:model="retentionDays" type="number" min="30" label="Olay kayıtları saklama süresi (gün)" />
                </div>
            </section>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">Kaydet</flux:button>
            </div>
        </form>
    @endif
</div>
