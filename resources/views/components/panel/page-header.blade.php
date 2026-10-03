@props([
    // [label => url, label => null, ...]; the last item is the current page
    'crumbs' => [],
    'title' => null,
    'subtitle' => null,
    // URL of the round "back" button
    'back' => null,
    // initials for the record avatar on detail / form pages
    'initials' => null,
])

{{-- Page title block (docs/ARAYUZ_KURALLARI.md §3). Slots: badge, meta, actions. --}}
<div {{ $attributes->class(['mb-5']) }}>
    @if ($crumbs)
        <nav class="mb-1.5 flex flex-wrap items-center gap-1.5 text-[12.5px] font-semibold text-muted-2" aria-label="Sayfa yolu">
            @foreach ($crumbs as $label => $url)
                @if (! $loop->first)
                    <span aria-hidden="true">/</span>
                @endif
                @if ($url)
                    <a href="{{ $url }}" wire:navigate class="font-bold text-brand hover:text-mint">{{ $label }}</a>
                @else
                    <span>{{ $label }}</span>
                @endif
            @endforeach
        </nav>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex min-w-0 items-center gap-3.5">
            @if ($back)
                <a href="{{ $back }}" wire:navigate class="flex size-10 shrink-0 items-center justify-center rounded-[10px] border-[1.5px] border-line-2 bg-white text-ink-2 transition hover:border-brand" aria-label="Geri">
                    <flux:icon.chevron-left variant="mini" />
                </a>
            @endif
            @if ($initials)
                <x-panel.avatar :initials="$initials" size="lg" tone="gradient" />
            @endif
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-[23px] leading-tight font-extrabold tracking-[-0.02em] text-ink sm:text-[25px]">{{ $title }}</h1>
                    {{ $badge ?? '' }}
                </div>
                @if ($subtitle)
                    <p class="mt-1 text-sm text-muted">{{ $subtitle }}</p>
                @endif
                {{ $meta ?? '' }}
            </div>
        </div>

        @isset($actions)
            <div class="flex flex-wrap items-center gap-2.5">{{ $actions }}</div>
        @endisset
    </div>
</div>
