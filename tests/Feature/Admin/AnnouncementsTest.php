<?php

namespace Tests\Feature\Admin;

use App\Actions\Access\GrantAccess;
use App\Enums\Permission;
use App\Enums\Portal;
use App\Models\Announcement;
use App\Models\Firm;
use App\Models\User;
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

        $herkese = Announcement::where('title', 'Herkese')->sole();
        Livewire::test('announcements')
            ->call('dismiss', $herkese->id)
            ->call('dismiss', $critical->id)
            ->assertDontSee('Herkese')
            ->assertSee('Kritik');
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
