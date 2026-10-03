<?php

namespace Tests\Feature;

use App\Actions\Access\GrantAccess;
use App\Billing\CheckoutStart;
use App\Billing\GatewayResult;
use App\Billing\PaymentGateway;
use App\Billing\SubscriptionBilling;
use App\Enums\ContractFeeType;
use App\Enums\Permission;
use App\Enums\Portal;
use App\Models\BillingCheckout;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Firm;
use App\Models\FirmContract;
use App\Models\FirmPaymentCard;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Workplace;
use Carbon\CarbonImmutable;
use Database\Seeders\PayrollCodeSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Abonelik: invoices from the contract, automatic monthly card charge, iyzico hosted page, admin follow-up.
 */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    private Firm $firm;

    private User $owner;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferenceDataSeeder::class, PayrollCodeSeeder::class]);
        $this->travelTo(CarbonImmutable::parse('2026-03-20 10:00'));
        $this->firm = Firm::factory()->create(['name' => 'Oigo Grup']);
        $workplace = Workplace::factory()->for(Company::factory()->for($this->firm))->create();
        Employee::factory()->count(3)->for($workplace)->create();
        Employee::factory()->for($workplace)->create(['status' => Employee::LEFT]);
        $this->owner = User::factory()->create(['firm_id' => $this->firm->id]);
        app(GrantAccess::class)->handle($this->owner, $this->firm, Permission::firmOwnerDefaults());

        $this->gateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    private function contract(ContractFeeType $type, string $fee = '1000.00', array $attributes = []): FirmContract
    {
        return FirmContract::create([
            'firm_id' => $this->firm->id, 'contract_no' => 'SZ-'.$type->value, 'title' => 'Bordro hizmeti', 'starts_on' => '2026-01-15',
            'ends_on' => null, 'auto_renew' => true, 'notice_days' => 30, 'fee_type' => $type, 'fee_amount' => $fee, 'currency' => 'TRY', ...$attributes,
        ]);
    }

    private function card(): FirmPaymentCard
    {
        return FirmPaymentCard::create(['firm_id' => $this->firm->id, 'card_user_key' => 'cuk', 'card_token' => 'ct', 'last_four' => '4242',
            'card_association' => 'VISA', 'is_default' => true, 'added_by' => $this->owner->id]);
    }

    public function test_periods_follow_the_contract(): void
    {
        $today = CarbonImmutable::parse('2026-03-20');

        [$start, $end] = SubscriptionBilling::periodFor($this->contract(ContractFeeType::MonthlyFixed), $today) ?? [null, null];
        $this->assertSame(['2026-03-15', '2026-04-14'], [$start?->toDateString(), $end?->toDateString()]);

        [$start, $end] = SubscriptionBilling::periodFor($this->contract(ContractFeeType::Yearly, attributes: ['contract_no' => 'Y', 'ends_on' => '2026-12-31']), $today) ?? [null, null];
        $this->assertSame(['2026-01-15', '2026-12-31'], [$start?->toDateString(), $end?->toDateString()], 'Clamped to the contract end.');

        $this->assertNull(SubscriptionBilling::periodFor($this->contract(ContractFeeType::MonthlyFixed, attributes: ['contract_no' => 'F', 'starts_on' => '2026-04-01']), $today));
    }

    public function test_per_employee_invoice_with_vat_is_issued_once(): void
    {
        $this->contract(ContractFeeType::MonthlyPerEmployee, '150.00');
        $billing = app(SubscriptionBilling::class);

        $this->assertSame(1, $billing->issueInvoices());
        $this->assertSame(0, $billing->issueInvoices(), 'Only once per period.');

        $invoice = Invoice::sole();
        $this->assertSame([3, '450.00', '90.00', '540.00', Invoice::PENDING], [$invoice->quantity, $invoice->amount, $invoice->vat_amount, $invoice->total, $invoice->status]);
        $this->assertSame('HRD-2026-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT), $invoice->number);
        $this->assertSame(1, $this->owner->notifications()->where('data->title', 'like', 'Yeni fatura%')->count());

        // Next month: a new period, a new invoice.
        $this->travelTo(CarbonImmutable::parse('2026-04-16'));
        $this->assertSame(1, $billing->issueInvoices());
    }

    public function test_due_invoices_are_charged_from_the_saved_card_and_retried(): void
    {
        $this->contract(ContractFeeType::MonthlyFixed);
        $card = $this->card();
        $billing = app(SubscriptionBilling::class);
        $billing->issueInvoices();

        // Declined: failed, retried after 3 days, everyone told.
        $this->gateway->decline = 'Yetersiz bakiye';
        $this->assertSame(0, $billing->chargeDue());
        $invoice = Invoice::sole();
        $this->assertSame([Invoice::FAILED, 1, '2026-03-23'], [$invoice->status, $invoice->attempts, $invoice->next_attempt_on?->toDateString()]);
        $this->assertSame(1, $this->owner->notifications()->where('data->title', 'like', 'Abonelik ödemesi alınamadı%')->count());
        $this->assertSame(0, $billing->chargeDue(), 'Not before the retry date.');

        // Retry day: the card works now.
        $this->gateway->decline = null;
        $this->travelTo(CarbonImmutable::parse('2026-03-23 08:00'));
        $this->assertSame(1, $billing->chargeDue());
        $invoice->refresh();
        $this->assertSame([Invoice::PAID, 'card_auto', $card->id, 'fake-pay-2'], [$invoice->status, $invoice->payment_method, $invoice->firm_payment_card_id, $invoice->provider_payment_id]);
        $this->assertSame('1200.00', $this->gateway->charges[1]['amount'] ?? null, 'KDV dahil tutar çekilir.');

        // Automatic payment switched off: nothing is charged.
        $this->travelTo(CarbonImmutable::parse('2026-04-16'));
        $billing->issueInvoices();
        $this->firm->forceFill(['settings' => ['billing' => ['auto_pay' => false]]])->save();
        $this->assertSame(0, $billing->chargeDue());
    }

    public function test_card_is_saved_on_iyzicos_page_and_invoices_are_paid_there(): void
    {
        $this->contract(ContractFeeType::MonthlyFixed);
        $this->actingAs($this->owner);

        $this->get(route('billing.index'))->assertOk()->assertSee('Kart ekle')->assertSee('Ödeme bekliyor')->assertSee('1.200,00');

        // Add card: off to the hosted page; the verification charge is cancelled on return.
        Livewire::test('pages::panel.billing.index')->call('addCard')->assertRedirect('https://sandbox.iyzico.test/pay/tok-1');
        $this->gateway->saveCard = true;
        $this->post(route('billing.callback'), ['token' => 'tok-1'])->assertRedirect(route('billing.index', ['odeme' => 'tamam', 'islem' => BillingCheckout::sole()->id]));

        $card = FirmPaymentCard::sole();
        $this->assertSame(['VISA', '4242', true], [$card->card_association, $card->last_four, $card->is_default]);
        $this->assertSame(['fake-checkout-tok-1'], $this->gateway->cancelled);
        $this->assertStringNotContainsString('fake-token', (string) DB::table('firm_payment_cards')->value('card_token'), 'Tokens are encrypted.');
        $this->get(route('billing.index', ['odeme' => 'tamam', 'islem' => BillingCheckout::sole()->id]))->assertSee('Kartınız kaydedildi')->assertSee('Visa •••• 4242');

        // Pay the open invoice on the hosted page.
        $invoice = Invoice::sole();
        Livewire::test('pages::panel.billing.index')->call('pay', $invoice->id)->assertRedirect('https://sandbox.iyzico.test/pay/tok-2');
        $this->post(route('billing.callback'), ['token' => 'tok-2']);
        $this->assertSame([Invoice::PAID, 'card'], [$invoice->fresh()?->status, $invoice->fresh()?->payment_method]);

        // A token that was not issued here is rejected.
        $this->post(route('billing.callback'), ['token' => 'baska'])->assertNotFound();
    }

    public function test_access_and_unconfigured_provider(): void
    {
        $member = User::factory()->create(['firm_id' => $this->firm->id]);
        app(GrantAccess::class)->handle($member, $this->firm, [Permission::FirmView]);
        $this->actingAs($member);
        $this->get(route('billing.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertDontSee(route('billing.index'), false);

        $this->gateway->configured = false;
        $this->actingAs($this->owner);
        $this->get(route('billing.index'))->assertOk()->assertSee('Kartla ödeme henüz etkin değil')->assertDontSee('Kart ekle');
    }

    public function test_admin_records_transfer_voids_and_retries(): void
    {
        $this->contract(ContractFeeType::MonthlyFixed);
        app(SubscriptionBilling::class)->issueInvoices();
        $invoice = Invoice::sole();
        $this->onPortal(Portal::Admin);
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(route('admin.billing.index'))->assertOk()->assertSee($invoice->number)->assertSee('Oigo Grup');
        Livewire::test('pages::admin.billing.index')->call('charge', $invoice->id);
        $this->assertSame(Invoice::PENDING, $invoice->fresh()?->status, 'No card, nothing charged.');

        Livewire::test('pages::admin.billing.index')->call('markPaid', $invoice->id);
        $this->assertSame([Invoice::PAID, 'transfer'], [$invoice->fresh()?->status, $invoice->fresh()?->payment_method]);
        Livewire::test('pages::admin.billing.index')->call('void', $invoice->id);
        $this->assertSame(Invoice::PAID, $invoice->fresh()?->status, 'A paid invoice cannot be voided.');
    }
}

class FakePaymentGateway implements PaymentGateway
{
    public bool $configured = true;

    public ?string $decline = null;

    public bool $saveCard = false;

    /** @var list<array<string, string>> */
    public array $charges = [];

    /** @var list<string> */
    public array $cancelled = [];

    private int $checkouts = 0;

    public function configured(): bool
    {
        return $this->configured;
    }

    public function startCheckout(Firm $firm, User $user, string $conversationId, string $basketId, string $description,
        string $amount, string $currency, string $callbackUrl, ?string $cardUserKey, string $ip): CheckoutStart
    {
        $token = 'tok-'.(++$this->checkouts);

        return new CheckoutStart(true, $token, 'https://sandbox.iyzico.test/pay/'.$token);
    }

    public function completeCheckout(string $token, string $conversationId): GatewayResult
    {
        return $this->saveCard
            ? new GatewayResult(true, 'fake-checkout-'.$token, '1.00', cardUserKey: 'fake-user-key', cardToken: 'fake-token', lastFour: '4242', binNumber: '552879', cardAssociation: 'VISA')
            : new GatewayResult(true, 'fake-checkout-'.$token, '1200.00');
    }

    public function chargeCard(Firm $firm, FirmPaymentCard $card, string $conversationId, string $basketId, string $description,
        string $amount, string $currency, string $ip): GatewayResult
    {
        $this->charges[] = ['amount' => $amount, 'basket' => $basketId];

        return $this->decline !== null ? new GatewayResult(false, error: $this->decline) : new GatewayResult(true, 'fake-pay-'.count($this->charges), $amount);
    }

    public function cancel(string $paymentId, string $ip): bool
    {
        $this->cancelled[] = $paymentId;

        return true;
    }

    public function deleteCard(FirmPaymentCard $card): void {}
}
