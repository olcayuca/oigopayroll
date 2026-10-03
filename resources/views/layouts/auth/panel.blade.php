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
    <body class="min-h-screen bg-white">
        <div class="grid min-h-svh lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
            {{-- Brand panel --}}
            <aside class="relative hidden flex-col justify-between overflow-hidden bg-linear-160 from-side via-[#122C4D] to-[#0F3A52] p-10 text-white lg:flex xl:p-12">
                <div class="pointer-events-none absolute -end-40 -top-40 size-[520px] rounded-full bg-mint/15 blur-3xl"></div>

                <a href="{{ route('home') }}" class="relative flex items-center gap-[11px]">
                    <span class="flex size-[38px] items-center justify-center rounded-[10px] bg-mint text-lg font-extrabold">O</span>
                    <span class="flex flex-col leading-[1.1]">
                        <span class="text-base font-extrabold">{{ config('app.name', 'Laravel') }}</span>
                        <span class="text-[9px] font-bold tracking-[0.17em] text-mint-light">BORDRO PLATFORMU</span>
                    </span>
                </a>

                <div class="relative max-w-lg">
                    <h1 class="text-[34px] leading-[1.15] font-extrabold tracking-[-0.02em] xl:text-[40px]">
                        Bordroyu güvenle, <span class="text-mint-light">şeffaf</span> bir şekilde yönetin.
                    </h1>
                    <p class="mt-4 text-[15px] leading-relaxed text-side-text">
                        Şirket, işyeri ve bordro süreçlerinizi tek panelden; kurumsal güven ve operasyonel verimlilikle yönetin.
                    </p>
                    <ul class="mt-8 space-y-3.5 text-[14px] font-semibold">
                        @foreach (['Şirket ve işyeri tanımları, Excel ile toplu aktarım', 'İşyeri şifreleri şifreli saklanır, her görüntüleme kayıt altında', 'Çok şirketli, rol bazlı ve iki aşamalı doğrulamalı güvenli erişim'] as $feature)
                            <li class="flex items-center gap-3">
                                <span class="flex size-[22px] shrink-0 items-center justify-center rounded-md bg-mint/20 text-mint-light">
                                    <flux:icon.check variant="micro" class="size-3.5" />
                                </span>
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="relative flex flex-wrap gap-x-5 gap-y-1 text-xs text-side-heading">
                    <span>© {{ now()->year }} {{ config('app.name', 'Laravel') }}</span>
                    @if (\App\Models\PolicyDocument::where('type', \App\Enums\PolicyType::Disclosure)->whereNotNull('published_at')->exists())
                        <a href="{{ route('kvkk.document', 'aydinlatma') }}" target="_blank" class="hover:text-white">KVKK Aydınlatma Metni</a>
                    @endif
                </div>
            </aside>

            {{-- Form --}}
            <main class="flex flex-col items-center justify-center px-6 py-10 sm:px-10">
                <div class="w-full max-w-[400px]">
                    <a href="{{ route('home') }}" class="mb-8 flex items-center gap-[11px] lg:hidden">
                        <span class="flex size-[38px] items-center justify-center rounded-[10px] bg-mint text-lg font-extrabold text-white">O</span>
                        <span class="text-base font-extrabold text-ink">{{ config('app.name', 'Laravel') }}</span>
                    </a>

                    <span class="mb-4 inline-flex items-center gap-1.5 rounded-full bg-mint-soft px-2.5 py-1 text-[11.5px] font-bold text-mint-strong">
                        <span class="size-1.5 rounded-full bg-mint"></span> Güvenli giriş
                    </span>

                    <div class="oigo-auth flex flex-col gap-6">
                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
