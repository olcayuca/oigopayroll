<?php

namespace Tests\Feature\Panel;

use App\Actions\Access\GrantAccess;
use App\Enums\Permission;
use App\Models\Firm;
use App\Models\User;
use App\Models\UserNote;
use App\Models\UserReminder;
use App\Notifications\DeliverDueReminders;
use App\Notifications\PersonalReminder;
use Database\Seeders\PayrollCodeSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Assistant shortcuts: personal notes, reminders (to the notification bell), calculator popup.
 */
class AssistantToolsTest extends TestCase
{
    use RefreshDatabase;

    private Firm $firm;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ReferenceDataSeeder::class, PayrollCodeSeeder::class]);
        $this->firm = Firm::factory()->create(['name' => 'Oigo Grup']);
        $this->user = User::factory()->create(['firm_id' => $this->firm->id]);
        app(GrantAccess::class)->handle($this->user, $this->firm, Permission::firmOwnerDefaults());
        $this->actingAs($this->user);
    }

    public function test_popups_are_on_every_panel_page(): void
    {
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('data-test="assistant-notes"', false)
            ->assertSee('data-test="assistant-reminders"', false)
            ->assertSee('data-test="assistant-calculator"', false);
    }

    public function test_notes_are_personal_encrypted_and_editable(): void
    {
        Livewire::test('assistant-tools')
            ->set('noteBody', 'Merkez işyerinin SGK şifresi müşteriden istenecek')
            ->call('saveNote')
            ->assertHasNoErrors()
            ->assertSee('Merkez işyerinin SGK şifresi müşteriden istenecek');

        $note = UserNote::sole();
        $this->assertSame([$this->user->id, $this->firm->id], [$note->user_id, $note->firm_id]);
        $this->assertStringNotContainsString('Merkez', (string) DB::table('user_notes')->value('body'), 'Stored encrypted.');

        Livewire::test('assistant-tools')
            ->call('editNote', $note->id)->assertSet('noteBody', $note->body)
            ->set('noteBody', 'Güncellendi')->call('saveNote')
            ->assertSee('Güncellendi');
        $this->assertSame(1, UserNote::count());

        // Another user neither sees nor changes it.
        $colleague = User::factory()->create(['firm_id' => $this->firm->id]);
        app(GrantAccess::class)->handle($colleague, $this->firm, Permission::firmOwnerDefaults());
        $this->actingAs($colleague);
        Livewire::test('assistant-tools')->assertDontSee('Güncellendi')->call('deleteNote', $note->id)->assertNotFound();

        $this->actingAs($this->user);
        Livewire::test('assistant-tools')->call('deleteNote', $note->id);
        $this->assertSame(0, UserNote::count());
    }

    public function test_reminder_is_delivered_once_to_the_notification_bell(): void
    {
        $this->travelTo(now()->setTime(10, 0));

        Livewire::test('assistant-tools')
            ->set('reminderTitle', 'Muhtasar beyanname kontrolü')
            ->set('reminderAt', now()->subMinute()->format('Y-m-d\TH:i'))
            ->call('saveReminder')
            ->assertHasErrors(['reminderAt'])
            ->call('preset', '1h')
            ->assertSet('reminderAt', now()->addHour()->format('Y-m-d\TH:i'))
            ->call('saveReminder')
            ->assertHasNoErrors()
            ->assertSee('Muhtasar beyanname kontrolü');

        $this->assertSame(0, app(DeliverDueReminders::class)->run(), 'Not due yet.');

        $this->travel(61)->minutes();
        Livewire::test('notification-bell', ['variant' => 'topbar'])->assertSee('Hatırlatma: Muhtasar beyanname kontrolü');

        $this->assertSame(0, app(DeliverDueReminders::class)->run(), 'Announced once.');
        $this->assertSame(1, $this->user->notifications()->where('type', PersonalReminder::class)->count());
        $this->assertNotNull(UserReminder::sole()->notified_at);
    }

    public function test_reminders_of_other_users_cannot_be_deleted(): void
    {
        $other = User::factory()->create(['firm_id' => $this->firm->id]);
        $reminder = UserReminder::create(['user_id' => $other->id, 'firm_id' => $this->firm->id, 'title' => 'X', 'remind_at' => now()->addDay()]);

        Livewire::test('assistant-tools')->call('deleteReminder', $reminder->id)->assertNotFound();
        $this->assertModelExists($reminder);
    }
}
