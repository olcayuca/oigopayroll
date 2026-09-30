<?php

use App\Enums\KvkkRequestType;
use App\Kvkk\DataRequests;
use App\Kvkk\Policies;
use App\Models\Consent;
use App\Models\KvkkRequest;
use App\Models\PolicyDocument;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('KVKK')] class extends Component {
    #[Url(as: 'sekme')]
    public string $tab = 'onaylar';

    public string $type = '';

    public string $message = '';

    /**
     * @return Collection<string, PolicyDocument>
     */
    #[Computed]
    public function documents(): Collection
    {
        return app(Policies::class)->currentDocuments();
    }

    /**
     * The user's decisions on the current documents, keyed by document id.
     *
     * @return Collection<int, Consent>
     */
    #[Computed]
    public function decisions(): Collection
    {
        return Consent::query()->where('user_id', auth()->id())
            ->whereIn('policy_document_id', $this->documents->pluck('id'))
            ->get()->keyBy('policy_document_id');
    }

    /**
     * @return EloquentCollection<int, KvkkRequest>
     */
    #[Computed]
    public function requests(): EloquentCollection
    {
        return KvkkRequest::query()->where('user_id', auth()->id())->latest('id')->get();
    }

    public function setConsent(int $documentId, bool $accepted, Policies $policies): void
    {
        $document = $this->documents->firstWhere('id', $documentId);
        abort_if($document === null || $document->type->isMandatory(), 404);

        $accepted ? $policies->record(auth()->user(), $document, true) : $policies->revoke(auth()->user(), $document);

        unset($this->decisions);
        Flux::toast(variant: 'success', text: $accepted ? 'Açık rızanız kaydedildi.' : 'Açık rızanız geri alındı.');
    }

    public function submit(DataRequests $requests): void
    {
        $this->resetValidation();
        $request = $requests->submit(auth()->user(), ['type' => $this->type, 'message' => $this->message]);

        $this->reset('type', 'message');
        unset($this->requests);
        Flux::modal('kvkk-request')->close();
        Flux::toast(variant: 'success', text: "Başvurunuz alındı (#{$request->id}). En geç {$request->due_at->format('d.m.Y')} tarihine kadar yanıtlanacaktır.");
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout heading="KVKK" subheading="Kişisel verilerinizle ilgili onaylarınız ve başvurularınız">
        <div class="space-y-6">
            <x-tabs :active="$tab" :tabs="['onaylar' => 'Onaylarım', 'basvurular' => 'Başvurularım']" :counts="['basvurular' => $this->requests->count()]" />

            @if ($tab === 'onaylar')
                <div class="space-y-4">
                    @forelse ($this->documents as $document)
                        @php($decision = $this->decisions->get($document->id))
                        <flux:card class="space-y-3" wire:key="doc-{{ $document->id }}">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <flux:heading>{{ $document->type->label() }}</flux:heading>
                                    <flux:text size="sm">Sürüm {{ $document->version }} · {{ $document->published_at?->format('d.m.Y') }}</flux:text>
                                </div>
                                @if ($decision?->isGiven())
                                    <flux:badge color="green" size="sm">{{ $document->type->isMandatory() ? 'Okundu' : 'Rıza verildi' }}</flux:badge>
                                @elseif ($decision)
                                    <flux:badge color="zinc" size="sm">{{ $decision->revoked_at ? 'Geri alındı' : 'Rıza verilmedi' }}</flux:badge>
                                @else
                                    <flux:badge color="amber" size="sm">Karar bekliyor</flux:badge>
                                @endif
                            </div>
                            @if ($decision)
                                <flux:text size="sm">
                                    Karar: {{ $decision->decided_at->format('d.m.Y H:i') }}
                                    @if ($decision->revoked_at) · Geri alma: {{ $decision->revoked_at->format('d.m.Y H:i') }} @endif
                                </flux:text>
                            @endif
                            <div class="flex flex-wrap gap-2">
                                <flux:button size="sm" variant="ghost" icon="document-text" :href="route('kvkk.document', $document->type->value)" target="_blank">Metni oku</flux:button>
                                @unless ($document->type->isMandatory())
                                    @if ($decision?->isGiven())
                                        <flux:button size="sm" wire:click="setConsent({{ $document->id }}, false)" wire:confirm="Açık rızanızı geri almak istediğinize emin misiniz?">Rızamı geri al</flux:button>
                                    @else
                                        <flux:button size="sm" variant="primary" wire:click="setConsent({{ $document->id }}, true)">Açık rıza ver</flux:button>
                                    @endif
                                @endunless
                            </div>
                        </flux:card>
                    @empty
                        <flux:text>Yayımlanmış KVKK metni yok.</flux:text>
                    @endforelse
                </div>
            @endif

            @if ($tab === 'basvurular')
                <div class="space-y-4">
                    <flux:text>
                        6698 sayılı Kanun'un 11. maddesindeki haklarınız için başvurabilirsiniz. Başvurular en geç 30 gün içinde yanıtlanır;
                        yanıtı bu sayfada görürsünüz.
                    </flux:text>
                    <flux:modal.trigger name="kvkk-request">
                        <flux:button variant="primary" icon="plus">Yeni Başvuru</flux:button>
                    </flux:modal.trigger>

                    @forelse ($this->requests as $request)
                        <flux:card class="space-y-2" wire:key="req-{{ $request->id }}">
                            <div class="flex items-start justify-between gap-3">
                                <flux:heading>#{{ $request->id }} · {{ $request->type->shortLabel() }}</flux:heading>
                                <flux:badge size="sm" :color="$request->status->color()">{{ $request->status->label() }}</flux:badge>
                            </div>
                            <flux:text size="sm">{{ $request->created_at?->format('d.m.Y H:i') }} · Son yanıt tarihi {{ $request->due_at->format('d.m.Y') }}</flux:text>
                            <flux:text class="whitespace-pre-line">{{ $request->message }}</flux:text>
                            @if ($request->response)
                                <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                                    <flux:text size="sm" class="font-medium">Yanıt ({{ $request->resolved_at?->format('d.m.Y') ?? $request->updated_at?->format('d.m.Y') }})</flux:text>
                                    <flux:text class="mt-1 whitespace-pre-line">{{ $request->response }}</flux:text>
                                </div>
                            @endif
                        </flux:card>
                    @empty
                        <flux:text class="text-zinc-500">Henüz başvurunuz yok.</flux:text>
                    @endforelse
                </div>
            @endif
        </div>

        <flux:modal name="kvkk-request" class="md:w-[36rem]">
            <form wire:submit="submit" class="space-y-5">
                <flux:heading size="lg">KVKK Başvurusu</flux:heading>
                <flux:select wire:model="type" label="Başvuru konusu" placeholder="Seçin…" required>
                    @foreach (KvkkRequestType::cases() as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:textarea wire:model="message" label="Açıklama" rows="5" placeholder="Talebinizi ayrıntılı yazın." required />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">Gönder</flux:button>
                </div>
            </form>
        </flux:modal>
    </x-pages::settings.layout>
</section>
