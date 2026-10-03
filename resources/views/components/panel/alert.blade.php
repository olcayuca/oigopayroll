@props([
    // info | warning | danger | success
    'variant' => 'info',
    'title' => null,
    'icon' => null,
])

@php
    $box = [
        'info' => 'border-[#D4E4F8] bg-st-blue-bg text-[#2B4F87]',
        'warning' => 'border-[#F2E2C4] bg-st-amber-bg text-[#8A5A14]',
        'danger' => 'border-[#F3D3D1] bg-st-red-bg text-st-red',
        'success' => 'border-[#CFE9E3] bg-st-green-bg text-st-green',
    ][$variant] ?? '';

    $icon ??= [
        'info' => 'information-circle',
        'warning' => 'exclamation-triangle',
        'danger' => 'exclamation-circle',
        'success' => 'check-circle',
    ][$variant] ?? 'information-circle';
@endphp

<div {{ $attributes->class(['flex flex-wrap items-start gap-3 rounded-xl border px-4 py-3 sm:flex-nowrap', $box]) }} role="status">
    <flux:icon :name="$icon" variant="mini" class="mt-px size-[18px] shrink-0" />
    <div class="min-w-0 flex-1 text-[13px] leading-relaxed">
        @if ($title)
            <div class="font-bold">{{ $title }}</div>
        @endif
        @if ($slot->isNotEmpty())
            <div @class(['opacity-90' => $title])>{{ $slot }}</div>
        @endif
    </div>
    @isset($actions)
        <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
