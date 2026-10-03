@props([
    // key => label, in order
    'tabs' => [],
    // index of the open tab
    'index' => 0,
    // Livewire property holding the open tab
    'model' => 'tab',
])

@php
    $keys = array_keys($tabs);
    $index = (int) $index;
    $previous = $keys[$index - 1] ?? null;
    $next = $keys[$index + 1] ?? null;
    $navButton = 'h-[38px] rounded-[10px] border-[1.5px] px-3.5 text-[13px] font-bold transition';
@endphp

{{-- Bottom bar of a tabbed form card: previous / next tab. --}}
<div {{ $attributes->class(['flex items-center justify-between gap-3 border-t border-line-3 px-6 py-4']) }}>
    <button type="button" @disabled(! $previous) wire:click="$set('{{ $model }}', '{{ $previous ?? $keys[$index] ?? '' }}')"
        class="{{ $navButton }} border-line-2 bg-white text-ink-3 enabled:hover:border-brand disabled:cursor-default disabled:opacity-40">
        ‹ {{ $previous ? $tabs[$previous] : 'Önceki' }}
    </button>
    <span class="text-[12.5px] font-semibold text-muted-2">Sekme {{ $index + 1 }} / {{ count($keys) }}</span>
    <button type="button" @disabled(! $next) wire:click="$set('{{ $model }}', '{{ $next ?? $keys[$index] ?? '' }}')"
        class="{{ $navButton }} border-line-2 bg-white text-ink-3 enabled:hover:border-brand disabled:cursor-default disabled:opacity-40">
        {{ $next ? $tabs[$next] : 'Sonraki' }} ›
    </button>
</div>
