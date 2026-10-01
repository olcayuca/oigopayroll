@props(['import', 'columns', 'onlyErrors' => false])

{{-- Step 3–4 of a bulk import: check results and confirmation. The Livewire page provides
     startOver(), confirm() and $onlyErrors. Rows carry the planned action (create / update / unchanged). --}}
@php
    $counts = $import->actionCounts();
    $creates = $counts[\App\Models\DataImportRow::CREATE] ?? 0;
    $updates = $counts[\App\Models\DataImportRow::UPDATE] ?? 0;
    $unchanged = $counts[\App\Models\DataImportRow::UNCHANGED] ?? 0;
    $actionLabels = [
        \App\Models\DataImportRow::CREATE => ['Yeni kayıt', 'sky'],
        \App\Models\DataImportRow::UPDATE => ['Güncellenecek', 'amber'],
        \App\Models\DataImportRow::UNCHANGED => ['Değişiklik yok', 'zinc'],
    ];
    $summary = collect([
        $creates ? "{$creates} yeni kayıt oluşturulacak" : null,
        $updates ? "{$updates} kayıt güncellenecek" : null,
    ])->filter()->implode(', ');
@endphp
<div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
    <div class="flex flex-wrap gap-8">
        <div><flux:text size="sm">Dosya</flux:text><div class="font-medium">{{ $import->original_filename }}</div></div>
        <div><flux:text size="sm">Satır</flux:text><div class="text-2xl font-semibold">{{ $import->total_rows }}</div></div>
        @if ($creates || $updates || $unchanged)
            <div><flux:text size="sm">Yeni</flux:text><div class="text-2xl font-semibold text-sky-600">{{ $creates }}</div></div>
            <div><flux:text size="sm">Güncellenecek</flux:text><div class="text-2xl font-semibold text-amber-600">{{ $updates }}</div></div>
            @if ($unchanged)
                <div><flux:text size="sm">Değişiklik yok</flux:text><div class="text-2xl font-semibold text-zinc-500">{{ $unchanged }}</div></div>
            @endif
        @else
            <div><flux:text size="sm">Hatasız</flux:text><div class="text-2xl font-semibold text-green-600">{{ $import->total_rows - $import->error_rows }}</div></div>
        @endif
        <div><flux:text size="sm">Hatalı</flux:text><div class="text-2xl font-semibold {{ $import->error_rows ? 'text-red-500' : '' }}">{{ $import->error_rows }}</div></div>
    </div>
    <div class="flex gap-2">
        <flux:button icon="arrow-path" wire:click="startOver">Yeni Dosya Yükle</flux:button>
        @if ($import->canBeConfirmed() && ($creates || $updates || ! ($unchanged)))
            <flux:button variant="primary" icon="check" wire:click="confirm"
                wire:confirm="{{ $summary !== '' ? $summary : $import->total_rows.' kayıt oluşturulacak' }}. Onaylıyor musunuz?">Onayla ve Uygula</flux:button>
        @endif
    </div>
</div>

@if ($import->file_errors)
    <flux:callout icon="x-circle" color="red" heading="Dosya okunamadı">
        <flux:callout.text>
            <ul class="list-disc ps-5">
                @foreach ($import->file_errors as $error) <li>{{ $error }}</li> @endforeach
            </ul>
        </flux:callout.text>
    </flux:callout>
@elseif ($import->error_rows > 0)
    <flux:callout icon="exclamation-triangle" color="amber" heading="{{ $import->error_rows }} satırda hata var"
        text="Hataları Excel dosyanızda düzeltip dosyayı yeniden yükleyin. Hatalar giderilmeden kayıt oluşturulmaz." />
@else
    @if ($unchanged === $import->total_rows)
        <flux:callout icon="check-circle" heading="Değişiklik yok"
            text="Dosyadaki tüm kayıtlar sistemdekiyle aynı; uygulanacak bir değişiklik bulunmuyor." />
    @else
        <flux:callout icon="check-circle" color="green" heading="Tüm satırlar geçerli"
            text="{{ $summary !== '' ? $summary.'.' : 'Kontrol edip onayladığınızda kayıtlar oluşturulacak.' }} Güncellemelerde boş bırakılan şifre alanları korunur." />
    @endif
@endif

@if ($import->total_rows > 0)
    <flux:checkbox wire:model.live="onlyErrors" label="Yalnızca hatalı satırları göster" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Satır</flux:table.column>
            @foreach ($columns as $header)
                <flux:table.column>{{ $header }}</flux:table.column>
            @endforeach
            <flux:table.column>Durum</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($import->rows as $row)
                @continue($onlyErrors && ! $row->hasErrors())
                <flux:table.row :key="$row->id">
                    <flux:table.cell>{{ $row->row_number }}</flux:table.cell>
                    @foreach (array_keys($columns) as $key)
                        <flux:table.cell>{{ $row->data[$key] ?? '' }}</flux:table.cell>
                    @endforeach
                    <flux:table.cell class="whitespace-normal">
                        @if ($row->hasErrors())
                            <ul class="space-y-0.5 text-sm text-red-500">
                                @foreach (collect($row->errors)->flatten() as $message) <li>{{ $message }}</li> @endforeach
                            </ul>
                        @elseif (isset($actionLabels[$row->action]))
                            <flux:badge size="sm" :color="$actionLabels[$row->action][1]" inset="top bottom">{{ $actionLabels[$row->action][0] }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="green" inset="top bottom">Geçerli</flux:badge>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
@endif
