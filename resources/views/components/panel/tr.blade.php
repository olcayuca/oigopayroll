@props([
    // whole row opens this URL (links and buttons inside the row keep working)
    'href' => null,
])

<tr
    x-data="{ href: @js($href) }"
    x-on:click="if (href && ! $event.target.closest('a, button, input, label, select')) Livewire.navigate(href)"
    {{ $attributes->class(['border-t border-line-4 transition-colors', 'cursor-pointer hover:bg-row-hover' => $href]) }}
>{{ $slot }}</tr>
