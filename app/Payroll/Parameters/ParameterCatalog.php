<?php

namespace App\Payroll\Parameters;

/**
 * Every legal parameter the payroll engine reads. Values live in the legal_parameters table.
 */
final class ParameterCatalog
{
    public const GROUPS = [
        'ucret' => 'Ücret',
        'sgk' => 'SGK',
        'vergi' => 'Vergi',
        'tazminat' => 'Tazminat',
    ];

    /**
     * key => [label, group, type, description]
     *
     * @var array<string, array{0: string, 1: string, 2: ParameterType, 3: string}>
     */
    private const DEFINITIONS = [
        // Ücret
        'min_wage_gross' => ['Brüt asgari ücret (aylık)', 'ucret', ParameterType::Money, 'Asgari Ücret Tespit Komisyonu kararı. Asgari ücret istisnası buna göre hesaplanır.'],

        // SGK
        'sgk_floor_monthly' => ['Prime esas kazanç alt sınırı (aylık)', 'sgk', ParameterType::Money, 'Günlük alt sınır = aylık / 30.'],
        'sgk_ceiling_monthly' => ['Prime esas kazanç üst sınırı (aylık)', 'sgk', ParameterType::Money, 'Günlük üst sınır = aylık / 30.'],
        'sgk_employee_pension' => ['Malullük-yaşlılık-ölüm, işçi payı', 'sgk', ParameterType::Percent, ''],
        'sgk_employer_pension' => ['Malullük-yaşlılık-ölüm, işveren payı', 'sgk', ParameterType::Percent, ''],
        'sgk_employee_health' => ['Genel sağlık sigortası, işçi payı', 'sgk', ParameterType::Percent, ''],
        'sgk_employer_health' => ['Genel sağlık sigortası, işveren payı', 'sgk', ParameterType::Percent, ''],
        'sgk_employer_short_term' => ['Kısa vadeli sigorta kolları, işveren payı', 'sgk', ParameterType::Percent, ''],
        'unemployment_employee' => ['İşsizlik sigortası, işçi payı', 'sgk', ParameterType::Percent, ''],
        'unemployment_employer' => ['İşsizlik sigortası, işveren payı', 'sgk', ParameterType::Percent, ''],
        'treasury_discount_manufacturing' => ['Hazine prim indirimi, imalat sektörü', 'sgk', ParameterType::Points, 'İşveren malullük-yaşlılık-ölüm payından düşülen puan.'],
        'treasury_discount_other' => ['Hazine prim indirimi, diğer sektörler', 'sgk', ParameterType::Points, 'İşveren malullük-yaşlılık-ölüm payından düşülen puan.'],

        // Vergi
        'income_tax_brackets' => ['Gelir vergisi tarifesi (ücret gelirleri)', 'vergi', ParameterType::Brackets, 'Yıllık kümülatif matraha uygulanır.'],
        'stamp_tax_rate' => ['Damga vergisi oranı (ücret)', 'vergi', ParameterType::Percent, 'Binde 7,59 = %0,759.'],
        'disability_deduction_1' => ['Engellilik indirimi, 1. derece (aylık)', 'vergi', ParameterType::Money, ''],
        'disability_deduction_2' => ['Engellilik indirimi, 2. derece (aylık)', 'vergi', ParameterType::Money, ''],
        'disability_deduction_3' => ['Engellilik indirimi, 3. derece (aylık)', 'vergi', ParameterType::Money, ''],
        'meal_exemption_daily' => ['Yemek bedeli istisnası (günlük)', 'vergi', ParameterType::Money, 'KDV hariç.'],
        'transport_exemption_daily' => ['Yol bedeli istisnası (günlük)', 'vergi', ParameterType::Money, 'Ayni / toplu taşıma kartı ile verilen yol yardımı.'],

        // Tazminat
        'severance_ceiling' => ['Kıdem tazminatı tavanı', 'tazminat', ParameterType::Money, 'Ocak ve Temmuz aylarında güncellenir.'],
    ];

    /**
     * @return array<string, ParameterDefinition>
     */
    public static function all(): array
    {
        $all = [];

        foreach (self::DEFINITIONS as $key => [$label, $group, $type, $description]) {
            $all[$key] = new ParameterDefinition($key, $label, $group, $type, $description);
        }

        return $all;
    }

    /**
     * @return array<string, ParameterDefinition>
     */
    public static function inGroup(string $group): array
    {
        return array_filter(self::all(), fn (ParameterDefinition $definition) => $definition->group === $group);
    }

    public static function find(string $key): ?ParameterDefinition
    {
        return self::all()[$key] ?? null;
    }
}
