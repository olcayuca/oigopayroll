<?php

namespace App\Enums;

/**
 * Official payroll code lists managed in Admin → Bordro Tanımları → Bordro Kodları.
 */
enum CodeList: string
{
    use HasLabels;

    case DocumentTypes = 'document_types';
    case MissingDayReasons = 'missing_day_reasons';
    case TerminationReasons = 'termination_reasons';
    case IncentiveLaws = 'incentive_laws';
    case Occupations = 'occupations';
    case Banks = 'banks';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::DocumentTypes => 'SGK Belge Türleri',
            self::MissingDayReasons => 'Eksik Gün Nedenleri',
            self::TerminationReasons => 'İşten Çıkış Kodları',
            self::IncentiveLaws => 'Teşvik Kanunları',
            self::Occupations => 'Meslek Kodları',
            self::Banks => 'Bankalar',
        };
    }

    /**
     * URL segment for the admin tabs.
     */
    public function slug(): string
    {
        return match ($this) {
            self::DocumentTypes => 'belge-turleri',
            self::MissingDayReasons => 'eksik-gun',
            self::TerminationReasons => 'isten-cikis',
            self::IncentiveLaws => 'tesvik',
            self::Occupations => 'meslek',
            self::Banks => 'banka',
        };
    }

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->slug() === $slug) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Code format hint and validation pattern.
     *
     * @return array{0: string, 1: string}
     */
    public function format(): array
    {
        return match ($this) {
            self::DocumentTypes, self::MissingDayReasons => ['2 haneli, ör. 01', '/^\d{2}$/'],
            self::TerminationReasons => ['1–2 haneli, ör. 4', '/^\d{1,2}$/'],
            self::IncentiveLaws => ['5 haneli, ör. 05510', '/^\d{5}$/'],
            self::Occupations => ['ISCO-08, ör. 2411.01', '/^\d{4}\.\d{2}$/'],
            self::Banks => ['Banka / EFT kodu, ör. 0010', '/^\d{3,5}$/'],
        };
    }
}
