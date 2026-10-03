<?php

use App\Billing\SubscriptionBilling;
use App\Models\Invoice;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/*
 * Admin → Faturalar: subscription invoices of every firm. Bank transfers are recorded here; failed card
 * charges can be retried right away; wrong invoices voided.
 */
new #[Title('Faturalar')] class extends Component {
    use WithPagination;

    #[Url(as: 'sekme', except: 'acik')]
    public string $tab = 'acik';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('manage-settings');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return [
            'acik' => Invoice::query()->open()->count(),
            'basarisiz' => Invoice::where('status', Invoice::FAILED)->count(),
            'odenen' => Invoice::where('status', Invoice::PAID)->count(),
            'tumu' => Invoice::count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Invoice>
     */
    #[Computed]
    public function invoices(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return Invoice::query()->with(['firm:id,name', 'card'])
            ->when($this->tab === 'acik', fn ($query) => $query->open())
            ->when($this->tab === 'basarisiz', fn ($query) => $query->where('status', Invoice::FAILED))
            ->when($this->tab === 'odenen', fn ($query) => $query->where('status', Invoice::PAID))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('number', 'like', '%'.$search.'%')
                ->orWhereHas('firm', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))))
            ->latest('due_on')->latest('id')
            ->paginate(25);
    }

    public function issueNow(SubscriptionBilling $billing): void
    {
        $this->authorize('manage-settings');
        $count = $billing->issueInvoices();
        unset($this->invoices, $this->counts);
        Flux::toast(variant: 'success', text: $count ? "{$count} yeni fatura oluşturuldu." : 'Oluşturulacak yeni fatura yok.');
    }

    public function charge(int $id, SubscriptionBilling $billing): void
    {
        $this->authorize('manage-settings');
        $invoice = Invoice::findOrFail($id);

        if ($billing->defaultCard($invoice->firm) === null) {
            Flux::toast(variant: 'danger', text: 'Firmanın kayıtlı kartı yok.');

            return;
        }

        $paid = $billing->charge($invoice, (string) request()->ip());
        unset($this->invoices, $this->counts);
        Flux::toast(variant: $paid ? 'success' : 'danger', text: $paid ? 'Ödeme alındı.' : 'Çekim başarısız: '.$invoice->fresh()?->failure_message);
    }

    public function markPaid(int $id, SubscriptionBilling $billing): void
    {
        $this->authorize('manage-settings');
        $billing->markPaidManually(Invoice::findOrFail($id), Auth::user());
        unset($this->invoices, $this->counts);
        Flux::toast(variant: 'success', text: 'Havale / EFT ile ödendi olarak işaretlendi.');
    }

    public function void(int $id, SubscriptionBilling $billing): void
    {
        $this->authorize('manage-settings');

        try {
            $billing->void(Invoice::findOrFail($id), Auth::user());
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: collect($e->errors())->flatten()->first());

            return;
        }

        unset($this->invoices, $this->counts);
        Flux::toast(variant: 'success', text: 'Fatura iptal edildi.');
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Faturalar</flux:heading>
            <flux:text class="mt-1">
                Sözleşmelerden her dönem başında oluşan abonelik faturaları (KDV %{{ rtrim(rtrim(number_format((float) config('billing.vat_rate'), 2, '.', ''), '0'), '.') }}).
                Kayıtlı kartı olan firmalardan otomatik çekilir; başarısız çekimler {{ config('billing.retry_days') }} gün arayla en fazla {{ config('billing.max_attempts') }} kez denenir.
            </flux:text>
        </div>
        <flux:button icon="arrow-path" wire:click="issueNow">Faturaları şimdi oluştur</flux:button>
    </div>

    @unless (app(SubscriptionBilling::class)->configured())
        <flux:callout variant="warning" icon="exclamation-triangle" heading="iyzico yapılandırılmadı">
            <flux:callout.text>.env dosyasına IYZICO_API_KEY ve IYZICO_SECRET_KEY eklenene kadar kart ile ödeme ve otomatik çekim kapalıdır; faturalar oluşur ve havale ile işaretlenebilir.</flux:callout.text>
        </flux:callout>
    @endunless

    <x-tabs :active="$tab" :tabs="['acik' => 'Ödenmemiş', 'basarisiz' => 'Başarısız', 'odenen' => 'Ödenen', 'tumu' => 'Tümü']" :counts="$this->counts" />

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Fatura no veya firma" class="max-w-xs" />

    <flux:table :paginate="$this->invoices">
        <flux:table.columns>
            <flux:table.column>Fatura</flux:table.column>
            <flux:table.column>Firma</flux:table.column>
            <flux:table.column>Dönem</flux:table.column>
            <flux:table.column align="end">Tutar</flux:table.column>
            <flux:table.column>Durum</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->invoices as $invoice)
                <flux:table.row :key="$invoice->id">
                    <flux:table.cell>
                        <div class="font-semibold">{{ $invoice->number }}</div>
                        <div class="text-xs text-zinc-500">{{ $invoice->description }}</div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $invoice->firm->name }}</flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ $invoice->periodLabel() }}</flux:table.cell>
                    <flux:table.cell align="end" class="font-semibold tabular-nums">{{ $invoice->money($invoice->total) }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="Invoice::STATUS_COLORS[$invoice->status]" inset="top bottom">{{ Invoice::STATUSES[$invoice->status] }}</flux:badge>
                        @if ($invoice->status === Invoice::FAILED)
                            <div class="mt-1 text-xs text-red-600">{{ $invoice->attempts }}. deneme · {{ $invoice->failure_message }}</div>
                        @elseif ($invoice->paid_at)
                            <div class="mt-1 text-xs text-zinc-500">{{ $invoice->paid_at->format('d.m.Y') }} · {{ Invoice::METHODS[$invoice->payment_method] ?? '' }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end" class="whitespace-nowrap">
                        @if ($invoice->isOpen())
                            <flux:dropdown>
                                <flux:button size="sm" icon:trailing="chevron-down">İşlem</flux:button>
                                <flux:menu>
                                    <flux:menu.item icon="credit-card" wire:click="charge({{ $invoice->id }})">Karttan şimdi çek</flux:menu.item>
                                    <flux:menu.item icon="banknotes" wire:click="markPaid({{ $invoice->id }})" wire:confirm="Havale / EFT ile ödendi olarak işaretlensin mi?">Havale ile ödendi</flux:menu.item>
                                    <flux:menu.item icon="x-mark" variant="danger" wire:click="void({{ $invoice->id }})" wire:confirm="Fatura iptal edilsin mi?">İptal et</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-10 text-center text-zinc-500">Bu listede fatura yok.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
