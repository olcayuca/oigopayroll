<?php

use App\Actions\Firms\ManageContracts;
use App\Enums\ContractFeeType;
use App\Enums\DocumentType;
use App\Enums\FirmStatus;
use App\Models\Firm;
use App\Models\FirmContract;
use App\Models\FirmDocument;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Sözleşmeler')] class extends Component {
    use WithPagination;

    public const FIELD_TABS = [
        'genel' => ['firm_id', 'contract_no', 'title', 'starts_on', 'ends_on', 'auto_renew', 'notice_days'],
        'ucret' => ['fee_type', 'fee_amount', 'currency'],
        'belge' => ['document_id', 'notes'],
    ];

    #[Url(as: 'sekme')]
    public string $tab = 'aktif';

    #[Url(as: 'firma', except: '')]
    public string $firmFilter = '';

    public ?int $editingId = null;

    public string $formTab = 'genel';

    /** @var array<string, mixed> */
    public array $form = [];

    public ?int $terminatingId = null;

    public string $terminatedOn = '';

    public string $terminationReason = '';

    public function mount(): void
    {
        $this->authorize('manage-settings');

        if (request()->integer('duzenle') > 0) {
            $this->edit(request()->integer('duzenle'));
        } elseif (request()->boolean('yeni')) {
            $this->create();
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['tab', 'firmFilter'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return [
            'aktif' => $this->baseQuery()->running()->count(),
            'bitiyor' => $this->baseQuery()->endingSoon()->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, FirmContract>
     */
    #[Computed]
    public function contracts(): LengthAwarePaginator
    {
        return $this->baseQuery()->with('firm')
            ->when($this->tab === 'aktif', fn ($query) => $query->running())
            ->when($this->tab === 'bitiyor', fn ($query) => $query->endingSoon())
            ->when($this->tab === 'biten', fn ($query) => $query->where(fn ($inner) => $inner
                ->whereDate('terminated_on', '<=', today())
                ->orWhere(fn ($ended) => $ended->whereNotNull('ends_on')->whereDate('ends_on', '<', today()))))
            ->when($this->tab === 'bitiyor', fn ($query) => $query->orderBy('ends_on'), fn ($query) => $query->orderByDesc('starts_on'))
            ->paginate(25);
    }

    /**
     * @return Collection<int, Firm>
     */
    #[Computed]
    public function firms(): Collection
    {
        return Firm::query()->whereIn('status', [FirmStatus::Active, FirmStatus::Pending, FirmStatus::Passive])->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Contract documents of the firm in the form.
     *
     * @return Collection<int, FirmDocument>
     */
    #[Computed]
    public function documents(): Collection
    {
        $firmId = (int) ($this->form['firm_id'] ?? 0);

        return FirmDocument::query()->where('firm_id', $firmId)
            ->orderByRaw('type = ? desc', [DocumentType::Contract->value])->latest('id')->get(['id', 'firm_id', 'title', 'type']);
    }

    /**
     * @return list<string>
     */
    public function invalidTabs(): array
    {
        $fields = array_map(fn ($key) => str_replace('form.', '', $key), $this->getErrorBag()->keys());

        return array_keys(array_filter(self::FIELD_TABS, fn ($tabFields) => array_intersect($tabFields, $fields) !== []));
    }

    public function create(): void
    {
        $this->reset('editingId');
        $this->formTab = 'genel';
        $this->form = [
            'firm_id' => $this->firmFilter !== '' ? $this->firmFilter : '', 'contract_no' => '', 'title' => 'Bordro Hizmet Sözleşmesi',
            'starts_on' => today()->toDateString(), 'ends_on' => today()->addYear()->subDay()->toDateString(), 'auto_renew' => true,
            'notice_days' => 30, 'fee_type' => ContractFeeType::MonthlyPerEmployee->value, 'fee_amount' => '', 'currency' => 'TRY',
            'document_id' => '', 'notes' => '',
        ];
        $this->resetValidation();

        Flux::modal('contract')->show();
    }

    public function edit(int $id): void
    {
        $contract = FirmContract::findOrFail($id);

        $this->editingId = $contract->id;
        $this->formTab = 'genel';
        $this->form = [
            'firm_id' => (string) $contract->firm_id, 'contract_no' => $contract->contract_no, 'title' => $contract->title,
            'starts_on' => $contract->starts_on->toDateString(), 'ends_on' => (string) $contract->ends_on?->toDateString(),
            'auto_renew' => $contract->auto_renew, 'notice_days' => $contract->notice_days, 'fee_type' => $contract->fee_type->value,
            'fee_amount' => (string) $contract->fee_amount, 'currency' => $contract->currency,
            'document_id' => (string) $contract->document_id, 'notes' => (string) $contract->notes,
        ];
        $this->resetValidation();

        Flux::modal('contract')->show();
    }

    public function save(ManageContracts $contracts): void
    {
        $this->authorize('manage-settings');
        $this->resetValidation();

        try {
            $contracts->save($this->editingId ? FirmContract::findOrFail($this->editingId) : null, $this->form, auth()->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError('form.'.$field, $messages[0]);
            }
            $this->formTab = $this->invalidTabs()[0] ?? $this->formTab;

            return;
        }

        unset($this->contracts, $this->counts);
        Flux::modal('contract')->close();
        Flux::toast(variant: 'success', text: 'Sözleşme kaydedildi.');
    }

    public function openTerminate(int $id): void
    {
        $this->terminatingId = $id;
        $this->terminatedOn = today()->toDateString();
        $this->terminationReason = '';
        $this->resetValidation();

        Flux::modal('terminate')->show();
    }

    public function terminate(ManageContracts $contracts): void
    {
        $this->authorize('manage-settings');
        $this->resetValidation();

        try {
            $contracts->terminate(FirmContract::findOrFail($this->terminatingId), $this->terminatedOn, $this->terminationReason, auth()->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field === 'terminated_on' ? 'terminatedOn' : 'terminationReason', $messages[0]);
            }

            return;
        }

        unset($this->contracts, $this->counts);
        Flux::modal('terminate')->close();
        Flux::toast(variant: 'success', text: 'Sözleşme feshedildi.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<FirmContract>
     */
    private function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return FirmContract::query()->when($this->firmFilter !== '', fn ($query) => $query->where('firm_id', (int) $this->firmFilter));
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Sözleşmeler</flux:heading>
            <flux:text class="mt-1">
                Müşteri firmalarla hizmet sözleşmeleri. Bitişine fesih bildirim süresi + {{ FirmContract::WARNING_DAYS }} gün kalanlar "Bitiyor" sekmesinde;
                otomatik yenilenenler bitiş tarihinde aynı süre kadar uzatılır.
            </flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">Yeni Sözleşme</flux:button>
    </div>

    <x-tabs :active="$tab" :tabs="['aktif' => 'Aktif', 'bitiyor' => 'Bitiyor', 'biten' => 'Sona Eren / Feshedilen', 'tumu' => 'Tümü']" :counts="$this->counts" />

    <flux:select wire:model.live="firmFilter" class="max-w-xs">
        <flux:select.option value="">Tüm firmalar</flux:select.option>
        @foreach ($this->firms as $firm)
            <flux:select.option value="{{ $firm->id }}">{{ $firm->name }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:table :paginate="$this->contracts">
        <flux:table.columns>
            <flux:table.column>Sözleşme</flux:table.column>
            <flux:table.column>Firma</flux:table.column>
            <flux:table.column>Süre</flux:table.column>
            <flux:table.column align="end">Ücret</flux:table.column>
            <flux:table.column>Durum</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->contracts as $contract)
                @php($status = $contract->status())
                <flux:table.row :key="$contract->id">
                    <flux:table.cell>
                        <div class="font-medium">{{ $contract->contract_no }}</div>
                        <div class="text-xs text-zinc-500">{{ $contract->title }}</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <a href="{{ route('admin.firms.show', ['firm' => $contract->firm, 'sekme' => 'sozlesmeler']) }}" wire:navigate class="hover:underline">{{ $contract->firm->name }}</a>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">
                        {{ $contract->starts_on->format('d.m.Y') }} – {{ $contract->ends_on?->format('d.m.Y') ?? 'süresiz' }}
                        <div class="text-xs text-zinc-500">
                            @if ($contract->auto_renew) Otomatik yenilenir · @endif
                            @if ($contract->noticeDeadline()) Son bildirim {{ $contract->noticeDeadline()->format('d.m.Y') }} @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell align="end" class="whitespace-nowrap">
                        {{ $contract->fee_amount !== null ? number_format((float) $contract->fee_amount, 2, ',', '.').' '.$contract->currency : '—' }}
                        <div class="text-xs text-zinc-500">{{ $contract->fee_type->label() }}</div>
                    </flux:table.cell>
                    <flux:table.cell><flux:badge size="sm" :color="$status->color()" inset="top bottom">{{ $status->label() }}</flux:badge></flux:table.cell>
                    <flux:table.cell align="end" class="whitespace-nowrap">
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $contract->id }})" />
                        @if ($contract->terminated_on === null)
                            <flux:button size="sm" variant="ghost" icon="no-symbol" wire:click="openTerminate({{ $contract->id }})">Feshet</flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-10 text-center text-zinc-500">Sözleşme yok.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="contract" class="md:w-[40rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Sözleşmeyi Düzenle' : 'Yeni Sözleşme' }}</flux:heading>

            <x-tabs :active="$formTab" model="formTab" :tabs="['genel' => 'Genel', 'ucret' => 'Ücret', 'belge' => 'Belge ve Not']" :invalid="$this->invalidTabs()" />

            <div @class(['space-y-4', 'hidden' => $formTab !== 'genel'])>
                <flux:select wire:model.live="form.firm_id" label="Firma" placeholder="Seçin…" :disabled="(bool) $editingId">
                    @foreach ($this->firms as $firm)
                        <flux:select.option value="{{ $firm->id }}">{{ $firm->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="form.contract_no" label="Sözleşme no" required />
                    <flux:input wire:model="form.title" label="Başlık" required />
                    <flux:input type="date" wire:model="form.starts_on" label="Başlangıç" required />
                    <flux:input type="date" wire:model="form.ends_on" label="Bitiş" description="Boş bırakılırsa süresiz." />
                    <flux:input type="number" min="0" max="365" wire:model="form.notice_days" label="Fesih bildirim süresi (gün)" />
                </div>
                <flux:checkbox wire:model="form.auto_renew" label="Bitişte aynı süre kadar otomatik yenilensin" />
            </div>

            <div @class(['grid gap-4 sm:grid-cols-2', 'hidden' => $formTab !== 'ucret'])>
                <flux:select wire:model="form.fee_type" label="Ücret tipi" class="sm:col-span-2">
                    @foreach (ContractFeeType::cases() as $type)
                        <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input type="number" step="0.01" min="0" wire:model="form.fee_amount" label="Ücret (KDV hariç)" />
                <flux:select wire:model="form.currency" label="Para birimi">
                    @foreach (ManageContracts::CURRENCIES as $currency)
                        <flux:select.option value="{{ $currency }}">{{ $currency }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div @class(['space-y-4', 'hidden' => $formTab !== 'belge'])>
                <flux:select wire:model="form.document_id" label="Sözleşme belgesi" description="Firma belgelerinden seçilir; önce firma detayı → Belgeler'den yükleyin.">
                    <flux:select.option value="">— Yok —</flux:select.option>
                    @foreach ($this->documents as $document)
                        <flux:select.option value="{{ $document->id }}">{{ $document->title }} ({{ $document->type->label() }})</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:textarea wire:model="form.notes" label="Not" rows="4" />
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Kaydet</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="terminate" class="md:w-[32rem]">
        <form wire:submit="terminate" class="space-y-5">
            <flux:heading size="lg">Sözleşmeyi feshet</flux:heading>
            <flux:input type="date" wire:model="terminatedOn" label="Fesih tarihi" required />
            <flux:textarea wire:model="terminationReason" label="Gerekçe" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Vazgeç</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">Feshet</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
