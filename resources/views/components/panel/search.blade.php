@props([
    'placeholder' => 'Ara...',
])

{{-- Toolbar search box. Pass wire:model.live.debounce... as an attribute. --}}
<div class="relative w-full min-w-[220px] flex-1 sm:max-w-[340px]">
    <flux:icon.magnifying-glass variant="micro" class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-faint" />
    <input type="search" placeholder="{{ $placeholder }}"
        {{ $attributes->class(['h-[38px] w-full rounded-[9px] border-[1.5px] border-[#E8EDF3] bg-field ps-9 pe-3 text-[13.5px] text-ink outline-none placeholder:text-faint focus:border-brand focus:bg-white']) }} />
</div>
