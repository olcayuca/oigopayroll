@php($pageName = $paginator->getPageName())

{{-- Livewire pagination for panel list cards (x-panel.table). --}}
<div class="flex flex-wrap items-center justify-between gap-3 border-t border-line-3 px-[18px] py-3 text-[12.5px] font-semibold text-muted-2">
    <span>
        @if ($paginator->total() > $paginator->count())
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} / {{ $paginator->total() }} kayıt
        @else
            {{ $paginator->total() }} kayıt
        @endif
    </span>

    @if ($paginator->hasPages())
        <nav class="flex items-center gap-1.5" aria-label="Sayfalar">
            @php($btn = 'flex h-[30px] min-w-[30px] items-center justify-center rounded-lg border-[1.5px] px-1.5 tabular-nums')
            <button type="button" wire:click="previousPage('{{ $pageName }}')" @disabled($paginator->onFirstPage())
                class="{{ $btn }} border-line text-faint enabled:hover:border-brand enabled:hover:text-brand disabled:opacity-50" aria-label="Önceki">‹</button>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-1">…</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="{{ $btn }} border-brand bg-brand font-bold text-white" aria-current="page">{{ $page }}</span>
                        @else
                            <button type="button" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" wire:key="page-{{ $pageName }}-{{ $page }}"
                                class="{{ $btn }} border-line text-ink-2 hover:border-brand hover:text-brand">{{ $page }}</button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            <button type="button" wire:click="nextPage('{{ $pageName }}')" @disabled(! $paginator->hasMorePages())
                class="{{ $btn }} border-line text-faint enabled:hover:border-brand enabled:hover:text-brand disabled:opacity-50" aria-label="Sonraki">›</button>
        </nav>
    @endif
</div>
