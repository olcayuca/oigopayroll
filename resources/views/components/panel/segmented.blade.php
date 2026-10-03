@props([
    // value => label
    'options' => [],
    // Livewire property the segment writes to
    'model',
    'current' => null,
])

<div {{ $attributes->class(['flex flex-wrap gap-1 rounded-[10px] bg-seg p-[3px]']) }} role="group">
    @foreach ($options as $value => $label)
        @php($active = (string) $value === (string) $current)
        <button type="button" wire:click="$set('{{ $model }}', '{{ $value }}')" aria-pressed="{{ $active ? 'true' : 'false' }}"
            @class([
                'h-8 rounded-lg px-3 text-[12.5px] font-bold whitespace-nowrap transition',
                'bg-white text-ink shadow-[0_1px_2px_rgba(16,40,72,0.08)]' => $active,
                'text-muted hover:text-ink' => ! $active,
            ])>{{ $label }}</button>
    @endforeach
</div>
