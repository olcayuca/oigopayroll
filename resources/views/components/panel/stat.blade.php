@props([
    'value' => null,
    'label' => null,
    // navy | blue | mint | amber | red | gray
    'color' => 'navy',
    'href' => null,
])

@php
    $bar = [
        'navy' => 'bg-brand',
        'blue' => 'bg-st-blue-dot',
        'mint' => 'bg-mint',
        'amber' => 'bg-st-amber-dot',
        'red' => 'bg-st-red-dot',
        'gray' => 'bg-faint',
    ][$color] ?? 'bg-brand';
@endphp

@if ($href)
    <a href="{{ $href }}" wire:navigate {{ $attributes->class(['flex items-center gap-3.5 rounded-[14px] border border-line bg-white px-[18px] py-4 transition hover:border-line-2 hover:shadow-sm']) }}>
@else
    <div {{ $attributes->class(['flex items-center gap-3.5 rounded-[14px] border border-line bg-white px-[18px] py-4']) }}>
@endif
        <span class="h-[34px] w-1.5 shrink-0 rounded {{ $bar }}"></span>
        <div class="min-w-0">
            <div class="text-[22px] leading-tight font-extrabold tracking-tight text-ink tabular-nums">{{ $value }}</div>
            <div class="truncate text-[12.5px] font-semibold text-muted">{{ $label }}</div>
        </div>
@if ($href)
    </a>
@else
    </div>
@endif
