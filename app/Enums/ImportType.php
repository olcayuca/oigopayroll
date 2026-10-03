<?php

namespace App\Enums;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Firm;
use App\Models\Workplace;

/**
 * Bulk Excel import kinds.
 */
enum ImportType: string
{
    use HasLabels;

    case Firm = 'firm';
    case Company = 'company';
    case Workplace = 'workplace';
    case Employee = 'employee';

    /**
     * URL segment (panel: aktarim/sirket, aktarim/isyeri; admin: firma).
     */
    public function slug(): string
    {
        return match ($this) {
            self::Firm => 'firma',
            self::Company => 'sirket',
            self::Workplace => 'isyeri',
            self::Employee => 'personel',
        };
    }

    /**
     * Name of the data sheet in the Excel template.
     */
    public function sheetTitle(): string
    {
        return match ($this) {
            self::Firm => 'Firmalar',
            self::Company => 'Şirketler',
            self::Workplace => 'İşyerleri',
            self::Employee => 'Personel Bilgileri',
        };
    }

    public function templateFilename(): string
    {
        return match ($this) {
            self::Firm => 'HRD_Firma_Sablonu.xlsx',
            self::Company => 'HRD_Sirket_Sablonu.xlsx',
            self::Workplace => 'HRD_Isyeri_Sablonu.xlsx',
            self::Employee => 'HRD_Personel_Sablonu.xlsx',
        };
    }

    /**
     * Model class whose policy authorizes the import ("import" ability).
     *
     * @return class-string<Firm|Company|Workplace|Employee>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Firm => Firm::class,
            self::Company => Company::class,
            self::Workplace => Workplace::class,
            self::Employee => Employee::class,
        };
    }

    /**
     * Panel list the import belongs to.
     */
    public function indexRoute(): string
    {
        return match ($this) {
            self::Company => 'companies.index',
            self::Employee => 'employees.index',
            default => 'workplaces.index',
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
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Firm => 'Firma',
            self::Company => 'Şirket',
            self::Workplace => 'İşyeri',
            self::Employee => 'Personel',
        };
    }
}
