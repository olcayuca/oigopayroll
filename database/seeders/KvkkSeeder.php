<?php

namespace Database\Seeders;

use App\Enums\PolicyType;
use App\Kvkk\Policies;
use App\Models\PolicyDocument;
use Illuminate\Database\Seeder;

/**
 * Placeholder KVKK texts (TASLAK). They must be reviewed by a lawyer and republished
 * from Admin → KVKK before going live. Only publishes a type that has no version yet.
 */
class KvkkSeeder extends Seeder
{
    public function run(Policies $policies): void
    {
        $texts = [
            [PolicyType::Disclosure, 'Kişisel Verilerin İşlenmesine İlişkin Aydınlatma Metni (TASLAK)', self::disclosure()],
            [PolicyType::ExplicitConsent, 'Açık Rıza Metni (TASLAK)', self::explicitConsent()],
        ];

        foreach ($texts as [$type, $title, $body]) {
            if (! PolicyDocument::query()->where('type', $type)->exists()) {
                $policies->publish($type, $title, $body);
            }
        }
    }

    public static function disclosure(): string
    {
        return <<<'MD'
> **TASLAK — YER TUTUCU METİN.** Bu metin hukuki onaydan geçmemiştir. Köşeli parantez içindeki alanlar doldurulmalı ve metin KVKK danışmanı / avukat tarafından gözden geçirildikten sonra yeni sürüm olarak yayımlanmalıdır.

## 1. Veri Sorumlusu

6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") uyarınca kişisel verileriniz, veri sorumlusu sıfatıyla **[ŞİRKET ÜNVANI]** ("Şirket") tarafından aşağıda açıklanan kapsamda işlenmektedir.

- Adres: [ADRES]
- E-posta: [E-POSTA]
- KEP: [KEP ADRESİ]
- VERBİS kayıt no: [VARSA]

## 2. İşlenen Kişisel Veriler

- **Kimlik:** ad, soyad, T.C. kimlik numarası [bordro hizmeti kapsamında]
- **İletişim:** e-posta adresi, telefon numarası
- **Müşteri işlem:** firma, şirket ve işyeri bilgileri, yetki tanımları
- **İşlem güvenliği:** IP adresi, tarayıcı bilgisi, giriş/çıkış kayıtları, işlem kayıtları
- [ÇALIŞANLAR İÇİN: özlük, finans (ücret, banka hesabı), SGK bilgileri vb.]

## 3. İşleme Amaçları

- Bordro ve insan kaynakları hizmetlerinin sunulması
- Sözleşmenin kurulması ve ifası
- Yasal yükümlülüklerin (vergi, SGK, iş mevzuatı) yerine getirilmesi
- Bilgi güvenliğinin sağlanması ve yetkisiz erişimin önlenmesi
- [DİĞER AMAÇLAR]

## 4. Hukuki Sebepler (KVKK md. 5/2)

- Kanunlarda açıkça öngörülmesi
- Sözleşmenin kurulması veya ifasıyla doğrudan ilgili olması
- Veri sorumlusunun hukuki yükümlülüğünü yerine getirmesi
- İlgili kişinin temel hak ve özgürlüklerine zarar vermemek kaydıyla veri sorumlusunun meşru menfaati

## 5. Aktarım

Kişisel verileriniz yukarıdaki amaçlarla sınırlı olarak; yetkili kamu kurum ve kuruluşlarına (SGK, Gelir İdaresi Başkanlığı vb.), hizmet aldığımız barındırma ve teknoloji sağlayıcılarına ve hizmet verdiğimiz müşteri firmaya aktarılabilir. [YURT DIŞINA AKTARIM VARSA BELİRTİN]

## 6. Toplama Yöntemi

Kişisel verileriniz bu sistem üzerinden elektronik ortamda, formlar, Excel aktarımları ve sistem kayıtları yoluyla toplanmaktadır.

## 7. Haklarınız (KVKK md. 11)

Kişisel verilerinizin işlenip işlenmediğini öğrenme, bilgi talep etme, amacına uygun kullanılıp kullanılmadığını öğrenme, aktarıldığı üçüncü kişileri bilme, düzeltilmesini, silinmesini veya yok edilmesini isteme, bu işlemlerin aktarılan üçüncü kişilere bildirilmesini isteme, otomatik sistemlerle analiz sonucu aleyhinize bir sonuç çıkmasına itiraz etme ve zarara uğramanız hâlinde zararın giderilmesini talep etme haklarına sahipsiniz.

Başvurularınızı sistemde **Ayarlar → KVKK → Başvurularım** bölümünden yapabilirsiniz. Başvurular en geç 30 gün içinde ücretsiz olarak sonuçlandırılır.
MD;
    }

    public static function explicitConsent(): string
    {
        return <<<'MD'
> **TASLAK — YER TUTUCU METİN.** Bu metin hukuki onaydan geçmemiştir. Açık rıza yalnızca KVKK md. 5/2'deki hukuki sebeplere dayanılamayan işlemler için alınmalıdır; hangi işlemlerin bu kapsamda olduğu hukuk danışmanınızla belirlenmelidir.

**[ŞİRKET ÜNVANI]** tarafından sunulan Aydınlatma Metni'ni okudum. Aşağıdaki işlemler için kişisel verilerimin işlenmesine **özgür irademle açık rıza veriyorum**:

- [ÖRNEK: Kişisel verilerimin yurt dışında bulunan sunucularda barındırılması / yurt dışına aktarılması]
- [ÖRNEK: Hizmetlerle ilgili bilgilendirme ve duyuru iletileri gönderilmesi]
- [DİĞER AÇIK RIZA GEREKTİREN İŞLEMLER]

Açık rıza vermemem, sistemin kullanımına engel değildir. Verdiğim rızayı dilediğim zaman **Ayarlar → KVKK** bölümünden geri alabileceğimi; geri almanın, geri alma tarihinden sonraki işlemler için geçerli olacağını biliyorum.
MD;
    }
}
