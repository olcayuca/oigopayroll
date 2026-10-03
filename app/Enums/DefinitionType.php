<?php

namespace App\Enums;

/**
 * Firm-level lists (Panel → Tanımlar) chosen on personnel records.
 */
enum DefinitionType: string
{
    use HasLabels;

    case UpperUnit = 'ust_birim';
    case Unit = 'birim';
    case JobFamily = 'is_ailesi';
    case Title = 'unvan';
    case Position = 'pozisyon';
    case Level = 'seviye';
    case CostGroup = 'masraf_grubu';

    public function label(): string
    {
        return match ($this) {
            self::UpperUnit => 'Üst Birimler',
            self::Unit => 'Birimler',
            self::JobFamily => 'İş Aileleri',
            self::Title => 'Unvanlar',
            self::Position => 'Pozisyonlar',
            self::Level => 'Seviyeler',
            self::CostGroup => 'Masraf Grupları',
        };
    }

    /**
     * Singular label (form fields, messages).
     */
    public function singular(): string
    {
        return match ($this) {
            self::UpperUnit => 'Üst Birim',
            self::Unit => 'Birim',
            self::JobFamily => 'İş Ailesi',
            self::Title => 'Unvan',
            self::Position => 'Pozisyon',
            self::Level => 'Seviye',
            self::CostGroup => 'Masraf Grubu',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::UpperUnit => 'Organizasyonun en üst kırılımı (direktörlük, müdürlük).',
            self::Unit => 'Personelin bağlı olduğu birimler ve üst birim eşleşmesi.',
            self::JobFamily => 'Benzer yetkinlik gerektiren görev grupları.',
            self::Title => 'Kurumsal unvan listesi.',
            self::Position => 'Birim bazlı pozisyon tanımları.',
            self::Level => 'Kariyer / kademe seviyeleri.',
            self::CostGroup => 'Personel maliyetinin muhasebeleştirileceği masraf merkezleri.',
        };
    }

    /**
     * The definition type a record of this type may point to (birim → üst birim, pozisyon → birim).
     */
    public function parentType(): ?self
    {
        return match ($this) {
            self::Unit => self::UpperUnit,
            self::Position => self::Unit,
            default => null,
        };
    }

    /**
     * Extra text columns kept in Definition::$extra: key => label.
     *
     * @return array<string, string>
     */
    public function extraFields(): array
    {
        return match ($this) {
            self::CostGroup => ['cost_center' => 'Masraf Merkezi Kodu', 'account_code' => 'Muhasebe Hesap Kodu'],
            default => [],
        };
    }

    /**
     * Employee column holding a definition of this type.
     */
    public function employeeColumn(): string
    {
        return match ($this) {
            self::UpperUnit => 'upper_unit_id',
            self::Unit => 'unit_id',
            self::JobFamily => 'job_family_id',
            self::Title => 'title_id',
            self::Position => 'position_id',
            self::Level => 'level_id',
            self::CostGroup => 'cost_group_id',
        };
    }

    /**
     * URL segment (?tur=).
     */
    public function slug(): string
    {
        return str_replace('_', '-', $this->value);
    }

    public static function fromSlug(string $slug): ?self
    {
        return self::tryFrom(str_replace('-', '_', $slug));
    }
}
