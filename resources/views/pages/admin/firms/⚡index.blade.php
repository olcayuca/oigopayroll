<?php

use App\Actions\Firms\CreateFirm;
use App\Actions\Firms\ReviewFirm;
use App\Enums\FirmStatus;
use App\Livewire\Concerns\MapsValidationErrors;
use App\Models\Firm;
use App\Validation\FirmRules;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Firmalar')] class extends Component {
    use MapsValidationErrors, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    /** @var array<string, string> */
    public array $firmForm = [];

    public ?int $rejectingId = null;

    public string $rejectionReason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Firm::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Firm>
     */
    #[Computed]
    public function firms(): LengthAwarePaginator
    {
        return Firm::visibleTo(Auth::user())
            ->with('creator')
            ->withCount(['companies', 'workplaces'])
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('title', 'like', '%'.$this->search.'%')
                ->orWhere('tax_number', 'like', '%'.$this->search.'%')))
            ->when(FirmStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status))
            ->orderByRaw('status = ? desc', [FirmStatus::Pending->value])
            ->latest()
            ->paginate(15);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return Firm::visibleTo(Auth::user())
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    public function createFirm(CreateFirm $createFirm): void
    {
        $this->authorize('create', Firm::class);

        $firm = $this->mappingErrors('firmForm', FirmRules::FIELDS, fn () => $createFirm->handle(Auth::user(), $this->firmForm));

        if (! $firm) {
            return;
        }

        $this->reset('firmForm');
        Flux::modal('create-firm')->close();
        Flux::toast(variant: 'success', text: "\"{$firm->name}\" oluşturuldu ve aktif edildi.");

        $this->redirectRoute('admin.firms.show', $firm, navigate: true);
    }

    public function approve(int $firmId, ReviewFirm $reviewFirm): void
    {
        $firm = $this->findFirm($firmId);
        $this->authorize('review', $firm);

        $reviewFirm->approve($firm, Auth::user());

        Flux::toast(variant: 'success', text: "\"{$firm->name}\" onaylandı.");
    }

    public function startRejecting(int $firmId): void
    {
        $this->authorize('review', $this->findFirm($firmId));

        $this->rejectingId = $firmId;
        $this->reset('rejectionReason');
        $this->resetValidation();

        Flux::modal('reject-firm')->show();
    }

    public function reject(ReviewFirm $reviewFirm): void
    {
        $firm = $this->findFirm((int) $this->rejectingId);
        $this->authorize('review', $firm);

        $this->validate(['rejectionReason' => ['required', 'string', 'max:1000']], [], ['rejectionReason' => 'Red gerekçesi']);

        $reviewFirm->reject($firm, Auth::user(), $this->rejectionReason);

        $this->reset('rejectingId', 'rejectionReason');
        Flux::modal('reject-firm')->close();
        Flux::toast(variant: 'success', text: "\"{$firm->name}\" reddedildi.");
    }

    private function findFirm(int $firmId): Firm
    {
        return Firm::visibleTo(Auth::user())->findOrFail($firmId);
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Firmalar</flux:heading>
            <flux:text class="mt-1">Müşteri firmaları, onay süreçleri ve firmaya bağlı şirketler.</flux:text>
        </div>

        @can('create', \App\Models\Firm::class)
            <flux:modal.trigger name="create-firm">
                <flux:button variant="primary" icon="plus">Yeni Firma</flux:button>
            </flux:modal.trigger>
        @endcan
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <div class="w-full sm:w-72">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Firma ara..." clearable />
        </div>

        <flux:radio.group wire:model.live="status" variant="segmented" size="sm">
            <flux:radio value="" label="Tümü ({{ array_sum($this->counts) }})" />
            @foreach (\App\Enums\FirmStatus::cases() as $case)
                <flux:radio value="{{ $case->value }}" label="{{ $case->label() }} ({{ $this->counts[$case->value] ?? 0 }})" />
            @endforeach
        </flux:radio.group>
    </div>

    <flux:table :paginate="$this->firms">
        <flux:table.columns>
            <flux:table.column>Firma</flux:table.column>
            <flux:table.column>Durum</flux:table.column>
            <flux:table.column>Kaynak</flux:table.column>
            <flux:table.column align="end">Şirket</flux:table.column>
            <flux:table.column align="end">İşyeri</flux:table.column>
            <flux:table.column>Oluşturulma</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->firms as $firm)
                <flux:table.row :key="$firm->id">
                    <flux:table.cell variant="strong">
                        <a href="{{ route('admin.firms.show', $firm) }}" wire:navigate class="hover:underline">{{ $firm->name }}</a>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$firm->status->color()" inset="top bottom">{{ $firm->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $firm->source->label() }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $firm->companies_count }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $firm->workplaces_count }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">
                        {{ $firm->created_at?->format('d.m.Y H:i') }}
                        @if ($firm->creator)
                            <div class="text-xs text-zinc-500">{{ $firm->creator->name }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end" class="whitespace-nowrap">
                        @if ($firm->status === \App\Enums\FirmStatus::Pending)
                            @can('review', $firm)
                                <flux:button size="sm" variant="primary" color="green" icon="check"
                                    wire:click="approve({{ $firm->id }})" wire:confirm="&quot;{{ $firm->name }}&quot; onaylansın mı?">
                                    Onayla
                                </flux:button>
                                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="startRejecting({{ $firm->id }})">Reddet</flux:button>
                            @endcan
                        @endif
                        <flux:button size="sm" variant="ghost" icon="chevron-right" :href="route('admin.firms.show', $firm)" wire:navigate />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500">
                        {{ $search !== '' || $status !== '' ? 'Filtreye uyan firma bulunamadı.' : 'Henüz firma yok.' }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @can('create', \App\Models\Firm::class)
    <flux:modal name="create-firm" class="md:w-[40rem]">
        <form wire:submit="createFirm" class="space-y-6">
            <div>
                <flux:heading size="lg">Yeni Firma</flux:heading>
                <flux:text class="mt-1">HRD tarafından oluşturulan firmalar onay beklemeden aktif olur. Müşteri kullanıcıları daha sonra eklenebilir.</flux:text>
            </div>

            <x-firm-fields />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Vazgeç</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Firmayı Oluştur</flux:button>
            </div>
        </form>
    </flux:modal>
    @endcan

    <flux:modal name="reject-firm" class="md:w-[28rem]">
        <form wire:submit="reject" class="space-y-6">
            <div>
                <flux:heading size="lg">Firmayı Reddet</flux:heading>
                <flux:text class="mt-1">Gerekçe firmayı oluşturan müşteriye gösterilecektir.</flux:text>
            </div>

            <flux:textarea wire:model="rejectionReason" label="Red gerekçesi" rows="4" required />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">Vazgeç</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">Reddet</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
