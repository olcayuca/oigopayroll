<?php

namespace App\Support;

use App\Models\Firm;

/**
 * Firm-level settings kept in firms.settings (Panel → Ayarlar).
 *
 * Bordro varsayılanları: wage type, minimum-wage exemption and automatic BES already prefill new
 * personnel records; the rest is read by payroll calculation once that module exists.
 */
final class FirmSettings
{
    /** key => [label, options|null (null = yes/no), default] */
    public const PAYROLL = [
        'payment_day' => ['Maaş ödeme günü', ['Ayın son iş günü', "Ayın 1'i", "Ayın 5'i", "Ayın 15'i"], 'Ayın son iş günü'],
        'wage_type' => ['Varsayılan ücret tipi', ['Brüt', 'Net'], 'Brüt'],
        'net_rounding' => ['Net ücret yuvarlama', ['Yuvarlama yok', '1 TL', '10 TL'], 'Yuvarlama yok'],
        'overtime_rate' => ['Fazla mesai çarpanı', ['%150', '%200'], '%150'],
        'monthly_days' => ['Aylık ücret gün hesabı', ['Her ay 30 gün', 'Takvim günü'], 'Her ay 30 gün'],
        'treasury_discount' => ['5 puan Hazine indirimi', null, true],
        'minimum_wage_exemption' => ['Asgari ücret istisnası', null, true],
        'auto_bes' => ['Otomatik BES', null, true],
    ];

    /** Shown under the yes/no switches. */
    public const PAYROLL_HINTS = [
        'treasury_discount' => 'Tüm personele varsayılan uygulanır.',
        'minimum_wage_exemption' => 'Yeni personelde varsayılan açık gelir.',
        'auto_bes' => 'Uygun personel otomatik dahil edilir (yeni personelde %3).',
    ];

    /**
     * Bordro varsayılanları with defaults filled in.
     *
     * @return array<string, string|bool>
     */
    public static function payroll(Firm $firm): array
    {
        $stored = (array) ($firm->settings['payroll'] ?? []);
        $values = [];

        foreach (self::PAYROLL as $key => [, $options, $default]) {
            $value = $stored[$key] ?? $default;
            $values[$key] = $options === null ? (bool) $value : (in_array($value, $options, true) ? $value : $default);
        }

        return $values;
    }

    /**
     * Abonelik: charge the saved card automatically when an invoice is due (on unless switched off).
     */
    public static function autoPay(Firm $firm): bool
    {
        return (bool) ($firm->settings['billing']['auto_pay'] ?? true);
    }

    /**
     * İki adımlı doğrulama zorunlu for every user working in this firm.
     */
    public static function requiresTwoFactor(Firm $firm): bool
    {
        return (bool) ($firm->settings['security']['require_two_factor'] ?? false);
    }
}
