<?php

namespace Tests\Feature\Admin;

use App\Actions\Access\GrantAccess;
use App\Enums\Permission;
use App\Enums\Portal;
use App\Models\Announcement;
use App\Models\Firm;
use App\Models\User;
use App\Notifications\DeliverAnnouncements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    private function announce(array $attributes = [], array $firms = []): Announcement
    {
        $announcement = Announcement::create([
            'title' => 'Duyuru', 'body' => 'Metin', 'level' => 'info', 'audience' => 'all', 'starts_at' => now()->subHour(), ...$attributes,
        ]);
        $announcement->firms()->sync($firms);

        return $announcement;
    }

    private function client(Firm $firm): User
    {
        $user = User::factory()->create();
        app(GrantAccess::class)->handle($user, $firm, Permission::firmOwnerDefaults());

        return $user->fresh() ?? $user;
    }

    public function test_audience_timing_and_dismissal(): void
    {
        $firm = Firm::factory()->create();
        $other = Firm::factory()->create();
        $client = $this->client($firm);
        $specialist = User::factory()->payrollSpecialist()->create();

        $this->announce(['title' => 'Herkese']);
        $this->announce(['title' => 'Müşterilere', 'audience' => 'clients']);
        $this->announce(['title' => 'HRD ekibine', 'audience' => 'staff']);
        $this->announce(['title' => 'Bu firmaya', 'audience' => 'firms'], [$firm->id]);
        $this->announce(['title' => 'Başka firmaya', 'audience' => 'firms'], [$other->id]);
        $this->announce(['title' => 'Planlı', 'starts_at' => now()->addDay()]);
        $this->announce(['title' => 'Biten', 'ends_at' => now()->subMinute()]);
        $critical = $this->announce(['title' => 'Kritik', 'level' => 'critical']);

        $titles = fn (User $user) => Announcement::query()->for($user)->pluck('title')->sort()->values()->all();

        $this->assertSame(['Bu firmaya', 'Herkese', 'Kritik', 'Müşterilere'], $titles($client));
        $this->assertSame(['HRD ekibine', 'Herkese', 'Kritik'], $titles($specialist));
        $this->assertSame('Kritik', Announcement::query()->for($client)->first()?->title, 'Critical first.');

        $this->actingAs($client);
        $this->get(route('dashboard'))->assertOk()->assertSee('Bu firmaya')->assertDontSee('Başka firmaya');

        // "Üst şeritte gösterme" on the Duyurular page: gone from the strip, still listed; critical ones stay.
        $herkese = Announcement::where('title', 'Herkese')->sole();
        Livewire::test('pages::panel.announcements.index')
            ->call('dismiss', $herkese->id)
            ->call('dismiss', $critical->id)
            ->assertSee('Herkese');
        Livewire::test('announcements')->assertDontSee('Herkese')->assertSee('Kritik');
    }

    public function test_duyurular_page_categories_reading_and_links(): void
    {
        $firm = Firm::factory()->create();
        $client = $this->client($firm);
        $this->announce(['title' => 'Planlı bakım', 'category' => 'Bakım', 'pinned' => true, 'starts_at' => now()->subDays(3)]);
        $mevzuat = $this->announce(['title' => 'Muhtasar son günü', 'category' => 'Mevzuat', 'link_label' => 'Personele git', 'link_url' => '/personel']);
        $this->announce(['title' => 'Eski duyuru', 'category' => 'Sistem', 'ends_at' => now()->subDay(), 'starts_at' => now()->subWeek()]);
        $this->announce(['title' => 'Başka firmaya', 'audience' => 'firms'], [Firm::factory()->create()->id]);
        $this->actingAs($client);

        $this->get(route('dashboard'))->assertSee('duyurular?duyuru='.$mevzuat->id, false);

        Livewire::test('pages::panel.announcements.index')
            ->assertSeeInOrder(['Planlı bakım', 'Muhtasar son günü'])
            ->assertDontSee('Eski duyuru')->assertDontSee('Başka firmaya')
            ->assertSee('Tümünü okundu say (2)')
            ->assertSee('Personele git')
            ->set('category', 'Mevzuat')->assertDontSee('Planlı bakım')
            ->set('view', 'arsiv')->set('category', '')->assertSee('Eski duyuru');

        // Opened from the strip or a notification: that one is read.
        Livewire::withQueryParams(['duyuru' => $mevzuat->id])->test('pages::panel.announcements.index')->assertSee('Tümünü okundu say (1)');
        $this->assertSame(1, Announcement::query()->live()->forAudience($client)->unreadBy($client)->count());

        Livewire::test('pages::panel.announcements.index')->call('readAll')->assertDontSee('Tümünü okundu say');
        $this->assertSame(0, Announcement::query()->live()->forAudience($client)->unreadBy($client)->count());
    }

    public function test_notification_on_publish_reaches_the_audience_once(): void
    {
        $firm = Firm::factory()->create();
        $client = $this->client($firm);
        $outsider = $this->client(Firm::factory()->create());
        $this->onPortal(Portal::Admin);
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test('pages::admin.announcements.index')
            ->call('create')
            ->set('title', 'Yeni Excel şablonu')->set('body', 'Personel şablonu güncellendi.')
            ->set('category', 'Yeni Özellik')
            ->set('audience', 'firms')->set('firmIds', [(string) $firm->id])
            ->set('linkLabel', 'Kötü')->set('linkUrl', 'javascript:alert(1)')
            ->call('save')->assertHasErrors('linkUrl')
            ->set('linkUrl', '/aktarim/personel')
            ->call('save')->assertHasNoErrors();

        $announcement = Announcement::sole();
        $this->assertNotNull($announcement->notified_at);
        $this->assertSame(1, $client->notifications()->where('data->title', 'like', '%Yeni Excel şablonu')->count());
        $this->assertSame(0, $outsider->notifications()->count());

        app(DeliverAnnouncements::class)->run();
        $this->assertSame(1, $client->notifications()->count(), 'Announced once.');

        // Planned: announced when it starts.
        $planned = $this->announce(['title' => 'Planlı', 'notify' => true, 'starts_at' => now()->addHour()]);
        $this->assertSame(0, app(DeliverAnnouncements::class)->run());
        $this->travel(2)->hours();
        $this->assertSame(1, app(DeliverAnnouncements::class)->run());
        $this->assertNotNull($planned->fresh()?->notified_at);
    }

    public function test_announcements_are_panel_only(): void
    {
        $this->announce(['title' => 'Panel duyurusu']);
        $this->onPortal(Portal::Admin);
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('Panel duyurusu');
    }

    public function test_admin_creates_targets_and_ends(): void
    {
        $this->onPortal(Portal::Admin);
        $this->actingAs(User::factory()->superAdmin()->create());
        $firm = Firm::factory()->create();

        $this->get(route('admin.announcements.index'))->assertOk()->assertSee('role="tablist"', false);

        Livewire::test('pages::admin.announcements.index')
            ->call('create')
            ->set('title', 'Bakım çalışması')
            ->set('body', '**Cumartesi** 22:00-23:00 arası erişim olmayacak.')
            ->set('audience', 'firms')
            ->call('save')
            ->assertHasErrors('firmIds')
            ->set('firmIds', [(string) $firm->id])
            ->call('save')
            ->assertHasNoErrors();

        $announcement = Announcement::with('firms')->sole();
        $this->assertSame([$firm->id], $announcement->firms->pluck('id')->all());
        $this->assertTrue($announcement->isLive());

        Livewire::test('pages::admin.announcements.index')->call('end', $announcement->id);
        $this->assertFalse($announcement->fresh()?->isLive());
    }
}
