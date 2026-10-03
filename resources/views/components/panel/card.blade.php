@props([
    'title' => null,
    'description' => null,
    'padding' => true,
])

<div {{ $attributes->class(['rounded-2xl border border-line bg-white shadow-[0_1px_3px_rgba(16,40,72,0.04)]']) }}>
    @if ($title || isset($actions))
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-line-3 px-5 py-4">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-[15px] font-extrabold tracking-tight text-ink">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-[12.5px] text-muted">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div @class(['p-5' => $padding])>
        {{ $slot }}
    </div>
</div>
