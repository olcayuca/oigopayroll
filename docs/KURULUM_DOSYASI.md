# Müşteri Kurulum Dosyası (KURULUM DOSYASI.xlsx)

Müşteri şirket kurulumunda iki sayfa doldurur. Kırmızı başlıklar zorunludur. Panel ekranları ve
Excel aktarımı bu dosyayla birebir uyumludur.

## 1. Firma Bilgileri → İşyeri (✅ uygulandı)

36 sütunun tamamı zorunludur. Karşılıkları `App\Models\Workplace::SETUP_FIELDS` içindedir ve işyeri
ekranındaki sekmelere dağılır. Kurulum tamamlanma oranı (ör. "%47 · 17 / 36") bu 36 alan üzerinden hesaplanır.

| Sekme | Excel sütunları |
|---|---|
| Genel Bilgiler | İş Yeri Tipi, Şirket Adı, İş Yeri Şube Adı, İş Yeri Numarası, İş Yeri Türü, NACE Kodu, Tehlike Sınıfı, ÇSGB İşkolu |
| Vergi | Vergi No, Vergi Dairesi, Vergi Dairesi Kullanıcı Kodu, Dijital VD Kullanıcı Adı / Şifre / Parola, E-Beyanname Şifresi |
| SGK | İş Yeri SGK Numarası, Bağlı SGK Müdürlüğü, SGK İş Yeri Yetkilisi, SGK Kullanıcı Adı (TCKN), SGK Bildirge Kullanıcı Adı (TCKN), SGK İş Yeri Kodu, SGK İş Yeri Şifresi, SGK Sistem Şifresi, E-Bildirge Yetkilisi |
| İŞKUR / TÜİK | İŞKUR Kullanıcı Adı Soyadı, İŞKUR Kullanıcı Kodu (TCKN), İŞKUR Şifre, İŞKUR Sicil Numarası |
| Emniyet & BES | Emniyet (Karakol) Bildirimi E-posta / Şifre, BES Firma Adı / Kullanıcı Adı / Şifre |
| Adres | Adres, İl, İlçe |

Notlar:
- Şifreler ve TCKN kullanıcı adları şifreli saklanır, ekranda maskelidir. "Göster" işlemi yetki ister
  ve her görüntüleme kayda geçer.
- Düzenlemede kayıtlı bir şifre boş bırakılırsa değişmez. Hiç girilmemiş bir şifre güncellemede de istenir.
- Dosyada olmayan eski alanlar isteğe bağlıdır: Ünvan (boşsa şirket unvanı), açılış tarihi, mahiyet,
  TÜİK, iletişim, adres ayrıntıları, sendika / TİS, risk sınıfı, MERSİS.
- Excel aktarımı müşterinin dosyasını olduğu gibi kabul eder:
  - Şirket, "Şirket Adı" (unvan veya kısa ad) ya da Şirket Numarası ile bulunur.
  - "MERKEZ İŞ YERİ" gibi büyük harfli liste değerleri ve "20 (GENEL İŞLER)" gibi işkolu değerleri tanınır.
  - "ÖRNEĞİN: …" örnek hücreleri ve "Kırmızı alanlar zorunludur." notu veri sayılmaz.

## 2. Personel Bilgileri → Personel kartı (✅ uygulandı)

Panel → Personel (liste, 8 sekmeli kart ve form), Panel → Tanımlar, Excel ile personel aktarımı.
Kodlar: `Employee` modeli, `EmployeeRules`, `EmployeeInput` (normalleştirme), `SaveEmployee`, `EmployeeFields` (aktarım sütunları).

- **Firma / SGK Firma / İş yeri şube:** Personel bir şirkete (Firma) ve SGK firmasındaki bir işyerine bağlanır.
  Excel'de bunlar ad ile verilir; şirket numarası, unvan veya kısa ad ile bulunur, şube adı ya da işyeri no ile eşleşir.
- **Eşleşme:** Firma içinde aynı sicil no varsa satır günceller. TCKN firma içinde benzersizdir (şifreli saklanır, hash ile kontrol edilir).
- **Şifreli alanlar:** TCKN, IBAN ve hesap no şifreli saklanır, ekranda maskelidir. "Göster" düzenleme yetkisi ister ve kayda geçer.
  Excel'de veya formda boş bırakılırlarsa mevcut değer korunur.
- **Türkçe yazımlar tanınır:**
  - Sayılar: "82.400,00", "%3" ve yüzde biçimli hücreler.
  - Evet / Hayır, ay adı ("Ocak"), "9:00" ve Excel saat hücreleri.
  - Belge türü "1" → "01".
  - Büyük-küçük harf farkları ve dosyadaki yazımlar ("Outsources", "EURO", "Derece 1"…).
- **Otomatik tanım oluşturma:** Tanımlarda olmayan birim, üst birim, unvan, pozisyon, iş ailesi, seviye ve masraf grubu
  aktarım onayında otomatik oluşturulur. Yeni birim yeni üst birime, yeni pozisyon yeni birime bağlanır.
- **Dosyadaki yardımcı satırlar veri sayılmaz:** Sayfanın ilk satırlarındaki açılır liste değerleri (Doktora, Lisans…) ve
  "Kırmızı alanlar zorunludur." notu atlanır. Aynı çalışma kitabı yüklenirse "Personel Bilgileri" sayfası otomatik seçilir.
