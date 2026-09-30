<?php

namespace App\Enums;

/**
 * KVKK texts shown to users.
 */
enum PolicyType: string
{
    use HasLabels;

    /** Aydınlatma yükümlülüğü (md. 10): information; users acknowledge having read it. */
    case Disclosure = 'aydinlatma';

    /** Açık rıza (md. 5/1): must be freely given, so users may accept or refuse and withdraw later. */
    case ExplicitConsent = 'acik_riza';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Disclosure => 'Aydınlatma Metni',
            self::ExplicitConsent => 'Açık Rıza Metni',
        };
    }

    /**
     * Whether the user must accept (acknowledge) before using the system.
     */
    public function isMandatory(): bool
    {
        return $this === self::Disclosure;
    }
}
