<?php

use App\Kvkk\Policies;
use App\Models\PolicyDocument;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('KVKK Onayı')] class extends Component {
    public string $tab = '';

    public function mount(): mixed
    {
        if ($this->pending->isEmpty()) {
            return $this->redirectIntended('/', navigate: false);
        }

        $this->tab = (string) $this->pending->keys()->first();

        return null;
    }

    /**
     * @return Collection<string, PolicyDocument>
     */
    #[Computed]
    public function pending(): Collection
    {
        return app(Policies::class)->pendingFor(auth()->user());
    }

    public function decide(int $documentId, bool $accepted): mixed
    {
        $document = $this->pending->firstWhere('id', $documentId);
        abort_if($document === null, 404);

        app(Policies::class)->record(auth()->user(), $document, $accepted);

        unset($this->pending);

        if ($this->pending->isEmpty()) {
            return $this->redirectIntended('/', navigate: false);
        }

        $this->tab = (string) $this->pending->keys()->first();

        return null;
    }
}; ?>

<div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">Kişisel Verilerin Korunması</flux:heading>
        <flux:text class="mt-1">
            Sistemi kullanmaya devam etmeden önce aşağıdaki {{ $this->pending->count() > 1 ? 'metinleri' : 'metni' }} okuyup kararınızı belirtin.
            Kararlarınızı daha sonra <strong>Ayarlar → KVKK</strong> bölümünden görebilir, açık rızanızı geri alabilirsiniz.
        </flux:text>
    </div>

    <x-tabs :active="$tab" :tabs="$this->pending->mapWithKeys(fn ($document, $key) => [$key => $document->type->label()])->all()" />

    @foreach ($this->pending as $key => $document)
        @if ($tab === $key)
            <flux:card class="space-y-4" wire:key="document-{{ $document->id }}">
                <div>
                    <flux:heading size="lg">{{ $document->title }}</flux:heading>
                    <flux:text size="sm">Sürüm {{ $document->version }} · {{ $document->published_at?->format('d.m.Y') }}</flux:text>
                </div>

                <x-policy-body :document="$document" class="max-h-[50vh] overflow-y-auto rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" />

                <div class="flex flex-wrap items-center justify-end gap-2">
                    @if ($document->type->isMandatory())
                        <flux:button variant="primary" wire:click="decide({{ $document->id }}, true)">Okudum, anladım</flux:button>
                    @else
                        <flux:text size="sm" class="me-auto">Açık rıza isteğe bağlıdır; vermemeniz sistemi kullanmanızı engellemez.</flux:text>
                        <flux:button wire:click="decide({{ $document->id }}, false)">Onaylamıyorum</flux:button>
                        <flux:button variant="primary" wire:click="decide({{ $document->id }}, true)">Açık rıza veriyorum</flux:button>
                    @endif
                </div>
            </flux:card>
        @endif
    @endforeach
</div>
