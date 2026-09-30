@props(['document'])

{{-- Rendered markdown of a KVKK text (raw HTML in the source is stripped). --}}
<div {{ $attributes->class('text-sm leading-relaxed text-zinc-700 dark:text-zinc-300 [&_a]:underline [&_h1]:mb-3 [&_h1]:text-lg [&_h1]:font-semibold [&_h2]:mt-5 [&_h2]:mb-2 [&_h2]:font-semibold [&_h2]:text-zinc-900 dark:[&_h2]:text-white [&_h3]:mt-4 [&_h3]:font-medium [&_li]:mt-1 [&_ol]:list-decimal [&_ol]:ps-5 [&_p]:mt-2 [&_strong]:font-semibold [&_ul]:list-disc [&_ul]:ps-5') }}>
    {{ $document->html() }}
</div>
