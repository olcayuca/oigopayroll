<?php

namespace App\Payroll\Calendar;

use App\Enums\AuditEvent;
use App\Models\Holiday;
use App\Support\Audit;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Official holidays for payroll (holiday pay, overtime, missing days).
 */
class WorkCalendar
{
    /**
     * Fixed national holidays, 2429 sayılı Kanun: [month, day, name, half day].
     *
     * @var list<array{0: int, 1: int, 2: string, 3: bool}>
     */
    public const FIXED = [
        [1, 1, 'Yılbaşı', false],
        [4, 23, 'Ulusal Egemenlik ve Çocuk Bayramı', false],
        [5, 1, 'Emek ve Dayanışma Günü', false],
        [5, 19, "Atatürk'ü Anma, Gençlik ve Spor Bayramı", false],
        [7, 15, 'Demokrasi ve Millî Birlik Günü', false],
        [8, 30, 'Zafer Bayramı', false],
        [10, 28, 'Cumhuriyet Bayramı arifesi', true],
        [10, 29, 'Cumhuriyet Bayramı', false],
    ];

    /**
     * Holidays of a year, ordered by date.
     *
     * @return Collection<int, Holiday>
     */
    public function year(int $year): Collection
    {
        return Holiday::whereYear('date', $year)->orderBy('date')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Holiday>
     */
    public function between(DateTimeInterface|string $from, DateTimeInterface|string $to): Collection
    {
        return Holiday::query()
            ->whereDate('date', '>=', Carbon::parse($from)->toDateString())
            ->whereDate('date', '<=', Carbon::parse($to)->toDateString())
            ->orderBy('date')
            ->get();
    }

    /**
     * Full-day holiday on the date (half days count only when $includeHalfDays).
     */
    public function isHoliday(DateTimeInterface|string $date, bool $includeHalfDays = false): bool
    {
        return Holiday::query()
            ->whereDate('date', Carbon::parse($date)->toDateString())
            ->when(! $includeHalfDays, fn ($query) => $query->where('is_half_day', false))
            ->exists();
    }

    /**
     * Whether religious holidays have been entered for the year (they move every year).
     */
    public function hasReligiousHolidays(int $year): bool
    {
        return Holiday::whereYear('date', $year)->where('type', 'religious')->exists();
    }

    /**
     * Create the fixed national holidays of a year (existing ones are kept). Returns how many were added.
     */
    public function generateFixed(int $year): int
    {
        $added = 0;

        foreach (self::FIXED as [$month, $day, $name, $halfDay]) {
            $date = Carbon::create($year, $month, $day)->toDateString();

            if (! Holiday::whereDate('date', $date)->where('name', $name)->exists()) {
                Holiday::create(['date' => $date, 'name' => $name, 'type' => 'national', 'is_half_day' => $halfDay]);
                $added++;
            }
        }

        if ($added > 0) {
            Audit::log(AuditEvent::SystemSettingsChanged, "{$year} yılı sabit resmi tatilleri oluşturuldu ({$added})");
        }

        return $added;
    }
}
