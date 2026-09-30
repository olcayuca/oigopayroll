<?php

namespace Tests\Feature\Admin;

use App\Enums\Portal;
use App\Models\Holiday;
use App\Models\User;
use App\Payroll\Calendar\WorkCalendar;
use Database\Seeders\HolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HolidaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_calendar_and_lookups(): void
    {
        $this->seed(HolidaySeeder::class);
        $this->seed(HolidaySeeder::class); // idempotent
        $calendar = new WorkCalendar;

        $this->assertSame(17, $calendar->year(2026)->count()); // 8 fixed + 9 religious
        $this->assertTrue($calendar->isHoliday('2026-05-27'));
        $this->assertFalse($calendar->isHoliday('2026-05-26'), 'Arife is only a half day.');
        $this->assertTrue($calendar->isHoliday('2026-05-26', includeHalfDays: true));
        $this->assertFalse($calendar->isHoliday('2026-06-01'));
        $this->assertSame(2, Holiday::whereDate('date', '2027-05-19')->count(), '19 May 2027 carries two holidays.');
        $this->assertSame(4, $calendar->between('2026-03-19', '2026-03-22')->count());
    }

    public function test_admin_page_generates_fixed_holidays_and_manages_entries(): void
    {
        $this->onPortal(Portal::Admin);
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(route('admin.holidays.index', ['yil' => 2028]))->assertOk()
            ->assertSee('role="tablist"', false)
            ->assertSee('2028 için dini bayramlar girilmemiş');

        Livewire::test('pages::admin.holidays.index', ['tab' => '2028'])
            ->set('tab', '2028')
            ->call('generateFixed')
            ->call('generateFixed') // no duplicates
            ->call('create')
            ->set('date', '2028-02-26')
            ->set('name', 'Ramazan Bayramı 1. gün')
            ->call('save')
            ->assertHasNoErrors()
            ->call('create')
            ->set('date', '2028-02-26')
            ->set('name', 'Ramazan Bayramı 1. gün')
            ->call('save')
            ->assertHasErrors('name');

        $this->assertSame(9, (new WorkCalendar)->year(2028)->count());
        $this->assertTrue((new WorkCalendar)->hasReligiousHolidays(2028));
    }

    public function test_non_admins_cannot_manage_the_calendar(): void
    {
        $this->actingAs(User::factory()->payrollSpecialist()->create());

        Livewire::test('pages::admin.holidays.index')->assertForbidden();
    }
}
