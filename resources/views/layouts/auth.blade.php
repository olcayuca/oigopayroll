@if (\App\Enums\Portal::fromHost(request()->getHost()) === \App\Enums\Portal::Panel)
    <x-layouts::auth.panel :title="$title ?? null">
        {{ $slot }}
    </x-layouts::auth.panel>
@else
    <x-layouts::auth.simple :title="$title ?? null">
        {{ $slot }}
    </x-layouts::auth.simple>
@endif
