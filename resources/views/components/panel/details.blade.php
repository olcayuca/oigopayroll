@props([
    // label => value (null / '' shown as "—")
    'items' => [],
    'columns' => 2,
])

<dl {{ $attributes->class([
    'grid gap-x-[22px] gap-y-5',
    'sm:grid-cols-2' => $columns >= 2,
    'xl:grid-cols-3' => $columns >= 3,
]) }}>
    @foreach ($items as $label => $value)
        <div class="min-w-0">
            <dt class="mb-1 text-[12px] font-bold tracking-wide text-muted-2">{{ $label }}</dt>
            <dd @class(['text-[14px] font-semibold break-words', 'text-ink' => filled($value), 'text-faint' => blank($value)])>{{ filled($value) ? $value : '—' }}</dd>
        </div>
    @endforeach
</dl>
