<?php

namespace Tests\Feature;

use App\Actions\Access\GrantAccess;
use App\Enums\Permission;
use App\Enums\Portal;
use App\Enums\TicketStatus;
use App\Models\Firm;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketUpdate;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Destek: firm users open tickets in the panel; HRD answers in the admin portal or (the firm's specialist) in the panel.
 */
class SupportTicketsTest extends TestCase
{
    use RefreshDatabase;

    private Firm $firm;

    private User $owner;

    private User $member;

    private User $specialist;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceDataSeeder::class);
        Storage::fake('local');
        $this->firm = Firm::factory()->create(['name' => 'Oigo Grup']);
        $this->owner = User::factory()->create(['name' => 'Selin Korkmaz', 'firm_id' => $this->firm->id]);
        app(GrantAccess::class)->handle($this->owner, $this->firm, Permission::firmOwnerDefaults());
        $this->member = User::factory()->create(['name' => 'Ali Demir', 'firm_id' => $this->firm->id]);
        app(GrantAccess::class)->handle($this->member, $this->firm, [Permission::FirmView]);
        $this->specialist = User::factory()->payrollSpecialist()->create(['name' => 'Deniz Özkan']);
        app(GrantAccess::class)->handle($this->specialist, $this->firm, [Permission::FirmView]);
        $this->firm->forceFill(['specialist_id' => $this->specialist->id])->save();
        $this->admin = User::factory()->superAdmin()->create(['name' => 'HRD Admin']);
    }

    private function openTicket(User $user, string $subject = 'Personel aktarımında hata'): SupportTicket
    {
        $this->actingAs($user);

        Livewire::test('pages::panel.support.index')
            ->set('subject', $subject)
            ->set('category', 'Hata bildirimi')
            ->set('module', 'Excel aktarımı')
            ->set('priority', 'high')
            ->set('body', 'Kurulum dosyasını yüklediğimde 3. satırda hata alıyorum.')
            ->set('attachment', UploadedFile::fake()->create('ekran.pdf', 120, 'application/pdf'))
            ->call('send')
            ->assertHasNoErrors();

        return SupportTicket::latest('id')->firstOrFail();
    }

    public function test_opening_a_ticket_notifies_hrd_the_mailbox_and_the_opener(): void
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        Setting::putMany(['support.email' => 'destek@hrd.test']);

        $ticket = $this->openTicket($this->member);

        $this->assertSame([TicketStatus::Open, $this->member->id, 1], [$ticket->status, $ticket->user_id, $ticket->messages()->count()]);
        Storage::disk('local')->assertExists((string) $ticket->messages()->first()?->attachment_path);

        Notification::assertSentTo([$this->admin, $this->specialist], SupportTicketUpdate::class,
            fn (SupportTicketUpdate $notification, array $channels) => str_contains($notification->title(), 'Yeni destek talebi') && in_array('mail', $channels, true));
        Notification::assertSentOnDemand(SupportTicketUpdate::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'destek@hrd.test');
        Notification::assertSentTo($this->member, SupportTicketUpdate::class, fn (SupportTicketUpdate $notification) => str_contains($notification->title(), 'Talebiniz alındı'));
        Notification::assertNotSentTo($this->owner, SupportTicketUpdate::class);
    }

    public function test_conversation_goes_both_ways_between_panel_and_admin(): void
    {
        $ticket = $this->openTicket($this->member);

        // The firm's specialist answers in the panel.
        $this->actingAs($this->specialist);
        Livewire::test('pages::support.show', ['ticket' => $ticket])
            ->assertSee('Kurulum dosyasını yüklediğimde')
            ->set('body', 'Hangi sütunda hata alıyorsunuz?')
            ->call('reply')
            ->assertHasNoErrors();

        $ticket->refresh();
        $this->assertSame([TicketStatus::AwaitingCustomer, $this->specialist->id], [$ticket->status, $ticket->assigned_to]);
        $this->assertSame(1, $this->member->notifications()->where('data->title', 'like', 'Talebiniz yanıtlandı%')->count());

        // The customer answers: back to HRD ("Açık"), the assigned specialist is told.
        $this->actingAs($this->member);
        $this->get(route('support.index'))->assertOk()->assertSee('Personel aktarımında hata')->assertSee('Yanıt bekleniyor');
        Livewire::test('pages::support.show', ['ticket' => $ticket])->set('body', 'SGK Meslek Kodu sütununda.')->call('reply');
        $this->assertSame(TicketStatus::Open, $ticket->fresh()?->status);
        $this->assertSame(1, $this->specialist->notifications()->where('data->title', 'like', 'Müşteri yanıtı%')->count());

        // Admin portal: listed as waiting, answered and resolved there.
        $this->onPortal(Portal::Admin);
        $this->actingAs($this->admin);
        $this->get(route('admin.support.index'))->assertOk()->assertSee('Personel aktarımında hata')->assertSee('Oigo Grup');
        $this->get(route('admin.support.show', $ticket))->assertOk()->assertSee('SGK Meslek Kodu sütununda.');
        Livewire::test('pages::support.show', ['ticket' => $ticket])
            ->set('body', 'Meslek kodu listesine göre düzeltildi.')
            ->set('replyStatus', 'resolved')
            ->call('reply');

        $this->assertSame(TicketStatus::Resolved, $ticket->fresh()?->status);
        $this->assertSame(4, $ticket->messages()->count());
        $this->assertTrue($ticket->messages()->get()->last()?->from_staff);
    }

    public function test_visibility_closing_and_attachments(): void
    {
        $ticket = $this->openTicket($this->member);
        $message = $ticket->messages()->firstOrFail();

        // Owners (firm managers) see every ticket of the firm; plain members only their own.
        $colleague = User::factory()->create(['firm_id' => $this->firm->id]);
        app(GrantAccess::class)->handle($colleague, $this->firm, [Permission::FirmView]);
        $this->actingAs($colleague);
        $this->get(route('support.index'))->assertOk()->assertDontSee('Personel aktarımında hata');
        $this->get(route('support.show', $ticket))->assertForbidden();
        $this->get(route('support.attachment', $message))->assertForbidden();

        $this->actingAs($this->owner);
        $this->get(route('support.index'))->assertOk()->assertSee('Personel aktarımında hata');
        $this->get(route('support.attachment', $message))->assertOk()->assertDownload('ekran.pdf');
        Livewire::test('pages::support.show', ['ticket' => $ticket])->assertDontSee('data-test="ticket-manage"', false)
            ->call('setStatus', 'resolved')->assertForbidden();

        $outsider = User::factory()->create();
        app(GrantAccess::class)->handle($outsider, Firm::factory()->create(), Permission::firmOwnerDefaults());
        $this->actingAs($outsider);
        $this->get(route('support.show', $ticket))->assertForbidden();

        // The opener closes the ticket; no more messages.
        $this->actingAs($this->member);
        Livewire::test('pages::support.show', ['ticket' => $ticket])->call('close');
        $this->assertSame(TicketStatus::Closed, $ticket->fresh()?->status);
        Livewire::test('pages::support.show', ['ticket' => $ticket->fresh()])->assertSee('Talep kapalı')->call('reply')->assertForbidden();
        $this->get(route('support.index', ['sekme' => 'gecmis']))->assertSee('Personel aktarımında hata');
    }

    public function test_validation(): void
    {
        $this->actingAs($this->member);

        Livewire::test('pages::panel.support.index')
            ->set('subject', '')
            ->set('body', '')
            ->set('category', 'Uydurma')
            ->call('send')
            ->assertHasErrors(['subject', 'body', 'category']);

        $this->assertSame(0, SupportTicket::count());
    }
}
