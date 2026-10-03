@props([
    // query of the İşlem Geçmişi page: ['sirket' => id] / ['sube' => id] / ['kayit' => 'personel:id'] / ['kullanici' => id]
    'filter' => [],
])

@php($firm = auth()->user()?->activeFirm())

{{-- "İşlem geçmişi" button for record pages; shown only to users who may read the history. --}}
@if ($firm && auth()->user()->can('viewAudit', $firm))
    <flux:button icon="clock" :href="route('audit.index', $filter)" wire:navigate {{ $attributes }}>İşlem geçmişi</flux:button>
@endif
