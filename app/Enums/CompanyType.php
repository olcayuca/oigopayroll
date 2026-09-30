<?php

namespace App\Enums;

/**
 * Legal form of a company (şirket tipi).
 */
enum CompanyType: string
{
    use HasLabels;

    case JointStock = 'anonim';
    case Limited = 'limited';
    case SoleProprietorship = 'sahis';
    case LiaisonOffice = 'irtibat_burosu';
    case OrdinaryPartnership = 'adi_ortaklik';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::JointStock => 'Anonim Şirket',
            self::Limited => 'Limited Şirket',
            self::SoleProprietorship => 'Şahıs Firması',
            self::LiaisonOffice => 'İrtibat Bürosu',
            self::OrdinaryPartnership => 'Adi Ortaklık',
        };
    }
}
