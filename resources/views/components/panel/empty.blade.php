@props([
    'icon' => 'magnifying-glass',
    'title' => null,
])

<div {{ $attributes->class(['px-5 py-14 text-center']) }}>
    <div class="mx-auto mb-3.5 flex size-14 items-center justify-center rounded-2xl bg-seg text-muted">
        <flux:icon :name="$icon" class="size-6" />
    </div>
    @if ($title)
        <div class="mb-1 text-[15px] font-extrabold text-ink">{{ $title }}</div>
    @endif
    <div class="mx-auto max-w-md text-[13px] text-muted">{{ $slot }}</div>
    @isset($actions)
        <div class="mt-4 flex flex-wrap justify-center gap-2">{{ $actions }}</div>
    @endisset
</div>
