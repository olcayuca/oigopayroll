@props([
    'initials' => '',
    // sm (30px) | md (34px) | lg (48px)
    'size' => 'md',
    // navy | soft | mint | amber | gradient
    'tone' => 'soft',
])

@php
    $sizeClass = [
        'sm' => 'size-[30px] rounded-lg text-[11px]',
        'md' => 'size-[34px] rounded-[9px] text-xs',
        'lg' => 'size-12 rounded-[13px] text-base',
    ][$size] ?? 'size-[34px] rounded-[9px] text-xs';

    $toneClass = [
        'navy' => 'bg-brand text-white',
        'soft' => 'bg-brand-soft text-brand',
        'mint' => 'bg-mint text-white',
        'mint-soft' => 'bg-mint-soft text-mint-strong',
        'amber' => 'bg-st-amber-bg text-st-amber',
        'gradient' => 'bg-linear-140 from-brand to-[#1f5a8f] text-white',
    ][$tone] ?? 'bg-brand-soft text-brand';
@endphp

<span {{ $attributes->class(['flex shrink-0 items-center justify-center font-extrabold', $sizeClass, $toneClass]) }}>{{ $initials }}</span>
