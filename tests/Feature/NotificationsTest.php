<?php

namespace Tests\Feature;

use App\Actions\Access\GrantAccess;
use App\Actions\Firms\AssignSpecialist;
use App\Actions\Firms\ManageContracts;
use App\Actions\Firms\ManageFirmDocuments;
use App\Actions\Firms\RegisterFirm;
use App\Actions\Firms\ReviewFirm;
use App\Enums\FirmStatus;
use App\Enums\Permission;
use App\Enums\Portal;
use App\Kvkk\DataRequests;
use App\Models\Firm;
use App\Models\FirmDocument;
use App\Models\User;
use App\Notifications\ContractEnding;
use App\Notifications\DocumentExpiring;
use App\Notifications\FirmAwaitingApproval;
use App\Notifications\FirmReviewed;
use App\Notifications\HrdNotification;
use App\Notifications\KvkkRequestAnswered;
use App\Notifications\SendReminders;
use App\Notifications\SpecialistAssignedToFirm;
use App\Support\TurkishIdentifiers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(Firm $firm): User
    {
        $user = User::factory()->create();
        app(GrantAccess::class)->handle($user, $firm, Permission::firmOwnerDefaults());

        return $user->fresh() ?? $user;
    }

    public function test_firm_registration_and_review_notify_the_right_people(): void
    {
        Notification::fake();
        $admin = User::factory()->superAdmin()->create();
        $client = User::factory()->create();

        $firm = app(RegisterFirm::class)->handle($client, ['name' => 'Yeni Firma', 'tax_number' => TurkishIdentifiers::makeVkn('123456789')]);
        Notification::assertSentTo($admin, FirmAwaitingApproval::class);
        Notification::assertNotSentTo($client, FirmAwaitingApproval::class);

        app(ReviewFirm::class)->approve($firm, $admin);
        Notification::assertSentTo($client, FirmReviewed::class, fn (FirmReviewed $n) => $n->approved);

        // Other firms' users hear nothing.
        $stranger = $this->owner(Firm::factory()->create());
        Notification::assertNotSentTo($stranger, FirmReviewed::class);
    }

    public function test_kvkk_answer_and_specialist_assignment(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $request = app(DataRequests::class)->submit($user, ['type' => 'bilgi', 'message' => 'Hangi verilerim işleniyor?']);

        app(DataRequests::class)->update($request, ['status' => 'inceleniyor'], User::factory()->superAdmin()->create());
        Notification::assertNothingSentTo($user);

        app(DataRequests::class)->update($request->fresh() ?? $request, ['status' => 'tamamlandi', 'response' => 'Yanıt'], User::factory()->superAdmin()->create());
        Notification::assertSentTo($user, KvkkRequestAnswered::class);

        $specialist = User::factory()->payrollSpecialist()->create();
        app(AssignSpecialist::class)->handle(Firm::factory()->create(), $specialist);
        Notification::assertSentTo($specialist, SpecialistAssignedToFirm::class);
    }

    public function test_reminders_are_sent_once_per_state(): void
    {
        Storage::fake('local');
        $firm = Firm::factory()->create();
        $owner = $this->owner($firm);
        $specialist = User::factory()->payrollSpecialist()->create();
        app(AssignSpecialist::class)->handle($firm, $specialist);
        $admin = User::factory()->superAdmin()->create();

        $document = app(ManageFirmDocuments::class)->store($firm, UploadedFile::fake()->create('l.pdf', 10, 'application/pdf'), [
            'type' => 'vergi_levhasi', 'title' => 'Levha', 'valid_until' => today()->addDays(10)->toDateString(),
        ]);
        app(ManageContracts::class)->save(null, [
            'firm_id' => $firm->id, 'contract_no' => 'S-1', 'title' => 'Sözleşme', 'starts_on' => today()->subYear()->toDateString(),
            'ends_on' => today()->addDays(20)->toDateString(), 'notice_days' => 30, 'fee_type' => 'yillik', 'currency' => 'TRY',
        ]);

        Notification::fake();
        $reminders = app(SendReminders::class);

        $this->assertSame(['documents' => 1, 'contracts' => 1], $reminders->run());
        Notification::assertSentTo([$owner, $specialist], DocumentExpiring::class, fn (DocumentExpiring $n) => ! $n->expired);
        Notification::assertSentTo($admin, ContractEnding::class);

        $this->assertSame(['documents' => 0, 'contracts' => 0], $reminders->run(), 'No repeats.');

        $this->travel(11)->days();
        $this->assertSame(1, $reminders->run()['documents'], 'Expired is a new state.');
        Notification::assertSentTo($owner, DocumentExpiring::class, fn (DocumentExpiring $n) => $n->expired);

        // A new date re-arms the reminder.
        app(ManageFirmDocuments::class)->update($document->fresh() ?? $document, [
            'type' => 'vergi_levhasi', 'title' => 'Levha', 'valid_until' => today()->addDays(5)->toDateString(),
        ]);
        $this->assertNull(FirmDocument::sole()->expiry_notice);
    }

    public function test_bell_page_and_open_marks_read(): void
    {
        $firm = Firm::factory()->create(['status' => FirmStatus::Pending]);
        $owner = $this->owner($firm);
        app(ReviewFirm::class)->approve($firm, User::factory()->superAdmin()->create());
        $this->assertFalse(HrdNotification::mailEnabled(), 'Tests use the array mailer: database only.');

        $this->actingAs($owner);
        $this->get(route('dashboard'))->assertOk()->assertSee('Bildirimler');
        Livewire::test('notification-bell')->assertSee('onaylandı');

        $this->get(route('notifications.index'))->assertOk()->assertSee('role="tablist"', false)->assertSee('onaylandı');

        $notification = $owner->notifications()->sole();
        $this->get(route('notifications.open', $notification->id))->assertRedirect(Portal::Panel->url('/'));
        $this->assertNotNull($notification->fresh()?->read_at);

        // Someone else's notification is not reachable.
        $this->actingAs(User::factory()->create())->get(route('notifications.open', $notification->id))->assertNotFound();
    }

    public function test_foreign_urls_are_not_followed(): void
    {
        $user = User::factory()->create();
        $user->notifications()->create([
            'id' => (string) str()->uuid(), 'type' => 'test', 'data' => ['title' => 'x', 'url' => 'https://evil.example.com/'],
        ]);

        $this->actingAs($user)->get(route('notifications.open', $user->notifications()->sole()->id))
            ->assertRedirect(route('notifications.index'));
    }
}
