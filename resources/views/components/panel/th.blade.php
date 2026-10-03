@props([
    'align' => 'start',
])

<th {{ $attributes->class([
    'px-3.5 py-[11px] text-[11px] font-bold tracking-[0.06em] whitespace-nowrap text-muted-2 uppercase first:ps-[18px] last:pe-[18px]',
    'text-start' => $align === 'start',
    'text-end' => $align === 'end',
    'text-center' => $align === 'center',
]) }}>{{ $slot }}</th>
