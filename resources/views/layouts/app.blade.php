@if (\App\Enums\Portal::fromHost(request()->getHost()) === \App\Enums\Portal::Panel)
    {{-- panel.siteadi.com has its own shell (docs/ARAYUZ_KURALLARI.md §3) --}}
    <x-layouts::panel :title="$title ?? null">
        {{ $slot }}
    </x-layouts::panel>
@else
    <x-layouts::app.sidebar :title="$title ?? null">
        <flux:main>
            @if (\App\Support\Impersonation::active())
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-amber-400 px-4 py-2 text-sm font-medium text-amber-950" role="alert" data-test="impersonation-banner">
                    <span>
                        Destek görünümü: <strong>{{ auth()->user()->name }}</strong> olarak görüntülüyorsunuz
                        ({{ \App\Support\Impersonation::impersonator()?->name }}) · {{ \App\Support\Impersonation::endsAt()?->format('H:i') }}'de sona erer.
                        Yaptığınız işlemler sizin adınızla kaydedilir.
                    </span>
                    <form method="POST" action="{{ route('impersonation.stop') }}">
                        @csrf
                        <button type="submit" class="rounded-md bg-amber-950 px-3 py-1 text-white hover:bg-amber-900">Destek görünümünü bitir</button>
                    </form>
                </div>
            @endif

            {{ $slot }}
        </flux:main>
    </x-layouts::app.sidebar>
@endif
