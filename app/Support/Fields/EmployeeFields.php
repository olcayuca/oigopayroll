<?php

namespace App\Support\Fields;

use App\Enums\CodeList;
use App\Models\PayrollCode;
use App\Support\EmployeeOptions;
use Closure;

/**
 * Personel columns, in the order and with the headers of the customer setup file
 * ("KURULUM DOSYASI.xlsx", Personel Bilgileri sheet), so that sheet can be uploaded as is.
 */
final class EmployeeFields
{
    /**
     * @return list<Field>
     */
    public static function all(): array
    {
        $yesNo = fn () => ['Evet', 'Hayır'];

        return [
            new Field('registry_no', 'Sicil No', required: true, hint: 'Firma içinde benzersiz; eşleşen sicil güncellenir.'),
            new Field('tckn', 'TC Kimlik No', required: true, type: Field::SECRET, hint: '11 hane. Şifreli saklanır.'),
            new Field('first_name', 'Adı', required: true),
            new Field('last_name', 'Soyadı', required: true),
            new Field('second_last_name', 'İkinci Soyadı'),
            new Field('work_email', 'Şirket E-posta'),
            new Field('personal_email', 'Kişisel E-posta', required: true),
            new Field('mobile_phone', 'Cep Telefonu', required: true),
            new Field('work_phone', 'İş Telefonu'),
            new Field('address', 'Oturulan Adres'),
            new Field('province', 'İl'),
            new Field('district', 'İlçe'),
            new Field('birth_date', 'Doğum Tarihi', required: true, type: Field::DATE, hint: 'GG.AA.YYYY'),
            new Field('hire_date', 'İşe Giriş Tarihi', required: true, type: Field::DATE, hint: 'GG.AA.YYYY'),
            new Field('seniority_date', 'Kıdeme Esas Tarihi', required: true, type: Field::DATE, hint: 'GG.AA.YYYY'),
            new Field('leave_base_date', 'İzne Esas Tarihi', required: true, type: Field::DATE, hint: 'GG.AA.YYYY'),
            new Field('collar', 'Yaka', type: Field::SELECT, options: self::list(EmployeeOptions::COLLAR)),
            new Field('gender', 'Cinsiyet', required: true, type: Field::SELECT, options: self::list(EmployeeOptions::GENDER)),
            new Field('marital_status', 'Medeni Hal', type: Field::SELECT, options: self::list(EmployeeOptions::MARITAL_STATUS)),
            new Field('education', 'Eğitim Durumu', type: Field::SELECT, options: self::list(EmployeeOptions::EDUCATION)),
            new Field('graduation_field', 'Mezuniyet Bölümü'),
            new Field('military_status', 'Askerlik', type: Field::SELECT, options: self::list(EmployeeOptions::MILITARY)),
            new Field('company_name', 'Firma', required: true, hint: 'Şirketin unvanı, kısa adı veya numarası.'),
            new Field('sgk_company_name', 'SGK Firma', required: true, hint: 'SGK işyerinin bağlı olduğu şirket.'),
            new Field('workplace_name', 'İş Yeri Şube Adı', required: true, hint: 'SGK firmasındaki işyerinin şube adı veya numarası.'),
            new Field('job_family', 'İş Ailesi', hint: 'Tanımlarda yoksa oluşturulur.'),
            new Field('unit', 'Birim', hint: 'Tanımlarda yoksa oluşturulur.'),
            new Field('upper_unit', 'Üst Birim', required: true, hint: 'Tanımlarda yoksa oluşturulur.'),
            new Field('duty_type', 'Görev Tipi', type: Field::SELECT, options: self::list(EmployeeOptions::DUTY_TYPE)),
            new Field('occupation_code', 'SGK Meslek Kodu', required: true, hint: '0000.00 biçiminde, ör. 2411.12'),
            new Field('insurance_branch', 'Sigorta Kolu', required: true, type: Field::SELECT, options: self::list(EmployeeOptions::INSURANCE_BRANCH)),
            new Field('sgk_status', 'SGK Statü', required: true, type: Field::SELECT, options: self::list(EmployeeOptions::SGK_STATUS)),
            new Field('employment_type', 'Çalışan Tipi', required: true, type: Field::SELECT, options: self::list(EmployeeOptions::EMPLOYMENT_TYPE)),
            new Field('duty_code', 'Görev Kodu', required: true, type: Field::SELECT, options: self::list(EmployeeOptions::DUTY_CODE)),
            new Field('sgk_document_type', 'SGK Belge Türü', required: true, type: Field::SELECT,
                options: fn () => Field::strings(PayrollCode::query()->where('list', CodeList::DocumentTypes)->orderBy('code')->pluck('code'))),
            new Field('bank_name', 'Banka Adı', required: true),
            new Field('bank_branch', 'Şube Adı', required: true, aliases: ['Banka Şube Adı']),
            new Field('iban', 'Iban Numarası', required: true, type: Field::SECRET, aliases: ['IBAN'], hint: 'TR ile başlayan 26 karakter.'),
            new Field('account_no', 'Hesap No', required: true, type: Field::SECRET),
            new Field('bes_rate', 'Otomatik Bes Oran', required: true, hint: 'Ör. %3', aliases: ['Otomatik BES Oranı']),
            new Field('cumulative_tax_base', 'Kümülatif Gelir Vergisi Mat.', required: true, aliases: ['Kümülatif Gelir Vergisi Matrahı']),
            new Field('tax_exemption_start_month', 'Vergi İstisnası Başlangıç Ayı', required: true, type: Field::SELECT,
                options: self::list(array_values(EmployeeOptions::MONTHS))),
            new Field('previous_sgk_base_1', 'Bir Önceki Dönem Devreden SGK Matrahı', required: true),
            new Field('previous_sgk_base_2', 'İki Önceki Dönem Devreden SGK Matrahı', required: true),
            new Field('wage_period', 'Ücret Periyodu', required: true, type: Field::SELECT, options: self::list(EmployeeOptions::WAGE_PERIOD)),
            new Field('currency', 'Para Birimi', required: true, type: Field::SELECT, options: self::list(EmployeeOptions::CURRENCY)),
            new Field('wage_type', 'Ücret Tipi', required: true, type: Field::SELECT, options: self::list(EmployeeOptions::WAGE_TYPE)),
            new Field('wage', 'Ücret', required: true, hint: 'Ör. 82.400,00'),
            new Field('is_minimum_wage', 'Asgari Ücretli', required: true, type: Field::BOOLEAN, options: $yesNo),
            new Field('minimum_wage_exemption', 'Asgari Ücret Vergi İstisnasına Tabi Mi?', required: true, type: Field::BOOLEAN, options: $yesNo),
            new Field('rnd_rate', 'Ar-Ge İndirim Oranı', type: Field::SELECT, options: self::list(EmployeeOptions::RND_RATE)),
            new Field('disability_degree', 'Engellilik Derecesi', type: Field::SELECT, options: self::list(EmployeeOptions::DISABILITY_DEGREE)),
            new Field('disability_tax_relief', 'Engelli Gelir Verg. İndiriminden Faydalanıyor mu?', type: Field::BOOLEAN, options: $yesNo,
                aliases: ['Engelli Gelir Vergisi İndiriminden Faydalanıyor mu?']),
            new Field('disability_end_date', 'Engellilik Bitiş Tarihi', type: Field::DATE, hint: 'GG.AA.YYYY'),
            new Field('cost_group', 'Masraf Grubu', hint: 'Tanımlarda yoksa oluşturulur.'),
            new Field('cost_group_rate', 'Masraf Grubu Oranı', hint: 'Ör. %100'),
            new Field('work_model', 'Çalışma Modeli', required: true, type: Field::SELECT, options: self::list(EmployeeOptions::WORK_MODEL)),
            new Field('contract_type', 'Sözleşme Türü', required: true, type: Field::SELECT, options: self::list(EmployeeOptions::CONTRACT_TYPE)),
            new Field('title', 'Unvan', required: true, hint: 'Tanımlarda yoksa oluşturulur.'),
            new Field('position', 'Pozisyon', required: true, hint: 'Tanımlarda yoksa oluşturulur.'),
            new Field('level', 'Seviye', hint: 'Tanımlarda yoksa oluşturulur.'),
            new Field('leave_manager_registry_no', 'İzin Yönetici Sicil No', required: true),
            new Field('functional_manager_registry_no', 'Fonksiyonel Yönetici Sicil No'),
            new Field('remaining_leave_days', 'Kalan Yıllık İzin Hakkı', required: true, hint: 'Gün'),
            new Field('is_shift_worker', 'Vardiyalı mı Çalışıyor?', required: true, type: Field::BOOLEAN, options: $yesNo),
            new Field('shift_start', 'Mesai Başlangıç Saati', required: true, hint: 'SS:DD, ör. 09:00'),
            new Field('shift_end', 'Mesai Bitiş Saati', required: true, hint: 'SS:DD, ör. 18:00'),
            new Field('weekly_rest', 'Hafta Tatili Gün(leri)', required: true, type: Field::SELECT, options: self::list(EmployeeOptions::WEEKLY_REST),
                aliases: ['Hafta Tatili']),
        ];
    }

    /**
     * Dropdown values of a fixed list.
     *
     * @param  array<int, string>  $options
     * @return Closure(): list<string>
     */
    private static function list(array $options): Closure
    {
        return fn (): array => array_values($options);
    }
}
