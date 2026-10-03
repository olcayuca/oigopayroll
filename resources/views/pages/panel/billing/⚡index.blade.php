<?php

use App\Billing\SubscriptionBilling;
use App\Enums\AuditEvent;
use App\Livewire\PanelComponent;
use App\Models\BillingCheckout;
use App\Models\Employee;
use App\Models\FirmContract;
use App\Models\FirmPaymentCard;
use App\Models\Invoice;
use App\Support\Audit;
use App\Support\FirmSettings;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/*
 * Abonelik / Lisans (prototype 32-abonelik): the firm's contract, usage, saved card, automatic payment and invoices.
 * Cards are entered on iyzico's page only; the saved card is charged automatically when an invoice is due.
 */
new #[Title('Abonelik')] class extends PanelComponent {
    use WithPagination;

    #[Url(as: 'odeme', except: '')]
    public string $result = '';

    #[Url(as: 'islem', except: null)]
    public ?int $checkoutId = null;

    public bool $autoPay = true;

    public function mount(SubscriptionBilling $billing): void
    {
        $this->authorize('manageBilling', $this->firm);
        $this->autoPay = FirmSettings::autoPay($this->firm);
        $billing->issueInvoices($this->firm);
    }

    #[Computed]
    public function contract(): ?FirmContract
    {
        return FirmContract::query()->where('firm_id', $this->firm->id)->running()->latest('starts_on')->first()
            ?? FirmContract::query()->where('firm_id', $this->firm->id)->latest('starts_on')->first();
    }

    #[Computed]
    public function card(): ?FirmPaymentCard
    {
        return app(SubscriptionBilling::class)->defaultCard($this->firm);
    }

    /**
     * @return LengthAwarePaginator<int, Invoice>
     */
    #[Computed]
    public function invoices(): LengthAwarePaginator
    {
        return Invoice::query()->where('firm_id', $this->firm->id)
            ->orderByRaw("case when status in ('pending', 'failed') then 0 else 1 end")->latest('due_on')->latest('id')
            ->paginate(12);
    }

    /**
     * Message of the payment the user just came back from (only this firm's).
     */
    #[Computed]
    public function checkout(): ?BillingCheckout
    {
        return $this->checkoutId ? BillingCheckout::query()->where('firm_id', $this->firm->id)->find($this->checkoutId) : null;
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function usage(): array
    {
        return [
            'Aktif personel' => Employee::query()->where('firm_id', $this->firm->id)->where('status', Employee::ACTIVE)->count(),
            'Şirket' => $this->firm->companies()->count(),
            'İşyeri' => $this->firm->workplaces()->count(),
            'Kullanıcı' => \App\Models\User::query()->where('firm_id', $this->firm->id)->where('is_active', true)->count(),
        ];
    }

    public function pay(int $invoiceId, SubscriptionBilling $billing): void
    {
        $this->authorize('manageBilling', $this->firm);
        $invoice = Invoice::query()->where('firm_id', $this->firm->id)->findOrFail($invoiceId);
        $this->redirectToCheckout(fn () => $billing->startCheckout($this->firm, Auth::user(), $invoice, (string) request()->ip()));
    }

    public function addCard(SubscriptionBilling $billing): void
    {
        $this->authorize('manageBilling', $this->firm);
        $this->redirectToCheckout(fn () => $billing->startCheckout($this->firm, Auth::user(), null, (string) request()->ip()));
    }

    public function removeCard(SubscriptionBilling $billing): void
    {
        $this->authorize('manageBilling', $this->firm);

        if ($this->card) {
            $billing->removeCard($this->card, Auth::user());
            unset($this->card);
            Flux::toast(variant: 'success', text: 'Kart kaldırıldı. Otomatik ödeme için yeni kart ekleyin.');
        }
    }

    public function updatedAutoPay(): void
    {
        $this->authorize('manageBilling', $this->firm);

        $settings = $this->firm->settings ?? [];
        $settings['billing'] = ['auto_pay' => $this->autoPay];
        $this->firm->forceFill(['settings' => $settings])->save();

        Audit::log(AuditEvent::BillingSettingsChanged, 'Otomatik ödeme '.($this->autoPay ? 'açıldı' : 'kapatıldı').": {$this->firm->name}", $this->firm);
        Flux::toast(variant: $this->autoPay ? 'success' : 'warning', text: $this->autoPay ? 'Otomatik ödeme açıldı.' : 'Otomatik ödeme kapatıldı; faturaları kendiniz ödemelisiniz.');
    }

    private function redirectToCheckout(Closure $start): void
    {
        try {
            $url = $start();
        } catch (ValidationException $e) {
            Flux::toast(variant: 'danger', text: collect($e->errors())->flatten()->first());

            return;
        }

        $this->redirect($url);
    }
}; ?>

