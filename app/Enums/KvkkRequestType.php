<?php

namespace App\Enums;

/**
 * Data subject rights (KVKK md. 11).
 */
enum KvkkRequestType: string
{
    use HasLabels;

    case Information = 'bilgi';
    case Copy = 'kopya';
    case Correction = 'duzeltme';
    case Erasure = 'silme';
    case Objection = 'itiraz';
    case Other = 'diger';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Information => 'Kişisel verilerimin işlenip işlenmediğini ve amacını öğrenme',
            self::Copy => 'İşlenen kişisel verilerimin bir kopyasını alma',
            self::Correction => 'Eksik veya yanlış işlenen verilerin düzeltilmesi',
            self::Erasure => 'Kişisel verilerimin silinmesi / yok edilmesi',
            self::Objection => 'İşlemeye / otomatik sonuçlara itiraz',
            self::Other => 'Diğer',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Information => 'Bilgi',
            self::Copy => 'Veri kopyası',
            self::Correction => 'Düzeltme',
            self::Erasure => 'Silme',
            self::Objection => 'İtiraz',
            self::Other => 'Diğer',
        };
    }
}
