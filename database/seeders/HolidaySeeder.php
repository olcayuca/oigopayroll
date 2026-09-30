<?php

namespace Database\Seeders;

use App\Models\Holiday;
use App\Payroll\Calendar\WorkCalendar;
use Illuminate\Database\Seeder;

/**
 * 2026–2027 official holidays. Religious holiday dates per Diyanet (compiled 30.09.2026).
 * Arife days are half days (from 13:00).
 */
class HolidaySeeder extends Seeder
{
    /**
     * @var list<array{0: string, 1: string, 2: bool}> date, name, half day
     */
    public const RELIGIOUS = [
        ['2026-03-19', 'Ramazan Bayramı arifesi', true],
        ['2026-03-20', 'Ramazan Bayramı 1. gün', false],
        ['2026-03-21', 'Ramazan Bayramı 2. gün', false],
        ['2026-03-22', 'Ramazan Bayramı 3. gün', false],
        ['2026-05-26', 'Kurban Bayramı arifesi', true],
        ['2026-05-27', 'Kurban Bayramı 1. gün', false],
        ['2026-05-28', 'Kurban Bayramı 2. gün', false],
        ['2026-05-29', 'Kurban Bayramı 3. gün', false],
        ['2026-05-30', 'Kurban Bayramı 4. gün', false],
        ['2027-03-08', 'Ramazan Bayramı arifesi', true],
        ['2027-03-09', 'Ramazan Bayramı 1. gün', false],
        ['2027-03-10', 'Ramazan Bayramı 2. gün', false],
        ['2027-03-11', 'Ramazan Bayramı 3. gün', false],
        ['2027-05-15', 'Kurban Bayramı arifesi', true],
        ['2027-05-16', 'Kurban Bayramı 1. gün', false],
        ['2027-05-17', 'Kurban Bayramı 2. gün', false],
        ['2027-05-18', 'Kurban Bayramı 3. gün', false],
        ['2027-05-19', 'Kurban Bayramı 4. gün', false],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $calendar = app(WorkCalendar::class);
        $calendar->generateFixed(2026);
        $calendar->generateFixed(2027);

        foreach (self::RELIGIOUS as [$date, $name, $halfDay]) {
            if (! Holiday::whereDate('date', $date)->where('name', $name)->exists()) {
                Holiday::create(['date' => $date, 'name' => $name, 'type' => 'religious', 'is_half_day' => $halfDay]);
            }
        }
    }
}
