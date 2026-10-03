@props([
    'label' => null,
    'value' => null,
    'hint' => null,
    // navy | blue | mint | amber | red
    'color' => 'navy',
])

@php
    $dot = [
        'navy' => 'bg-brand',
        'blue' => 'bg-st-blue-dot',
        'mint' => 'bg-mint',
        'amber' => 'bg-st-amber-dot',
        'red' => 'bg-st-red-dot',
    ][$color] ?? 'bg-brand';
@endphp

<div {{ $attributes->class(['rounded-[14px] border border-line bg-white px-[18px] py-4']) }}>
    <div class="flex items-center justify-between gap-2">
        <span class="text-[12.5px] font-bold text-ink-2">{{ $label }}</span>
        <span class="size-2 rounded-sm {{ $dot }}"></span>
    </div>
    <div class="mt-3 truncate text-[26px] leading-tight font-extrabold tracking-tight text-ink tabular-nums">{{ $value }}</div>
    @if ($hint)
        <div class="mt-1 truncate text-xs font-medium text-muted-2">{{ $hint }}</div>
    @endif
</div>
