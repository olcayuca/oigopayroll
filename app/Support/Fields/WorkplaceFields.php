<?php

namespace App\Support\Fields;

use App\Enums\HazardClass;
use App\Enums\RegistrationType;
use App\Enums\WorkplaceKind;
use App\Enums\WorkplaceType;
use App\Models\LaborSector;
use App\Models\Province;
use App\Models\RiskClass;

/**
 * İşyeri columns, in template order. Aliases accept the headers of the original customer spreadsheet.
 */
final class WorkplaceFields
{
    /**
     * @return list<Field>
     */
    public static function all(): array
    {
        return [
            // Temel bilgiler
            new Field('company_no', 'Şirket Numarası', required: true, hint: 'İşyerinin bağlı olduğu şirketin numarası.'),
            new Field('workplace_no', 'İşyeri Numarası', required: true, hint: 'Şirket içinde benzersiz olmalıdır.'),
            new Field('branch_name', 'İşyeri Şube Adı', required: true),
            new Field('workplace_type', 'İşyeri Tipi', required: true, type: Field::SELECT,
                options: fn () => array_values(WorkplaceType::options())),
            new Field('workplace_kind', 'İşyeri Türü', required: true, type: Field::SELECT,
                options: fn () => array_values(WorkplaceKind::options()), aliases: ['İş Yeri Tipi 2']),
            new Field('title', 'Ünvan', required: true, aliases: ['Ünvanı', 'Unvan']),
            new Field('registration_type', 'Tescil Tipi', type: Field::SELECT,
                options: fn () => array_values(RegistrationType::options())),
            new Field('tax_number', 'Vergi Numarası', required: true,
                hint: '10 haneli VKN. Tescil tipi Gerçek ise 11 haneli T.C. kimlik numarası.'),
            new Field('tax_office', 'Vergi Dairesi', required: true),
            new Field('mersis_no', 'MERSİS Numarası', hint: '16 hane.'),
            new Field('risk_class', 'Risk Sınıfı', type: Field::SELECT,
                options: fn () => Field::strings(RiskClass::query()->active()->orderBy('name')->pluck('name'))),
            new Field('hazard_class', 'Tehlike Sınıfı', required: true, type: Field::SELECT,
                options: fn () => array_values(HazardClass::options())),
            new Field('labor_sector', 'ÇSGB İşkolu', type: Field::SELECT,
                options: fn () => Field::strings(LaborSector::query()->orderBy('id')->pluck('name')),
                aliases: ['İş Yerinin Çalışma ve Sosyal Güvenlik Bakanlığı İşkolu', 'Çalışma ve Sosyal Güvenlik Bakanlığı İş Kolu']),

            // Adres ve iletişim
            new Field('province_name', 'İl', required: true, type: Field::SELECT,
                options: fn () => Field::strings(Province::query()->orderBy('name')->pluck('name')),
                hint: 'Listeden seçin; listede yoksa elle yazılabilir.', aliases: ['İş Yeri İl']),
            new Field('district_name', 'İlçe', required: true, hint: 'İle göre eşleştirilir; bulunamazsa elle girilen değer saklanır.',
                aliases: ['İş Yeri İlçe']),
            new Field('neighborhood', 'Mahalle'),
            new Field('street', 'Cadde / Sokak / Bulvar', aliases: ['Bulvar', 'Cadde', 'Sokak']),
            new Field('outer_door_no', 'Dış Kapı No', aliases: ['Dış Kapı']),
            new Field('inner_door_no', 'İç Kapı No'),
            new Field('postal_code', 'Posta Kodu'),
            new Field('address', 'İşyeri Açık Adresi', required: true, aliases: ['Açık Adres', 'Adres']),
            new Field('phone', 'Telefon', aliases: ['Telefon 1']),
            new Field('mobile_phone', 'Cep Telefonu'),
            new Field('email', 'E-Posta'),
            new Field('kep_address', 'KEP Adresi'),
            new Field('e_signature_officer', 'E-İmza Yetkilisi'),

            // SGK
            new Field('sgk_registry_no', 'İşyeri SGK Sicil Numarası', hint: '26 hane, sadece rakam.',
                aliases: ['İş Yeri SGK Numarası', 'İşyeri SGK Numarası']),
            new Field('sgk_directorate', 'Bağlı Bulunulan SGK Müdürlüğü', aliases: ['Bağlı Bulunan SGK Müdürlüğü']),
            new Field('sgk_officer_name', 'SGK İşyeri Yetkilisi Adı Soyadı', required: true),
            new Field('sgk_workplace_code', 'SGK İşyeri Kodu', required: true),
            new Field('ebildirge_officer_name', 'e-Bildirge Yetkilisi Adı Soyadı', required: true,
                aliases: ['E-Bildirge Yetkili Adı Soyadı']),
            new Field('opening_date', 'İşyeri Açılış Tarihi', required: true, type: Field::DATE,
                hint: 'GG.AA.YYYY', aliases: ['Başlangıç Tarihi']),
            new Field('closing_date', 'İşyeri Kapanış Tarihi', type: Field::DATE, hint: 'GG.AA.YYYY', aliases: ['Bitiş Tarihi']),
            new Field('mahiyet_code', 'Mahiyet Kodu', aliases: ['Mahiyet Kod']),
            new Field('mahiyet_name', 'Mahiyet Adı'),
            new Field('sgk_declaration_username', 'SGK Bildirge Kullanıcı Adı (TCKN)', required: true, type: Field::SECRET,
                hint: '11 haneli T.C. kimlik numarası.'),
            new Field('sgk_workplace_password', 'SGK İşyeri Şifresi', required: true, type: Field::SECRET),
            new Field('sgk_system_password', 'SGK Sistem Şifresi', required: true, type: Field::SECRET),

            // İŞKUR / TÜİK / Vergi
            new Field('iskur_user_name', 'İŞKUR Kullanıcı Adı Soyadı'),
            new Field('iskur_user_code', 'İŞKUR Kullanıcı Kodu (TCKN)', type: Field::SECRET, hint: '11 haneli T.C. kimlik numarası.'),
            new Field('iskur_password', 'İŞKUR Şifresi', type: Field::SECRET, aliases: ['İŞKUR Şifre']),
            new Field('iskur_registry_no', 'İŞKUR Sicil Numarası'),
            new Field('tuik_user_full_name', 'TÜİK Kullanıcı Adı Soyadı', aliases: ['TÜİK Kullanıcı Adı ve Soyadı']),
            new Field('tuik_username', 'TÜİK Kullanıcı Adı'),
            new Field('tuik_password', 'TÜİK Şifresi', type: Field::SECRET, aliases: ['TÜİK Şifre']),
            new Field('tax_office_user_code', 'Vergi Dairesi Kullanıcı Kodu'),
            new Field('ebeyanname_password', 'e-Beyanname Şifresi', type: Field::SECRET),

            // Sendika / TİS
            new Field('has_union', 'Sendikalı İşyeri', type: Field::BOOLEAN, options: fn () => ['Evet', 'Hayır'],
                aliases: ['İşyeri Sendika']),
            new Field('union_name', 'Sendika Adı'),
            new Field('cba_start_date', 'TİS Başlangıç Tarihi', type: Field::DATE, hint: 'GG.AA.YYYY',
                aliases: ['Sözleşme Başlangıç Tarihi']),
            new Field('cba_end_date', 'TİS Bitiş Tarihi', type: Field::DATE, hint: 'GG.AA.YYYY', aliases: ['Sözleşme Bitiş Tarihi']),
            new Field('cba_signed_date', 'TİS İmza Tarihi', type: Field::DATE, hint: 'GG.AA.YYYY', aliases: ['Sözleşme İmza Tarihi']),
        ];
    }
}
