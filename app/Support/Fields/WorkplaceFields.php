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
 * İşyeri columns, in template order.
 *
 * The first block follows the customer's setup file ("KURULUM DOSYASI.xlsx", Firma Bilgileri sheet)
 * column by column, so that file can be uploaded as is; aliases accept its exact headers.
 * The second block holds the additional (optional) fields of the original spec.
 */
final class WorkplaceFields
{
    /**
     * @return list<Field>
     */
    public static function all(): array
    {
        return [
            // Kurulum dosyası (Firma Bilgileri) — hepsi zorunlu
            new Field('workplace_type', 'İşyeri Tipi', required: true, type: Field::SELECT,
                options: fn () => array_values(WorkplaceType::options())),
            new Field('company_name', 'Şirket Adı', hint: 'Şirketin unvanı veya kısa adı. Şirket Numarası verilirse o kullanılır.'),
            new Field('branch_name', 'İşyeri Şube Adı', required: true),
            new Field('workplace_no', 'İşyeri Numarası', required: true, hint: 'Şirket içinde benzersiz olmalıdır.'),
            new Field('workplace_kind', 'İşyeri Türü', required: true, type: Field::SELECT,
                options: fn () => array_values(WorkplaceKind::options()), aliases: ['İş Yeri Tipi 2']),
            new Field('tax_number', 'Vergi Numarası', required: true, aliases: ['Vergi No'],
                hint: '10 haneli VKN. Tescil tipi Gerçek ise 11 haneli T.C. kimlik numarası.'),
            new Field('tax_office', 'Vergi Dairesi', required: true),
            new Field('sgk_username', 'SGK Kullanıcı Adı (TCKN)', required: true, type: Field::SECRET,
                hint: '11 haneli T.C. kimlik numarası.'),
            new Field('hazard_class', 'Tehlike Sınıfı', required: true, type: Field::SELECT,
                options: fn () => array_values(HazardClass::options())),
            new Field('labor_sector', 'ÇSGB İşkolu', required: true, type: Field::SELECT,
                options: fn () => Field::strings(LaborSector::query()->orderBy('id')->pluck('name')), hint: 'Örn: 20 (Genel İşler)',
                aliases: ['İş Yerinin Çalışma ve Sosyal Güvenlik Bakanlığı İşkolu', 'Çalışma ve Sosyal Güvenlik Bakanlığı İş Kolu']),
            new Field('sgk_registry_no', 'İşyeri SGK Sicil Numarası', required: true, hint: '26 hane, sadece rakam.',
                aliases: ['İş Yeri SGK Numarası', 'İşyeri SGK Numarası']),
            new Field('sgk_directorate', 'Bağlı Bulunulan SGK Müdürlüğü', required: true, aliases: ['Bağlı Bulunan SGK Müdürlüğü']),
            new Field('sgk_officer_name', 'SGK İşyeri Yetkilisi Adı Soyadı', required: true),
            new Field('sgk_declaration_username', 'SGK Bildirge Kullanıcı Adı (TCKN)', required: true, type: Field::SECRET,
                hint: '11 haneli T.C. kimlik numarası.'),
            new Field('sgk_workplace_code', 'SGK İşyeri Kodu', required: true),
            new Field('sgk_workplace_password', 'SGK İşyeri Şifresi', required: true, type: Field::SECRET),
            new Field('sgk_system_password', 'SGK Sistem Şifresi', required: true, type: Field::SECRET),
            new Field('ebildirge_officer_name', 'e-Bildirge Yetkilisi Adı Soyadı', required: true,
                aliases: ['E-Bildirge Yetkili Adı Soyadı']),
            new Field('iskur_user_name', 'İŞKUR Kullanıcı Adı Soyadı', required: true),
            new Field('iskur_user_code', 'İŞKUR Kullanıcı Kodu (TCKN)', required: true, type: Field::SECRET, hint: '11 haneli T.C. kimlik numarası.'),
            new Field('iskur_password', 'İŞKUR Şifresi', required: true, type: Field::SECRET, aliases: ['İŞKUR Şifre']),
            new Field('iskur_registry_no', 'İŞKUR Sicil Numarası', required: true),
            new Field('tax_office_user_code', 'Vergi Dairesi Kullanıcı Kodu', required: true),
            new Field('dvd_username', 'Dijital Vergi Dairesi Kullanıcı Adı', required: true),
            new Field('dvd_password', 'Dijital Vergi Dairesi Şifre', required: true, type: Field::SECRET),
            new Field('dvd_passphrase', 'Dijital Vergi Dairesi Parola', required: true, type: Field::SECRET),
            new Field('ebeyanname_password', 'e-Beyanname Şifresi', required: true, type: Field::SECRET),
            new Field('nace_code', 'NACE Kodu', required: true, hint: '00.00.00 biçiminde, ör. 62.01.01'),
            new Field('police_email', 'Emniyet (Karakol) Bildirimi E-posta', required: true,
                aliases: ['Emniyet(Karakol) Bildirimi E-MAİL', 'Emniyet Bildirimi E-posta']),
            new Field('police_password', 'Emniyet (Karakol) Bildirimi Şifre', required: true, type: Field::SECRET,
                aliases: ['Emniyet(Karakol) Bildirimi Şifre']),
            new Field('bes_company_name', 'BES Firma Adı', required: true),
            new Field('bes_username', 'BES Firma Kullanıcı Adı', required: true),
            new Field('bes_password', 'BES Firma Şifre', required: true, type: Field::SECRET, aliases: ['BES Firma Şifresi']),
            new Field('address', 'İşyeri Açık Adresi', required: true, aliases: ['Açık Adres', 'Adres']),
            new Field('province_name', 'İl', required: true, type: Field::SELECT,
                options: fn () => Field::strings(Province::query()->orderBy('name')->pluck('name')),
                hint: 'Listeden seçin; listede yoksa elle yazılabilir.', aliases: ['İş Yeri İl']),
            new Field('district_name', 'İlçe', required: true, hint: 'İle göre eşleştirilir; bulunamazsa elle girilen değer saklanır.',
                aliases: ['İş Yeri İlçe']),

            // Ek bilgiler — isteğe bağlı
            new Field('company_no', 'Şirket Numarası', hint: 'Şirket Adı yerine kullanılabilir.'),
            new Field('title', 'Ünvan', aliases: ['Ünvanı', 'Unvan'], hint: 'Boş bırakılırsa şirketin unvanı kullanılır.'),
            new Field('registration_type', 'Tescil Tipi', type: Field::SELECT,
                options: fn () => array_values(RegistrationType::options())),
            new Field('mersis_no', 'MERSİS Numarası', hint: '16 hane.'),
            new Field('risk_class', 'Risk Sınıfı', type: Field::SELECT,
                options: fn () => Field::strings(RiskClass::query()->active()->orderBy('name')->pluck('name'))),
            new Field('neighborhood', 'Mahalle'),
            new Field('street', 'Cadde / Sokak / Bulvar', aliases: ['Bulvar', 'Cadde', 'Sokak']),
            new Field('outer_door_no', 'Dış Kapı No', aliases: ['Dış Kapı']),
            new Field('inner_door_no', 'İç Kapı No'),
            new Field('postal_code', 'Posta Kodu'),
            new Field('phone', 'Telefon', aliases: ['Telefon 1']),
            new Field('mobile_phone', 'Cep Telefonu'),
            new Field('email', 'E-Posta'),
            new Field('kep_address', 'KEP Adresi'),
            new Field('e_signature_officer', 'E-İmza Yetkilisi'),
            new Field('opening_date', 'İşyeri Açılış Tarihi', type: Field::DATE, hint: 'GG.AA.YYYY', aliases: ['Başlangıç Tarihi']),
            new Field('closing_date', 'İşyeri Kapanış Tarihi', type: Field::DATE, hint: 'GG.AA.YYYY', aliases: ['Bitiş Tarihi']),
            new Field('mahiyet_code', 'Mahiyet Kodu', aliases: ['Mahiyet Kod']),
            new Field('mahiyet_name', 'Mahiyet Adı'),
            new Field('tuik_user_full_name', 'TÜİK Kullanıcı Adı Soyadı', aliases: ['TÜİK Kullanıcı Adı ve Soyadı']),
            new Field('tuik_username', 'TÜİK Kullanıcı Adı'),
            new Field('tuik_password', 'TÜİK Şifresi', type: Field::SECRET, aliases: ['TÜİK Şifre']),
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
