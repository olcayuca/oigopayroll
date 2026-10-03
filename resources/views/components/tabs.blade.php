@props([
    // key => label
    'tabs' => [],
    // currently open tab key
    'active' => null,
    // Livewire property holding the open tab
    'model' => 'tab',
    // key => number shown next to the label (lists)
    'counts' => [],
    // keys of tabs that contain validation errors
    'invalid' => [],
])

@php($panel = \App\Enums\Portal::fromHost(request()->getHost()) === \App\Enums\Portal::Panel)

{{-- Page-level tabs (see docs/ARAYUZ_KURALLARI.md). Panels are rendered by the page with @if ($tab === '...'). --}}
@if ($panel)
    <nav {{ $attributes->class('flex gap-6 overflow-x-auto border-b border-line-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden') }} role="tablist">
        @foreach ($tabs as $key => $label)
            @php($isActive = $key === $active)
            <button
                type="button"
                role="tab"
                aria-selected="{{ $isActive ? 'true' : 'false' }}"
                wire:click="$set('{{ $model }}', '{{ $key }}')"
                data-test="tab-{{ $key }}"
                @class([
                    'flex shrink-0 items-center gap-2 px-0.5 pt-[15px] pb-3.5 text-[13.5px] font-bold whitespace-nowrap transition',
                    'text-ink shadow-[inset_0_-2.5px_0_var(--color-mint)]' => $isActive,
                    'text-muted hover:text-ink' => ! $isActive,
                ])
            >
                {{ $label }}
                @if (array_key_exists($key, $counts))
                    <span class="rounded-md bg-seg px-[7px] py-0.5 text-[10.5px] font-extrabold text-muted">{{ $counts[$key] }}</span>
                @endif
                @if (in_array($key, $invalid, true))
                    <span class="size-2 rounded-full bg-st-red-dot" title="Bu sekmede hata var"></span>
                @endif
            </button>
        @endforeach
    </nav>
@else
    <nav {{ $attributes->class('-mb-px flex gap-1 overflow-x-auto border-b border-zinc-200 dark:border-zinc-700') }} role="tablist">
        @foreach ($tabs as $key => $label)
            @php($isActive = $key === $active)
            <button
                type="button"
                role="tab"
                aria-selected="{{ $isActive ? 'true' : 'false' }}"
                wire:click="$set('{{ $model }}', '{{ $key }}')"
                data-test="tab-{{ $key }}"
                @class([
                    'relative flex shrink-0 items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium whitespace-nowrap transition',
                    'border-zinc-800 text-zinc-900 dark:border-white dark:text-white' => $isActive,
                    'border-transparent text-zinc-500 hover:border-zinc-300 hover:text-zinc-800 dark:text-zinc-400 dark:hover:border-zinc-600 dark:hover:text-white' => ! $isActive,
                ])
            >
                {{ $label }}
                @if (array_key_exists($key, $counts))
                    <span class="rounded-full bg-zinc-100 px-1.5 py-0.5 text-xs text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">{{ $counts[$key] }}</span>
                @endif
                @if (in_array($key, $invalid, true))
                    <span class="size-2 rounded-full bg-red-500" title="Bu sekmede hata var"></span>
                @endif
            </button>
        @endforeach
    </nav>
@endif
