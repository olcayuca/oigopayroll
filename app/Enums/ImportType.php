<?php

namespace App\Enums;

/**
 * Bulk Excel import kinds.
 */
enum ImportType: string
{
    use HasLabels;

    case Firm = 'firm';
    case Company = 'company';
    case Workplace = 'workplace';

    /**
     * URL segment (panel: aktarim/sirket, aktarim/isyeri; admin: firma).
     */
    public function slug(): string
    {
        return match ($this) {
            self::Firm => 'firma',
            self::Company => 'sirket',
            self::Workplace => 'isyeri',
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
        };
    }

    public function templateFilename(): string
    {
        return match ($this) {
            self::Firm => 'HRD_Firma_Sablonu.xlsx',
            self::Company => 'HRD_Sirket_Sablonu.xlsx',
            self::Workplace => 'HRD_Isyeri_Sablonu.xlsx',
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
        };
    }
}
