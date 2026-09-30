@props(['templateUrl'])

{{-- Step 1–2 of a bulk import. The Livewire page provides $file and upload(). --}}
<div class="grid gap-4 lg:grid-cols-2">
    <div class="rounded-xl border border-dashed border-zinc-300 p-6 dark:border-zinc-600">
        <flux:heading>1. Şablonu indirin</flux:heading>
        <flux:text class="mt-1">Zorunlu sütunlar sarı işaretlidir; açılır listeler ve "Açıklamalar" sayfası şablonun içindedir.</flux:text>
        <flux:button class="mt-4" icon="arrow-down-tray" :href="$templateUrl">Excel Şablonunu İndir</flux:button>
    </div>

    <form wire:submit="upload" class="rounded-xl border border-dashed border-zinc-300 p-6 dark:border-zinc-600">
        <flux:heading>2. Doldurduğunuz dosyayı yükleyin</flux:heading>
        <flux:text class="mt-1">.xlsx, .xls veya .csv — en fazla 10 MB.</flux:text>
        <div class="mt-4 space-y-3">
            <input type="file" wire:model="file" accept=".xlsx,.xls,.csv"
                class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-zinc-800 file:px-3 file:py-2 file:text-white dark:file:bg-zinc-200 dark:file:text-zinc-900" />
            <flux:error name="file" />
            <div wire:loading wire:target="file" class="text-sm text-zinc-500">Dosya yükleniyor...</div>
            <flux:button type="submit" variant="primary" icon="magnifying-glass" wire:loading.attr="disabled" wire:target="file,upload">
                Yükle ve Kontrol Et
            </flux:button>
        </div>
    </form>
</div>
