@php
    use App\Models\Company;
    use App\Models\Workplace;
    use App\Support\Impersonation;

    $user = auth()->user();
    $firm = $user->activeFirm();
    $canManageUsers = $firm && $user->can('manageUsers', $firm);

    $counts = $firm ? [
        'companies' => Company::visibleTo($user)->where('firm_id', $firm->id)->count(),
        'workplaces' => Workplace::visibleTo($user)->whereHas('company', fn ($query) => $query->where('firm_id', $firm->id))->count(),
        'employees' => \App\Models\Employee::viewableBy($user, $firm)->where('status', \App\Models\Employee::ACTIVE)->count(),
    ] : [];

    $isImport = fn (string $type) => request()->routeIs('imports.*') && request()->route('type') === $type;

    // label, route (null = coming soon), active?, count
    $navGroups = [
        'KURULUM' => [
            ['label' => 'Şirketler', 'route' => 'companies.index', 'active' => request()->routeIs('companies.*') || $isImport('sirket'), 'count' => $counts['companies'] ?? null],
            ['label' => 'İşyerleri', 'route' => 'workplaces.index', 'active' => request()->routeIs('workplaces.*') || $isImport('isyeri'), 'count' => $counts['workplaces'] ?? null],
            ['label' => 'Belgeler', 'route' => 'documents.index', 'active' => request()->routeIs('documents.*')],
            ['label' => 'Personel', 'route' => $firm ? 'employees.index' : null, 'active' => request()->routeIs('employees.*') || $isImport('personel'), 'count' => $counts['employees'] ?? null],
            ['label' => 'Tanımlar', 'route' => $firm ? 'definitions.index' : null, 'active' => request()->routeIs('definitions.*')],
            ['label' => 'Kurulum Sihirbazı', 'route' => $firm ? 'setup.wizard' : null, 'active' => request()->routeIs('setup.*')],
        ],
        'OPERASYON' => [
            ['label' => 'Gösterge Paneli', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard')],
            ['label' => 'Bordro Dönemleri', 'route' => null],
            ['label' => 'İzinler', 'route' => null],
            ['label' => 'Avans & Borçlar', 'route' => null],
            ['label' => 'Hesaplamalar', 'route' => null],
            ['label' => 'Raporlar', 'route' => null],
        ],
        'YÖNETİM' => array_values(array_filter([
            $canManageUsers ? ['label' => 'Kullanıcılar & Yetkiler', 'route' => 'users.index', 'active' => request()->routeIs('users.*')] : null,
            $canManageUsers ? ['label' => 'Firma Erişimleri', 'route' => 'firm-access.index', 'active' => request()->routeIs('firm-access.*')] : null,
            $firm && $user->can('viewAudit', $firm) ? ['label' => 'İşlem Geçmişi', 'route' => 'audit.index', 'active' => request()->routeIs('audit.*')] : null,
            ['label' => 'Bildirimler', 'route' => 'notifications.index', 'active' => request()->routeIs('notifications.*')],
            ['label' => 'Çöp Kutusu', 'route' => 'trash.index', 'active' => request()->routeIs('trash.*')],
            ['label' => 'Ayarlar', 'route' => 'profile.edit', 'active' => request()->routeIs('profile.*', 'security.*', 'kvkk.edit', 'appearance.*')],
        ])),
    ];
@endphp
<!DOCTYPE html>
<html lang="tr" class="oigo">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>{{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}</title>
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-canvas" x-data="{ nav: false }" x-on:keydown.escape.window="nav = false">
        <div class="flex min-h-screen">
            {{-- Mobile backdrop --}}
            <div x-cloak x-show="nav" x-transition.opacity x-on:click="nav = false" class="fixed inset-0 z-40 bg-[#0E2038]/50 lg:hidden"></div>

            {{-- Sidebar --}}
            <aside class="oigo-side fixed inset-y-0 start-0 z-50 flex w-64 shrink-0 -translate-x-full flex-col overflow-y-auto bg-linear-to-b from-side to-side-2 transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
                x-bind:class="nav && '!translate-x-0'" aria-label="Ana menü">
                <div class="flex items-center gap-[11px] border-b border-white/7 px-5 pt-[22px] pb-[18px]">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-[11px]">
                        <span class="flex size-[38px] shrink-0 items-center justify-center rounded-[10px] bg-mint text-lg font-extrabold text-white">O</span>
                        <span class="flex flex-col leading-[1.1]">
                            <span class="text-base font-extrabold text-white">{{ config('app.name', 'OigoPayroll') }}</span>
                            <span class="text-[9px] font-bold tracking-[0.17em] text-mint-light">PANEL</span>
                        </span>
                    </a>
                    <button type="button" x-on:click="nav = false" class="ms-auto rounded-lg p-1 text-side-text hover:text-white lg:hidden" aria-label="Menüyü kapat">
                        <flux:icon.x-mark variant="mini" />
                    </button>
                </div>

                <nav class="flex-1 py-2.5">
                    @foreach ($navGroups as $heading => $items)
                        <div class="px-6 pt-4 pb-2 text-[10.5px] font-bold tracking-[0.12em] text-side-heading">{{ $heading }}</div>
                        @foreach ($items as $item)
                            @if ($item['route'])
                                <a href="{{ route($item['route']) }}" wire:navigate aria-current="{{ ($item['active'] ?? false) ? 'page' : 'false' }}"
                                    @class([
                                        'mx-3 my-px flex items-center gap-3 rounded-[10px] py-[9px] ps-[21px] pe-3.5 text-[13.5px] font-semibold transition select-none',
                                        'bg-white/7 text-white shadow-[inset_3px_0_0_0_var(--color-mint)]' => $item['active'] ?? false,
                                        'text-side-text hover:bg-white/4 hover:text-white' => ! ($item['active'] ?? false),
                                    ])>
                                    <span class="size-[7px] shrink-0 rounded-[2px] bg-current opacity-55"></span>
                                    <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                    @if (($item['count'] ?? null) !== null)
                                        <span class="rounded-md bg-white/8 px-[7px] py-px text-[11px] font-bold tabular-nums">{{ $item['count'] }}</span>
                                    @endif
                                </a>
                            @else
                                <div class="mx-3 my-px flex cursor-default items-center gap-3 rounded-[10px] py-[9px] ps-[21px] pe-3.5 text-[13.5px] font-semibold text-side-text/45 select-none" title="Yakında">
                                    <span class="size-[7px] shrink-0 rounded-[2px] bg-current opacity-55"></span>
                                    <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                    <span class="rounded-md border border-white/10 px-1.5 text-[9.5px] font-bold tracking-wide text-side-text/60">YAKINDA</span>
                                </div>
                            @endif
                        @endforeach
                    @endforeach
                </nav>

                @if ($firm?->specialist?->is_active)
                    <div class="m-3 rounded-xl border border-white/7 bg-white/4 p-3.5">
                        <div class="text-[10.5px] font-bold tracking-[0.12em] text-side-heading uppercase">Sorumlu bordro uzmanınız</div>
                        <div class="mt-1.5 truncate text-[13px] font-bold text-white">{{ $firm->specialist->name }}</div>
                        <a href="mailto:{{ $firm->specialist->email }}" class="block truncate text-xs text-side-text hover:text-mint-light">{{ $firm->specialist->email }}</a>
                    </div>
                @endif
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                {{-- Top bar --}}
                <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-2.5 border-b border-[#E8EDF3] bg-white px-4 sm:gap-3 sm:px-7">
                    <button type="button" x-on:click="nav = true" class="flex size-10 shrink-0 items-center justify-center rounded-[10px] border-[1.5px] border-[#E8EDF3] text-ink-2 lg:hidden" aria-label="Menüyü aç">
                        <flux:icon.bars-3 variant="mini" />
                    </button>

                    @if ($firm)
                        <livewire:panel-search />
                    @endif

                    <div class="hidden min-w-0 flex-1 md:flex">
                        <livewire:announcements />
                    </div>
                    <div class="flex-1 md:hidden"></div>

                    <livewire:firm-switcher />
                    <livewire:notification-bell variant="topbar" />

                    <div class="mx-1 hidden h-7 w-px bg-[#E8EDF3] sm:block"></div>

                    {{-- User menu --}}
                    <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
                        <button type="button" x-on:click="open = ! open" data-test="sidebar-menu-button"
                            class="flex items-center gap-2.5 rounded-[11px] p-1 transition sm:pe-2" x-bind:class="open ? 'bg-canvas' : 'hover:bg-canvas'">
                            <x-panel.avatar :initials="$user->initials()" tone="mint" class="!size-9 !rounded-[10px] !text-[13px]" />
                            <span class="hidden flex-col text-start leading-tight xl:flex">
                                <span class="max-w-40 truncate text-[13px] font-extrabold text-ink">{{ $user->name }}</span>
                                <span class="max-w-40 truncate text-[11px] font-semibold text-muted-2">{{ $user->type->label() }}</span>
                            </span>
                            <flux:icon.chevron-down variant="micro" class="hidden size-4 text-faint xl:block" />
                        </button>

                        <div x-cloak x-show="open" x-transition.opacity.duration.150ms
                            class="absolute end-0 top-[52px] z-30 w-[260px] overflow-hidden rounded-[14px] border border-[#E5EAF1] bg-white shadow-[0_16px_40px_rgba(16,40,72,0.16)]">
                            <div class="border-b border-line-3 px-4 py-3.5">
                                <div class="truncate text-[13.5px] font-extrabold text-ink">{{ $user->name }}</div>
                                <div class="truncate text-xs text-muted-2">{{ $user->email }}</div>
                            </div>
                            <div class="p-1.5 text-[13.5px] font-semibold text-ink-2">
                                <a href="{{ route('profile.edit') }}" wire:navigate class="flex items-center gap-3 rounded-[9px] px-2.5 py-2 hover:bg-[#F1F5FA] hover:text-ink">
                                    <flux:icon.user-circle variant="outline" class="size-[18px]" /> Profilim
                                </a>
                                <a href="{{ route('security.edit') }}" wire:navigate class="flex items-center gap-3 rounded-[9px] px-2.5 py-2 hover:bg-[#F1F5FA] hover:text-ink">
                                    <flux:icon.shield-check variant="outline" class="size-[18px]" /> Güvenlik
                                </a>
                                <a href="{{ route('notifications.index') }}" wire:navigate class="flex items-center gap-3 rounded-[9px] px-2.5 py-2 hover:bg-[#F1F5FA] hover:text-ink">
                                    <flux:icon.bell variant="outline" class="size-[18px]" /> Bildirimler
                                </a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" data-test="logout-button" class="flex w-full items-center gap-3 rounded-[9px] px-2.5 py-2 text-st-red hover:bg-st-red-bg">
                                        <flux:icon.arrow-right-start-on-rectangle variant="outline" class="size-[18px]" /> Çıkış Yap
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </header>

                <main class="flex-1 px-4 pt-6 pb-24 sm:px-7">
                    <div class="mx-auto max-w-[1360px] oigo-fade-up">
                        @if (Impersonation::active())
                            <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-st-amber-dot px-4 py-2.5 text-[13px] font-semibold text-[#3d2606]" role="alert" data-test="impersonation-banner">
                                <span>
                                    Destek görünümü: <strong>{{ $user->name }}</strong> olarak görüntülüyorsunuz
                                    ({{ Impersonation::impersonator()?->name }}) · {{ Impersonation::endsAt()?->format('H:i') }}'de sona erer.
                                    Yaptığınız işlemler sizin adınızla kaydedilir.
                                </span>
                                <form method="POST" action="{{ route('impersonation.stop') }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-[#3d2606] px-3 py-1.5 text-white hover:bg-black">Destek görünümünü bitir</button>
                                </form>
                            </div>
                        @endif

                        <livewire:announcements mode="banner" />

                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @if ($firm)
            <livewire:setup-assistant />
        @endif

        @fluxScripts
    </body>
</html>
