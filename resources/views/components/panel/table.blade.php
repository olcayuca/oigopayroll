@props([
    // LengthAwarePaginator|Collection — shows the "x / y kayıt" footer and the page links
    'paginate' => null,
    'minWidth' => '860px',
])

{{-- List card: toolbar slot, head slot (x-panel.th), rows in the default slot (x-panel.tr / x-panel.td), optional empty slot. --}}
<div {{ $attributes->class(['overflow-hidden rounded-2xl border border-line bg-white shadow-[0_1px_3px_rgba(16,40,72,0.04)]']) }}>
    @isset($toolbar)
        <div class="flex flex-wrap items-center gap-3 border-b border-line-3 px-[18px] py-3.5">
            {{ $toolbar }}
        </div>
    @endisset

    <div class="overflow-x-auto">
        <table class="w-full border-collapse" style="min-width: {{ $minWidth }}">
            @isset($head)
                <thead>
                    <tr class="bg-head">{{ $head }}</tr>
                </thead>
            @endisset
            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    </div>

    {{ $empty ?? '' }}

    @if ($paginate instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
        @if ($paginate->total() > 0)
            {{ $paginate->links('partials.panel-pagination') }}
        @endif
    @elseif ($paginate instanceof \Illuminate\Support\Collection && $paginate->isNotEmpty())
        <div class="border-t border-line-3 px-[18px] py-3 text-[12.5px] font-semibold text-muted-2">{{ $paginate->count() }} kayıt</div>
    @endif

    {{ $footer ?? '' }}
</div>
