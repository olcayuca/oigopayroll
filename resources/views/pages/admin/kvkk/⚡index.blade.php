<?php

use App\Enums\KvkkRequestStatus;
use App\Enums\PolicyType;
use App\Kvkk\AnonymizeUser;
use App\Kvkk\DataRequests;
use App\Kvkk\Policies;
use App\Models\Consent;
use App\Models\KvkkRequest;
use App\Models\PolicyDocument;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('KVKK')] class extends Component {
    #[Url(as: 'sekme')]
    public string $tab = 'metinler';

    #[Url(as: 'durum')]
    public string $statusFilter = 'acik';

    public string $search = '';

    // Publish form
    public string $publishType = '';

    public string $title = '';

    public string $body = '';

    // Request handling form
    public ?int $requestId = null;

    public string $status = '';

    public string $response = '';

    public function mount(): void
    {
        $this->authorize('manage-settings');
    }

    /**
     * All versions per type, newest first.
     *
     * @return Collection<string, EloquentCollection<int, PolicyDocument>>
     */
    #[Computed]
    public function versions(): Collection
    {
        $documents = PolicyDocument::query()->withCount([
            'consents as accepted_count' => fn ($query) => $query->where('accepted', true)->whereNull('revoked_at'),
        ])->orderByDesc('version')->get();

        return collect(PolicyType::cases())->mapWithKeys(fn (PolicyType $type) => [
            $type->value => $documents->where('type', $type)->values(),
        ]);
    }

    /**
     * Decision counts for the current version of each text, among active users.
     *
     * @return list<array{document: PolicyDocument, users: int, accepted: int, refused: int, pending: int}>
     */
    #[Computed]
    public function stats(): array
    {
        $users = User::query()->where('is_active', true)->count();
        $stats = [];

        foreach (app(Policies::class)->currentDocuments() as $document) {
            $decisions = Consent::query()->where('policy_document_id', $document->id)
                ->whereHas('user', fn ($query) => $query->where('is_active', true))->get();
            $accepted = $decisions->filter->isGiven()->count();

            $stats[] = [
                'document' => $document,
                'users' => $users,
                'accepted' => $accepted,
                'refused' => $decisions->count() - $accepted,
                'pending' => max(0, $users - $decisions->count()),
            ];
        }

        return $stats;
    }

    /**
     * @return EloquentCollection<int, Consent>
     */
    #[Computed]
    public function consents(): EloquentCollection
    {
        return Consent::query()->with(['user', 'document'])
            ->when(trim($this->search) !== '', fn ($query) => $query->whereHas('user', fn ($users) => $users
                ->where('email', 'like', '%'.trim($this->search).'%')->orWhere('name', 'like', '%'.trim($this->search).'%')))
            ->latest('decided_at')->limit(200)->get();
    }

    /**
     * @return EloquentCollection<int, KvkkRequest>
     */
    #[Computed]
    public function requests(): EloquentCollection
    {
        return KvkkRequest::query()->with(['user', 'handler'])
            ->when($this->statusFilter === 'acik', fn ($query) => $query->whereIn('status', [KvkkRequestStatus::Open, KvkkRequestStatus::InProgress]))
            ->orderBy('due_at')->latest('id')->get();
    }

    #[Computed]
    public function openCount(): int
    {
        return KvkkRequest::query()->whereIn('status', [KvkkRequestStatus::Open, KvkkRequestStatus::InProgress])->count();
    }

    #[Computed]
    public function selected(): ?KvkkRequest
    {
        return $this->requestId ? KvkkRequest::with('user')->find($this->requestId) : null;
    }

    public function newVersion(string $type): void
    {
        $policyType = PolicyType::from($type);
        $current = app(Policies::class)->current($policyType);

        $this->publishType = $policyType->value;
        $this->title = $current->title ?? $policyType->label();
        $this->body = $current->body ?? '';
        $this->resetValidation();

        Flux::modal('publish')->show();
    }

    public function publish(Policies $policies): void
    {
        $this->authorize('manage-settings');

        $this->validate([
            'publishType' => ['required', \Illuminate\Validation\Rule::enum(PolicyType::class)],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000'],
        ], [], ['title' => 'Başlık', 'body' => 'Metin']);

        $document = $policies->publish(PolicyType::from($this->publishType), $this->title, $this->body, auth()->user());

        unset($this->versions, $this->stats);
        Flux::modal('publish')->close();
        Flux::toast(variant: 'success', text: "{$document->type->label()} v{$document->version} yayımlandı. Tüm kullanıcılardan yeniden onay istenecek.");
    }

    public function openRequest(int $id): void
    {
        $request = KvkkRequest::findOrFail($id);

        $this->requestId = $request->id;
        $this->status = $request->status->value;
        $this->response = (string) $request->response;
        $this->resetValidation();

        Flux::modal('request')->show();
    }

    public function saveRequest(DataRequests $requests): void
    {
        $this->authorize('manage-settings');
        $this->resetValidation();

        $requests->update(KvkkRequest::findOrFail($this->requestId), ['status' => $this->status, 'response' => $this->response], auth()->user());

        unset($this->requests, $this->openCount, $this->selected);
        Flux::modal('request')->close();
        Flux::toast(variant: 'success', text: 'Başvuru güncellendi.');
    }

    public function anonymize(AnonymizeUser $action): void
    {
        $this->authorize('manage-settings');

        $user = $this->selected?->user;
        abort_if($user === null, 404);

        $action->handle($user, auth()->user());

        unset($this->selected, $this->requests);
        Flux::toast(variant: 'success', text: 'Hesap anonimleştirildi. Başvuruyu yanıtlayıp sonuçlandırmayı unutmayın.');
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">KVKK</flux:heading>
        <flux:text class="mt-1">
            Aydınlatma ve açık rıza metinleri, kullanıcı onayları ve ilgili kişi başvuruları (6698 s.K. md. 10, 11, 13).
        </flux:text>
    </div>

    <x-tabs :active="$tab" :tabs="['metinler' => 'Metinler', 'onaylar' => 'Onaylar', 'basvurular' => 'Başvurular']" :counts="['basvurular' => $this->openCount]" />

    @if ($tab === 'metinler')
        <flux:callout icon="exclamation-triangle" color="amber" heading="Metinler hukuki onaydan geçmelidir"
            text="Sistemle gelen metinler yer tutucu taslaktır. Yayımlamadan önce KVKK danışmanınıza / avukatınıza kontrol ettirin. Yeni sürüm yayımlandığında tüm kullanıcılardan yeniden karar istenir; eski sürümler ve verilen onaylar kanıt olarak saklanır." />

        <div class="grid gap-6 lg:grid-cols-2">
            @foreach (PolicyType::cases() as $type)
                @php($versions = $this->versions[$type->value])
                <flux:card class="space-y-4" wire:key="type-{{ $type->value }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <flux:heading size="lg">{{ $type->label() }}</flux:heading>
                            <flux:text size="sm">{{ $type->isMandatory() ? 'Zorunlu — kullanıcı "okudum" onayı verir.' : 'İsteğe bağlı — kullanıcı onaylar veya reddeder, sonradan geri alabilir.' }}</flux:text>
                        </div>
                        <flux:button size="sm" variant="primary" icon="plus" wire:click="newVersion('{{ $type->value }}')">Yeni sürüm</flux:button>
                    </div>

                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Sürüm</flux:table.column>
                            <flux:table.column>Başlık</flux:table.column>
                            <flux:table.column>Yayım</flux:table.column>
                            <flux:table.column align="end">Onay</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse ($versions as $document)
                                <flux:table.row :key="$document->id">
                                    <flux:table.cell>
                                        v{{ $document->version }}
                                        @if ($loop->first) <flux:badge size="sm" color="green" inset="top bottom">Güncel</flux:badge> @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="max-w-48 truncate">{{ $document->title }}</flux:table.cell>
                                    <flux:table.cell>{{ $document->published_at?->format('d.m.Y') }}</flux:table.cell>
                                    <flux:table.cell align="end">{{ $document->getAttribute('accepted_count') }}</flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="4" class="py-6 text-center text-zinc-500">Yayımlanmış sürüm yok.</flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>

                    @if ($versions->isNotEmpty())
                        <flux:button size="sm" variant="ghost" icon="arrow-top-right-on-square" :href="route('kvkk.document', $type->value)" target="_blank">Güncel metni görüntüle</flux:button>
                    @endif
                </flux:card>
            @endforeach
        </div>
    @endif

    @if ($tab === 'onaylar')
        <div class="grid gap-4 md:grid-cols-2">
            @forelse ($this->stats as $stat)
                <flux:card class="space-y-3">
                    <flux:heading>{{ $stat['document']->type->label() }} v{{ $stat['document']->version }}</flux:heading>
                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div><div class="text-2xl font-semibold text-green-600">{{ $stat['accepted'] }}</div><flux:text size="sm">{{ $stat['document']->type->isMandatory() ? 'Okudu' : 'Rıza verdi' }}</flux:text></div>
                        <div><div class="text-2xl font-semibold text-zinc-500">{{ $stat['refused'] }}</div><flux:text size="sm">Reddetti / geri aldı</flux:text></div>
                        <div><div class="text-2xl font-semibold text-amber-600">{{ $stat['pending'] }}</div><flux:text size="sm">Bekliyor</flux:text></div>
                    </div>
                    <flux:text size="sm">{{ $stat['users'] }} aktif kullanıcı arasında.</flux:text>
                </flux:card>
            @empty
                <flux:text>Yayımlanmış metin yok.</flux:text>
            @endforelse
        </div>

        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Kullanıcı adı veya e-posta" class="max-w-sm" />

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Kullanıcı</flux:table.column>
                <flux:table.column>Metin</flux:table.column>
                <flux:table.column>Karar</flux:table.column>
                <flux:table.column>Tarih</flux:table.column>
                <flux:table.column>IP</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->consents as $consent)
                    <flux:table.row :key="$consent->id">
                        <flux:table.cell>
                            <div class="font-medium">{{ $consent->user->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $consent->user->email }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $consent->document->type->label() }} v{{ $consent->document->version }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($consent->isGiven())
                                <flux:badge size="sm" color="green" inset="top bottom">{{ $consent->document->type->isMandatory() ? 'Okundu' : 'Onay' }}</flux:badge>
                            @elseif ($consent->revoked_at)
                                <flux:badge size="sm" color="amber" inset="top bottom">Geri alındı {{ $consent->revoked_at->format('d.m.Y') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc" inset="top bottom">Ret</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $consent->decided_at->format('d.m.Y H:i') }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $consent->ip_address }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-10 text-center text-zinc-500">Kayıt yok.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    @if ($tab === 'basvurular')
        <flux:radio.group wire:model.live="statusFilter" variant="segmented" class="max-w-xs">
            <flux:radio value="acik" label="Sonuçlanmamış" />
            <flux:radio value="tumu" label="Tümü" />
        </flux:radio.group>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>No</flux:table.column>
                <flux:table.column>Başvuran</flux:table.column>
                <flux:table.column>Konu</flux:table.column>
                <flux:table.column>Tarih</flux:table.column>
                <flux:table.column>Son yanıt</flux:table.column>
                <flux:table.column>Durum</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->requests as $request)
                    <flux:table.row :key="$request->id">
                        <flux:table.cell>#{{ $request->id }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium">{{ $request->requester_name }}</div>
                            <div class="text-xs text-zinc-500">{{ $request->requester_email }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $request->type->shortLabel() }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">{{ $request->created_at?->format('d.m.Y') }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap">
                            @if ($request->isOverdue())
                                <flux:badge size="sm" color="red" inset="top bottom">{{ $request->due_at->format('d.m.Y') }} · gecikti</flux:badge>
                            @else
                                {{ $request->due_at->format('d.m.Y') }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$request->status->color()" inset="top bottom">{{ $request->status->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" wire:click="openRequest({{ $request->id }})">İncele</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500">Başvuru yok.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="publish" class="md:w-[48rem]">
        <form wire:submit="publish" class="space-y-5">
            <flux:heading size="lg">{{ PolicyType::tryFrom($publishType)?->label() }} — yeni sürüm</flux:heading>
            <flux:text>Yayımlandığında tüm kullanıcılar bir sonraki işlemlerinde metni yeniden onaylamaya yönlendirilir.</flux:text>
            <flux:input wire:model="title" label="Başlık" required />
            <flux:textarea wire:model="body" label="Metin" rows="16" description="Markdown desteklenir: ## Başlık, **kalın**, - madde." required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Yayımla</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="request" class="md:w-[40rem]">
        @if ($request = $this->selected)
            <form wire:submit="saveRequest" class="space-y-5">
                <div>
                    <flux:heading size="lg">Başvuru #{{ $request->id }} · {{ $request->type->shortLabel() }}</flux:heading>
                    <flux:text size="sm">{{ $request->requester_name }} ({{ $request->requester_email }}) · {{ $request->created_at?->format('d.m.Y H:i') }} · son yanıt {{ $request->due_at->format('d.m.Y') }}</flux:text>
                </div>

                <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                    <flux:text class="whitespace-pre-line">{{ $request->message }}</flux:text>
                </div>

                @if ($request->user)
                    <div class="flex flex-wrap gap-2">
                        <flux:button size="sm" icon="arrow-down-tray" :href="route('admin.kvkk.export', $request)">Kişisel veri dökümü (JSON)</flux:button>
                        @if ($request->type === \App\Enums\KvkkRequestType::Erasure && ! AnonymizeUser::isAnonymized($request->user))
                            <flux:button size="sm" variant="danger" icon="user-minus" wire:click="anonymize"
                                wire:confirm="Hesap kapatılacak; ad ve e-posta kalıcı olarak silinecek, yetkiler kaldırılacak. Devam edilsin mi?">Hesabı anonimleştir</flux:button>
                        @endif
                    </div>
                    @if (AnonymizeUser::isAnonymized($request->user))
                        <flux:text size="sm">Hesap anonimleştirildi.</flux:text>
                    @endif
                @endif

                <flux:select wire:model="status" label="Durum">
                    @foreach (KvkkRequestStatus::cases() as $option)
                        <flux:select.option value="{{ $option->value }}">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:textarea wire:model="response" label="Yanıt" rows="5" description="Başvuru sahibi yanıtı Ayarlar → KVKK sayfasında görür. Sonuçlandırırken zorunludur." />
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="filled">Kapat</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">Kaydet</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