- **Kararlar:**
  - İkinci Soyadı dosyada kırmızı olsa da isteğe bağlı tutuldu, çünkü çoğu kişide yok.
  - "Çalışan Tipi" sütunundaki liste dosyadaki gibi sözleşme süresidir (Belirsiz / Belirli / Kısmi / Çağrı üzerine).
  - Banka ve meslek kodu, Admin → Bordro Kodları listeleri yüklendiğinde öneri olarak gelir.
    Liste yüklenene kadar serbest metin girilir; meslek kodu 0000.00 biçiminde doğrulanır.

Prototipteki personel kartının 8 sekmesi bu sayfadan türetilmiştir. **Z** = zorunlu (kırmızı başlık).

| Sekme | Alanlar |
|---|---|
| Kimlik & İletişim | Sicil No **Z**, TC Kimlik No **Z**, Adı **Z**, Soyadı **Z**, İkinci Soyadı **Z**, Şirket E-posta, Kişisel E-posta **Z**, Cep Telefonu **Z**, İş Telefonu, Oturulan Adres, İl, İlçe |
| Kişisel | Doğum Tarihi **Z**, Cinsiyet **Z**, Medeni Hal, Eğitim Durumu, Mezuniyet Bölümü, Askerlik |
| İstihdam & Organizasyon | Firma **Z**, SGK Firma **Z**, İş Yeri Şube Adı **Z**, Yaka, İş Ailesi, Birim, Üst Birim **Z**, Görev Tipi, Unvan **Z**, Pozisyon **Z**, Seviye, İzin Yönetici Sicil No **Z**, Fonksiyonel Yönetici Sicil No, İşe Giriş **Z**, Kıdeme Esas **Z**, İzne Esas Tarihi **Z** |
| SGK | SGK Meslek Kodu **Z**, Sigorta Kolu **Z**, SGK Statü **Z**, Çalışan Tipi **Z**, Görev Kodu **Z**, SGK Belge Türü **Z** |
| Ücret & Bordro | Ücret Periyodu **Z**, Para Birimi **Z**, Ücret Tipi **Z**, Ücret **Z**, Asgari Ücretli **Z**, Asgari Ücret Vergi İstisnasına Tabi mi **Z**, Kümülatif GV Matrahı **Z**, Vergi İstisnası Başlangıç Ayı **Z**, Bir / İki Önceki Dönem Devreden SGK Matrahı **Z**, Otomatik BES Oranı **Z**, Ar-Ge İndirim Oranı |
| Banka | Banka Adı **Z**, Şube Adı **Z**, IBAN **Z**, Hesap No **Z** |
| Engellilik & Masraf | Engellilik Derecesi, Engelli GV İndiriminden Faydalanıyor mu, Engellilik Bitiş Tarihi, Masraf Grubu, Masraf Grubu Oranı |
| Çalışma Düzeni | Çalışma Modeli **Z**, Sözleşme Türü **Z**, Vardiyalı mı **Z**, Mesai Başlangıç / Bitiş **Z**, Hafta Tatili **Z**, Kalan Yıllık İzin Hakkı **Z** |

Dosyadaki seçenek listeleri:
- Eğitim: Doktora, Yüksek Lisans, Lisans, Ön lisans, Lise, Orta Okul, İlkokul, Okur yazar, Okur yazar değil
- Askerlik: Yapıldı, Yapılmadı, Tecilli
- Görev Tipi: Kadrolu, Stajyer, Outsource
- Sigorta Kolu: Tüm Sigorta Kolları (Zorunlu), Sosyal Güvenlik Destek Primi, Çırak, Stajyer
- SGK Statü: Normal, Emekli, Kapsam dışı
- Görev Kodu: İşçi, İşveren veya vekili, Çıraklar ve stajer öğrenciler, Diğerleri
- SGK Belge Türü: 1, 2, 4, 5, 6, 7, 12, 13, 14, 19, 20, 21, 22, 23, 24, 28–37, 39, 41–43, 46–55, 90–92
- Çalışan Tipi (sözleşme): Belirsiz Süreli, Belirli Süreli, Kısmi Süreli, Çağrı Üzerine
- Ücret Periyodu: Aylık, Haftalık, Günlük, Saatlik
- Para Birimi: TRY, EURO, USD, GBP
- Ücret Tipi: Net, Brüt
- Ar-Ge İndirim Oranı: 0, %80, %90, %95, %100 (4691)
- Engellilik Derecesi: Derece 1, 2, 3
- Çalışma Modeli: Evde Çalışma, Ofiste Çalışma, Hibrit
- Sözleşme Türü: Tam Zamanlı, Kısmi Süreli
- Hafta Tatili: Pazar, Cumartesi & Pazar, Sabit Değil / Haftanın bir günü
- Evet / Hayır alanları: Asgari Ücretli, Asgari ücret vergi istisnası, Vardiyalı

Birim, Üst Birim, İş Ailesi, Unvan, Pozisyon, Seviye, Masraf Grubu, Banka ve SGK Meslek Kodu
**Tanımlar** modülünden seçilir (prototip: Tanımlar ekranı).
