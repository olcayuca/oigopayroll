<?php

namespace App\Enums;

enum DocumentType: string
{
    use HasLabels;

    case TaxCertificate = 'vergi_levhasi';
    case SignatureCircular = 'imza_sirkuleri';
    case TradeRegistryGazette = 'ticaret_sicil_gazetesi';
    case ActivityCertificate = 'faaliyet_belgesi';
    case SgkRegistration = 'sgk_tescil';
    case PowerOfAttorney = 'vekaletname';
    case Contract = 'sozlesme';
    case IdentityCopy = 'kimlik';
    case Other = 'diger';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::TaxCertificate => 'Vergi Levhası',
            self::SignatureCircular => 'İmza Sirküleri',
            self::TradeRegistryGazette => 'Ticaret Sicil Gazetesi',
            self::ActivityCertificate => 'Faaliyet Belgesi',
            self::SgkRegistration => 'SGK İşyeri Tescil Belgesi',
            self::PowerOfAttorney => 'Vekaletname',
            self::Contract => 'Sözleşme',
            self::IdentityCopy => 'Kimlik Fotokopisi',
            self::Other => 'Diğer',
        };
    }
}
