<?php

namespace Database\Seeders;

use App\Models\LegalParameter;
use App\Payroll\Parameters\ParameterCatalog;
use Illuminate\Database\Seeder;

/**
 * 2026 legal payroll parameters. Only missing entries are created, so values edited in
 * Admin → Yasal Parametreler are never overwritten.
 *
 * Compiled on 30.09.2026 from the sources noted on each entry; the admin should verify them.
 */
class LegalParameterSeeder extends Seeder
{
    private const MIN_WAGE = 'Asgari Ücret Tespit Komisyonu kararı, 2026';

    private const SGK_2026_2 = 'SGK Genelgesi 2026/2 (07.01.2026)';

    private const LAW_7566 = '5510 s.K. / 7566 s.K. (RG 19.12.2025)';

    private const GVT_332 = 'Gelir Vergisi Genel Tebliği Seri No: 332';

    /**
     * @return list<array{0: string, 1: string, 2: mixed, 3: string}> key, effective_from, value, source
     */
    public static function values(): array
    {
        return [
            ['min_wage_gross', '2026-01-01', '33030.00', self::MIN_WAGE],

            ['sgk_floor_monthly', '2026-01-01', '33030.00', self::SGK_2026_2],
            ['sgk_ceiling_monthly', '2026-01-01', '297270.00', self::SGK_2026_2.'; üst sınır alt sınırın 9 katı'],
            ['sgk_employee_pension', '2026-01-01', '9.00', self::LAW_7566],
            ['sgk_employer_pension', '2026-01-01', '12.00', self::LAW_7566.'; işveren payı %11 → %12'],
            ['sgk_employee_health', '2026-01-01', '5.00', '5510 s.K. md. 81'],
            ['sgk_employer_health', '2026-01-01', '7.50', '5510 s.K. md. 81'],
            ['sgk_employer_short_term', '2026-01-01', '2.25', '5510 s.K. md. 81'],
            ['unemployment_employee', '2026-01-01', '1.00', '4447 s.K. md. 49'],
            ['unemployment_employer', '2026-01-01', '2.00', '4447 s.K. md. 49'],
            ['treasury_discount_manufacturing', '2026-01-01', '5.00', self::LAW_7566.'; imalat sektöründe 31.12.2026\'ya kadar 5 puan'],
            ['treasury_discount_other', '2026-01-01', '2.00', self::LAW_7566.'; imalat dışı sektörlerde 2 puan'],

            ['income_tax_brackets', '2026-01-01', [
                ['up_to' => '190000', 'rate' => '15'],
                ['up_to' => '400000', 'rate' => '20'],
                ['up_to' => '1500000', 'rate' => '27'],
                ['up_to' => '5300000', 'rate' => '35'],
                ['up_to' => null, 'rate' => '40'],
            ], self::GVT_332],
            ['stamp_tax_rate', '2026-01-01', '0.759', '488 s. Damga Vergisi K. (1) sayılı tablo; binde 7,59'],
            ['disability_deduction_1', '2026-01-01', '12000.00', self::GVT_332],
            ['disability_deduction_2', '2026-01-01', '7000.00', self::GVT_332],
            ['disability_deduction_3', '2026-01-01', '3000.00', self::GVT_332],
            ['meal_exemption_daily', '2026-01-01', '300.00', self::GVT_332],
            ['transport_exemption_daily', '2026-01-01', '158.00', self::GVT_332],

            ['severance_ceiling', '2026-01-01', '64948.77', 'Hazine ve Maliye Bakanlığı genelgesi, 2026 I. dönem'],
            ['severance_ceiling', '2026-07-01', '73729.87', 'Hazine ve Maliye Bakanlığı genelgesi Sıra No: 5 (02.07.2026)'],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::values() as [$key, $from, $value, $source]) {
            if (LegalParameter::where('key', $key)->whereDate('effective_from', $from)->exists()) {
                continue;
            }

            LegalParameter::create([
                'key' => $key,
                'effective_from' => $from,
                'value' => ParameterCatalog::find($key)?->normalize($value),
                'source' => $source,
            ]);
        }
    }
}
