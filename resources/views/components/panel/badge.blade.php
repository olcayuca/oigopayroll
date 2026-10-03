@props([
    // green | amber | red | gray | navy | blue
    'color' => 'gray',
    'dot' => true,
])

@php
    // Accept the Flux color names the enums already return.
    $color = ['zinc' => 'gray', 'sky' => 'blue', 'emerald' => 'green', 'lime' => 'green', 'orange' => 'amber', 'yellow' => 'amber', 'rose' => 'red', 'indigo' => 'navy'][$color] ?? $color;

    $classes = [
        'green' => 'bg-st-green-bg text-st-green',
        'amber' => 'bg-st-amber-bg text-st-amber',
        'red' => 'bg-st-red-bg text-st-red',
        'gray' => 'bg-st-gray-bg text-st-gray',
        'navy' => 'bg-st-navy-bg text-st-navy',
        'blue' => 'bg-st-blue-bg text-st-blue',
    ][$color] ?? 'bg-st-gray-bg text-st-gray';

    $dotClass = [
        'green' => 'bg-st-green-dot',
        'amber' => 'bg-st-amber-dot',
        'red' => 'bg-st-red-dot',
        'gray' => 'bg-st-gray-dot',
        'navy' => 'bg-st-navy-dot',
        'blue' => 'bg-st-blue-dot',
    ][$color] ?? 'bg-st-gray-dot';
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 whitespace-nowrap rounded-[7px] px-2.5 py-[3px] text-[11.5px] font-bold', $classes]) }}>
    @if ($dot)
        <span class="size-1.5 shrink-0 rounded-full {{ $dotClass }}"></span>
    @endif
    {{ $slot }}
</span>
