@props([
    // Eloquent model with timestamps (and optionally a creator relation)
    'record',
])

<div {{ $attributes->class(['rounded-2xl border border-line bg-white px-[18px] py-4']) }}>
    <div class="mb-2.5 text-[12px] font-bold tracking-[0.04em] text-muted">KAYIT BİLGİSİ</div>
    <div class="flex flex-col gap-[9px] text-[12.5px]">
        <div class="flex justify-between gap-3"><span class="text-muted-2">Oluşturan</span><span class="truncate font-bold text-ink">{{ $record->creator?->name ?? '—' }}</span></div>
        <div class="flex justify-between gap-3"><span class="text-muted-2">Oluşturma</span><span class="font-bold text-ink">{{ $record->created_at?->format('d.m.Y H:i') ?? '—' }}</span></div>
        <div class="flex justify-between gap-3"><span class="text-muted-2">Son güncelleme</span><span class="font-bold text-ink">{{ $record->updated_at?->format('d.m.Y H:i') ?? '—' }}</span></div>
        {{ $slot }}
    </div>
</div>
