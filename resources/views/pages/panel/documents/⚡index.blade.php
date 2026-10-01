<?php

use App\Livewire\PanelComponent;
use Livewire\Attributes\Title;

new #[Title('Belgeler')] class extends PanelComponent {
    //
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">Belgeler</flux:heading>
        <flux:text class="mt-1">
            {{ $this->firm->name }} firmasının vergi levhası, imza sirküleri, sözleşme gibi belgeleri.
            Geçerlilik tarihi girilen belgeler için süresi dolmadan 30 gün önce uyarı gösterilir.
        </flux:text>
    </div>

    @can('viewDocuments', $this->firm)
        <livewire:firm-documents :firm="$this->firm" :key="'documents-'.$this->firm->id" />
    @else
        <flux:callout icon="lock-closed" heading="Belgeleri görme yetkiniz yok"
            text="Firma belgeleri, firma düzeyinde görüntüleme yetkisi olan kullanıcılara açıktır." />
    @endcan
</div>
