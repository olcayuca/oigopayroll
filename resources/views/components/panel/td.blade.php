@props([
    'align' => 'start',
    // main line + muted second line
    'sub' => null,
    'strong' => false,
])

<td {{ $attributes->class([
    'px-3.5 py-3.5 align-middle first:ps-[18px] last:pe-[18px]',
    'text-start' => $align === 'start',
    'text-end' => $align === 'end',
    'text-center' => $align === 'center',
]) }}>
    <div @class([
        'text-[13px] whitespace-nowrap',
        'font-bold text-ink' => $strong,
        'font-semibold text-ink-2' => ! $strong,
    ])>{{ $slot }}</div>
    @if (filled($sub))
        <div class="text-xs whitespace-nowrap text-muted-2">{{ $sub }}</div>
    @endif
</td>
