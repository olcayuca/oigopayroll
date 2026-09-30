<?php

namespace App\Enums;

/**
 * Bulk Excel import kinds.
 */
enum ImportType: string
{
    use HasLabels;

    case Company = 'company';
    case Workplace = 'workplace';

    /**
     * URL segment used in the panel (aktarim/sirket, aktarim/isyeri).
     */
    public function slug(): string
    {
        return match ($this) {
            self::Company => 'sirket',
            self::Workplace => 'isyeri',
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
            self::Company => 'Şirket',
            self::Workplace => 'İşyeri',
        };
    }
}
