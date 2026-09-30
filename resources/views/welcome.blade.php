@php
    $content = \App\Support\LandingContent::get();
    $siteName = \App\Models\Setting::get('site.name', config('app.name'));
    $contact = [
        'phone' => \App\Models\Setting::get('contact.phone'),
        'email' => \App\Models\Setting::get('contact.email'),
        'address' => \App\Models\Setting::get('contact.address'),
    ];
    $companyTitle = \App\Models\Setting::get('company.title', $siteName);
    $loginUrl = \App\Enums\Portal::Panel->url('/login');
    $registerUrl = \App\Enums\Portal::Panel->url('/register');
@endphp
<!DOCTYPE html>
<html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $content['meta_title'] ?: $siteName }}</title>
        @if ($content['meta_description'])
            <meta name="description" content="{{ $content['meta_description'] }}">
        @endif

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased">
        <header class="sticky top-0 z-10 border-b border-zinc-100 bg-white/90 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
                <a href="/" class="text-lg font-semibold tracking-tight">{{ $siteName }}</a>
                <nav class="flex items-center gap-2 text-sm">
                    <a href="#hizmetler" class="hidden px-3 py-2 text-zinc-600 hover:text-zinc-900 sm:inline">Hizmetler</a>
                    <a href="#hakkimizda" class="hidden px-3 py-2 text-zinc-600 hover:text-zinc-900 sm:inline">Hakkımızda</a>
                    @if (array_filter($contact))
                        <a href="#iletisim" class="hidden px-3 py-2 text-zinc-600 hover:text-zinc-900 sm:inline">İletişim</a>
                    @endif
                    <a href="{{ $loginUrl }}" class="rounded-lg border border-zinc-200 px-4 py-2 font-medium hover:border-zinc-400">Giriş Yap</a>
                </nav>
            </div>
        </header>

        <main>
            <section class="bg-gradient-to-b from-violet-50 to-white">
                <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
                    <h1 class="max-w-3xl text-4xl font-semibold tracking-tight sm:text-5xl">{{ $content['hero_title'] }}</h1>
                    @if ($content['hero_subtitle'])
                        <p class="mt-6 max-w-2xl text-lg text-zinc-600">{{ $content['hero_subtitle'] }}</p>
                    @endif
                    <div class="mt-10 flex flex-wrap gap-3">
                        <a href="{{ $registerUrl }}" class="rounded-lg bg-violet-600 px-6 py-3 font-medium text-white hover:bg-violet-700">{{ $content['hero_cta'] }}</a>
                        <a href="{{ $loginUrl }}" class="rounded-lg px-6 py-3 font-medium text-violet-700 hover:bg-violet-100">Müşteri Girişi →</a>
                    </div>
                </div>
            </section>

            @if (count($content['services']))
                <section id="hizmetler" class="mx-auto max-w-6xl scroll-mt-16 px-4 py-20 sm:px-6">
                    <h2 class="text-3xl font-semibold tracking-tight">{{ $content['services_title'] }}</h2>
                    <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($content['services'] as $service)
                            <div class="rounded-xl border border-zinc-200 p-6">
                                <div class="mb-4 size-2 rounded-full bg-violet-600"></div>
                                <h3 class="font-semibold">{{ $service['title'] }}</h3>
                                @if ($service['text'])
                                    <p class="mt-2 text-sm leading-relaxed text-zinc-600">{{ $service['text'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section id="hakkimizda" class="scroll-mt-16 bg-zinc-50">
                <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
                    <h2 class="text-3xl font-semibold tracking-tight">{{ $content['about_title'] }}</h2>
                    @if ($content['about_text'])
                        <p class="mt-6 max-w-3xl whitespace-pre-line leading-relaxed text-zinc-600">{{ $content['about_text'] }}</p>
                    @endif
                </div>
            </section>

            @if (array_filter($contact))
                <section id="iletisim" class="mx-auto max-w-6xl scroll-mt-16 px-4 py-20 sm:px-6">
                    <h2 class="text-3xl font-semibold tracking-tight">İletişim</h2>
                    <dl class="mt-8 grid gap-6 sm:grid-cols-3">
                        @if ($contact['phone'])
                            <div>
                                <dt class="text-sm text-zinc-500">Telefon</dt>
                                <dd class="mt-1 font-medium"><a href="tel:{{ preg_replace('/\s+/', '', $contact['phone']) }}" class="hover:text-violet-700">{{ $contact['phone'] }}</a></dd>
                            </div>
                        @endif
                        @if ($contact['email'])
                            <div>
                                <dt class="text-sm text-zinc-500">E-posta</dt>
                                <dd class="mt-1 font-medium"><a href="mailto:{{ $contact['email'] }}" class="hover:text-violet-700">{{ $contact['email'] }}</a></dd>
                            </div>
                        @endif
                        @if ($contact['address'])
                            <div>
                                <dt class="text-sm text-zinc-500">Adres</dt>
                                <dd class="mt-1 whitespace-pre-line font-medium">{{ $contact['address'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </section>
            @endif
        </main>

        <footer class="border-t border-zinc-100">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-8 text-sm text-zinc-500 sm:px-6">
                <span>© {{ now()->year }} {{ $companyTitle }}</span>
                <div class="flex flex-wrap gap-4">
                    <a href="{{ route('kvkk.document', 'aydinlatma') }}" class="hover:text-zinc-900">KVKK Aydınlatma Metni</a>
                    <a href="{{ $loginUrl }}" class="hover:text-zinc-900">Müşteri Paneli</a>
                </div>
            </div>
        </footer>
    </body>
</html>