<div>
    @php
        $billing = app(SubscriptionBilling::class);
        $contract = $this->contract;
        $card = $this->card;
        $open = Invoice::query()->where('firm_id', $this->firm->id)->open()->get();
    @endphp

    <x-panel.page-header :crumbs="['Yönetim' => null, 'Abonelik' => null, $this->firm->name => null]" title="Abonelik / Lisans"
        subtitle="Sözleşmeniz, kullanımınız, ödeme yönteminiz ve faturalarınız." />

    @if ($this->checkout)
        <x-panel.alert :variant="$this->checkout->status === 'completed' ? 'success' : 'danger'" class="mb-5" data-test="checkout-result">
            {{ $this->checkout->message ?? ($this->checkout->status === 'completed' ? 'İşlem tamamlandı.' : 'Ödeme tamamlanamadı.') }}
        </x-panel.alert>
    @endif

    @if ($open->where('status', Invoice::FAILED)->isNotEmpty())
        <x-panel.alert variant="danger" class="mb-5" title="Ödeme alınamadı">
            {{ $open->where('status', Invoice::FAILED)->count() }} faturanın otomatik ödemesi başarısız oldu. Kartla ödeyin veya kartınızı güncelleyin.
        </x-panel.alert>
    @endif

    <div class="mb-5 grid items-start gap-5 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
        {{-- Contract --}}
        <div class="overflow-hidden rounded-2xl bg-linear-160 from-side to-[#143A5E] p-6 text-white shadow-[0_10px_30px_rgba(16,40,72,0.18)]">
            <div class="text-[11px] font-bold tracking-[0.12em] text-mint-light">MEVCUT SÖZLEŞME</div>
            @if ($contract)
                <div class="mt-1.5 text-[21px] font-extrabold tracking-tight">{{ $contract->title }}</div>
                <div class="mt-1 text-[13px] text-side-text">
                    {{ $contract->contract_no }} · {{ $contract->starts_on->format('d.m.Y') }} – {{ $contract->ends_on?->format('d.m.Y') ?? 'süresiz' }}
                    · <span class="font-bold text-white">{{ $contract->status()->label() }}</span>
                </div>
                <div class="mt-5 flex flex-wrap items-end gap-x-8 gap-y-3">
                    <div>
                        <div class="text-[11.5px] font-semibold text-side-text">ÜCRET ({{ mb_strtoupper($contract->fee_type->label()) }})</div>
                        <div class="mt-0.5 text-[26px] font-extrabold tabular-nums">
                            {{ $contract->fee_amount ? \Illuminate\Support\Number::currency((float) $contract->fee_amount, $contract->currency ?: 'TRY', 'tr') : '—' }}
                            <span class="text-[13px] font-semibold text-side-text">+ KDV</span>
                        </div>
                    </div>
                    <div class="text-[12.5px] text-side-text">
                        @if ($contract->ends_on)
                            {{ $contract->auto_renew ? 'Sözleşme '.$contract->ends_on->format('d.m.Y').' tarihinde otomatik yenilenir.' : 'Sözleşme '.$contract->ends_on->format('d.m.Y').' tarihinde sona erer.' }}
                        @else
                            Süresiz sözleşme.
                        @endif
                    </div>
                </div>
            @else
                <div class="mt-2 text-[15px] font-bold">Henüz tanımlı bir sözleşme yok.</div>
                <div class="mt-1 text-[13px] text-side-text">Sözleşme HRD tarafından tanımlandığında faturalar burada oluşur.</div>
            @endif
        </div>

        {{-- Payment method --}}
        <div class="rounded-2xl border border-line bg-white p-5" data-test="payment-method">
            <div class="mb-3 text-[12px] font-bold tracking-[0.04em] text-muted">ÖDEME YÖNTEMİ</div>
            @if (! $billing->configured())
                <p class="text-[13px] text-muted">Kartla ödeme henüz etkin değil. Faturalarınızı havale / EFT ile ödeyebilirsiniz; bilgi için bordro uzmanınıza başvurun.</p>
            @elseif ($card)
                <div class="flex items-center gap-3 rounded-xl border border-line px-4 py-3">
                    <flux:icon.credit-card class="size-6 text-brand" />
                    <div class="min-w-0 flex-1">
                        <div class="text-[14px] font-extrabold text-ink" data-test="saved-card">{{ $card->label() }}</div>
                        <div class="text-[12px] text-muted">{{ $card->created_at?->format('d.m.Y') }} tarihinde {{ $card->adder->name ?? '—' }} ekledi</div>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <flux:button size="sm" icon="arrow-path" wire:click="addCard">Kartı değiştir</flux:button>
                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeCard" wire:confirm="Kayıtlı kart kaldırılsın mı? Otomatik ödeme duracak.">Kaldır</flux:button>
                </div>
            @else
                <p class="text-[13px] text-muted">Kayıtlı kart yok. Kart ekleyerek aylık faturalarınızın otomatik ödenmesini sağlayın.</p>
                <flux:button variant="primary" icon="credit-card" class="mt-3" wire:click="addCard" data-test="add-card">Kart ekle</flux:button>
            @endif

            @if ($billing->configured())
                <div class="mt-4 border-t border-line-3 pt-4">
                    <flux:switch wire:model.live="autoPay" label="Otomatik ödeme"
                        description="Fatura kesildiğinde kayıtlı karttan otomatik çekilir; başarısız olursa {{ config('billing.retry_days') }} gün arayla yeniden denenir." />
                </div>
                <p class="mt-3 flex items-start gap-1.5 text-[11.5px] leading-snug text-muted-2">
                    <flux:icon.lock-closed variant="micro" class="mt-px size-3.5 shrink-0" />
                    Kart bilgileri iyzico'nun güvenli ödeme sayfasında girilir; sistemimizde kart numarası saklanmaz.
                    Kart eklerken "Kartımı kaydet" seçeneğini işaretleyin; doğrulama için çekilen {{ config('billing.card_verification_amount') }} TL hemen iade edilir.
                </p>
            @endif
        </div>
    </div>

    {{-- Usage --}}
    <div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->usage as $label => $value)
            <div class="rounded-2xl border border-line bg-white px-5 py-4">
                <div class="text-[12px] font-bold text-muted">{{ $label }}</div>
                <div class="mt-1 text-[24px] font-extrabold text-ink tabular-nums">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    {{-- Invoices --}}
    <div class="rounded-2xl border border-line bg-white shadow-[0_1px_3px_rgba(16,40,72,0.04)]">
        <div class="border-b border-line-3 px-5 py-4">
            <h2 class="text-[15px] font-extrabold text-ink">Faturalar</h2>
            <p class="mt-0.5 text-[12.5px] text-muted">Her dönemin başında sözleşmenize göre oluşturulur; tutarlar KDV dahildir.</p>
        </div>
        <div class="divide-y divide-line-4">
            @forelse ($this->invoices as $invoice)
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3.5" wire:key="i-{{ $invoice->id }}" data-test="invoice-row">
                    <div class="min-w-0 flex-1">
                        <div class="text-[13.5px] font-bold text-ink">{{ $invoice->number }} <span class="font-semibold text-muted">· {{ $invoice->periodLabel() }}</span></div>
                        <div class="text-[12px] text-muted">
                            {{ $invoice->description }}
                            @if ($invoice->paid_at) · {{ $invoice->paid_at->format('d.m.Y') }} · {{ Invoice::METHODS[$invoice->payment_method] ?? '' }} @endif
                            @if ($invoice->status === Invoice::FAILED && $invoice->failure_message) · <span class="text-st-red">{{ $invoice->failure_message }}</span> @endif
                        </div>
                    </div>
                    <div class="text-[14px] font-extrabold text-ink tabular-nums">{{ $invoice->money($invoice->total) }}</div>
                    <x-panel.badge :color="Invoice::STATUS_COLORS[$invoice->status]">{{ Invoice::STATUSES[$invoice->status] }}</x-panel.badge>
                    @if ($invoice->isOpen() && $billing->configured())
                        <flux:button size="sm" variant="primary" icon="credit-card" wire:click="pay({{ $invoice->id }})">Kartla öde</flux:button>
                    @endif
                </div>
            @empty
                <x-panel.empty icon="document-text" title="Fatura yok">Sözleşmeniz başladığında ilk fatura burada görünür.</x-panel.empty>
            @endforelse
        </div>
        @if ($this->invoices->hasPages())
            <div class="border-t border-line-3 px-5 py-3">{{ $this->invoices->links('partials.panel-pagination') }}</div>
        @endif
    </div>
</div>
