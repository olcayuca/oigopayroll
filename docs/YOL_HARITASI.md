# HRD Bordro – Yol Haritası

Kaynak: *HRD Bordro Sistemi 1. AŞAMA FİRMA İŞYERİ BİLGİLERİ.docx* + görüşmede alınan kararlar.
Durum: ✅ bitti · 🔄 devam ediyor · ⬜ yapılacak · ⏸ ertelendi · ❓ karar bekliyor

Sıra: **1) Altyapı → 2) Admin → 3) Panel (Word dosyasının tamamı) → 4) Bordro hesaplamaları**

---

## Alınan kararlar

- Hiyerarşi: **Firma (müşteri hesabı) → Şirket → İşyeri → Çalışan**
- Alan adları: `siteadi.com` landing · `admin.siteadi.com` yalnızca Super Admin · `panel.siteadi.com` müşteri kullanıcıları + HRD personeli
- Admin: firmalar, kullanıcılar, web sitesi (landing içeriği), sistem ayarları
- Panel: şirket, işyeri, çalışan, bordro işleri; seçili (aktif) firma bağlamında
- Yetki: kullanıcı + kapsam (firma/şirket/işyeri) + işlem; yetki şablonları opsiyonel
- Risk Sınıfı: yönetilebilir seçim listesi
- Excel şablonlarını sistem üretir
- Kullanıcı aidiyeti: her müşteri kullanıcısı tek bir firmaya (ana firma) aittir ve yalnızca o firmanın işlerini yapar.
- Firmalar arası yetki: bir firma başka firmaları yönetebilir (alt firma açarak veya "işlerimi yönet" daveti ile). Bağlantı kendiliğinden kimseye erişim vermez; yönetici firma kendi kullanıcılarını yönettiği firmaya tek tek atar. Atanan kullanıcının yetkisi, bağlantıda izin verilen yetkilerle sınırlıdır. Bağlantı kaldırılınca bu atamalar da silinir.
- Veritabanı MySQL, arayüz Flux + Livewire, dil Türkçe

---

## 1. Altyapı

- ✅ Veri modeli: firma, şirket, işyeri, referans tablolar (il, işkolu, sektör, risk sınıfı)
- ✅ Kullanıcı tipleri (Super Admin, Bordro Uzmanı, Müşteri Kullanıcısı)
- ✅ Kapsamlı yetki sistemi + yetki şablonları (kod tarafı)
- ✅ Firma onay akışı (müşteri başvurusu → HRD onay/red; HRD doğrudan aktif firma)
- ✅ Şirket / işyeri kayıt kuralları (VKN, TCKN, MERSİS, SGK sicil doğrulaması, tekillik)
- ✅ İşyeri şifreleri şifreli saklama + görüntüleme logu
- ✅ Excel: şablon üretimi, önizleme + hata kontrolü, onaylı toplu kayıt
- ✅ Üç alan adlı portal yapısı, https zorunluluğu, Türkçe arayüz
- ✅ Panelde aktif firma seçimi
- ✅ Git deposu (github.com/olcayuca/oigopayroll)
- ✅ İlçe listesi: 973 ilçe, resmi ilçe kodlarıyla (iki bağımsız kaynakla doğrulandı)
- ⏸ E-posta gönderimi (şifre sıfırlama, davet, onay/red bildirimi) — SMTP bilgileri sonra; şu an `log` sürücüsü. Bildirimler hazır, SMTP gelince e-postayla da gider

## 2. Admin (admin.siteadi.com)

### 2.1 Firmalar
- ✅ Liste, arama, durum filtresi, onay bekleyenler üstte
- ✅ Onayla / gerekçeli reddet
- ✅ HRD adına aktif firma oluşturma
- ✅ Firma detayı: şirketler, yetkili kullanıcılar
- ✅ Firma adını düzenleme
- ✅ Pasife alma / yeniden aktifleştirme
- ✅ Firma detayından müşteri kullanıcısı oluşturma / mevcut kullanıcıya yetki verme

- ✅ Firma temel alanları: unvan, vergi no / dairesi, yetkili kişi, telefon, e-posta, adres
- ✅ Excel ile toplu firma oluşturma (şablon → yükle → önizleme → onay)
- ✅ Firmalar arası yetki: A firması B firmasını yönetir (admin: iki yönde; panel: B'nin yetkilisi A'yı vergi no ile ekler)

- ✅ Kullanıcı–firma aidiyeti: her müşteri kullanıcısı tek bir firmaya aittir; başka firmaya sızma yok
- ✅ Yönetici firma, yönettiği firmaya kendi kullanıcılarını tek tek atar (bağlantı yetkileriyle sınırlı)
- ✅ Alt firma: firma kendi altında firma açar, otomatik yönetim bağlantısı kurulur (HRD onayına düşer)
- ✅ Firma belgeleri: vergi levhası, imza sirküleri, sicil gazetesi, faaliyet/SGK belgesi, vekaletname, sözleşme…; firma geneli veya şirkete ait;
  geçerlilik tarihi ve 30 gün önceden uyarı; private depoda, yetkili indirme (loglu); panel → Belgeler, admin → firma detayı → Belgeler, Admin → Belge Takibi
- ✅ Sözleşmeler (yalnızca admin): sözleşme no, süre, ücret tipi/tutarı, fesih bildirim süresi, otomatik yenileme (gece görevle aynı süre uzatılır),
  ilgili belge, fesih; durum aktif / bitiyor / sona erdi / feshedildi; Admin → Sözleşmeler ve firma detayı → Sözleşmeler

### 2.2 Kullanıcılar
- ✅ Liste (tip, durum, arama filtreleri)
- ✅ Kullanıcı oluşturma (Bordro Uzmanı / Müşteri Kullanıcısı / Super Admin)
- ✅ Düzenleme, pasife alma / aktifleştirme
- ✅ Şifre sıfırlama (geçici şifre; e-posta ile bağlantı gönderimi e-posta altyapısıyla birlikte)
- ✅ Yetki atama: firma / şirket / işyeri kapsamı + tek tek yetkiler veya şablon
- ✅ Yetki kaldırma
- ✅ Yetki şablonları yönetimi (oluştur / düzenle / sil)
- ✅ Uzman dağılımı (Admin → Uzman Dağılımı): firmaya sorumlu bordro uzmanı; atama "Bordro Uzmanı" yetkisini otomatik verir,
  değişince eski uzmanın firma yetkisi kalkar; uzman iş yükü (firma/şirket/işyeri), sorumlusuz firma uyarısı, toplu devir;
  müşteri panelde sorumlu uzmanını görür
- ✅ Çöp kutusu (panel + admin): silinen şirket/işyerleri, kimin ne zaman sildiği; geri alma (silme yetkisiyle, hiyerarşi sırasıyla);
  kalıcı silme yalnızca admin'de (önce işyerleri, yetkiler temizlenir, işlem kaydı kalır)
  - ⬜ Çalışan/bordro verisi gelince: kalıcı silme bağlı kayıt varken engellenecek, yasal saklama süreleri uygulanacak
- ✅ Raporlar (Admin → Raporlar): özet (firma durumları, tehlike sınıfları, il dağılımı, son 6 ay) ve Excel dışa aktarma
  (firmalar, kullanıcılar, şirketler, işyerleri, işlem kayıtları); panelde Şirketler/İşyerleri "Excel İndir".
  Şifre ve kimlik alanları hiçbir zaman dışa aktarılmaz; her dışa aktarma işlem kaydına yazılır.
- ✅ Excel ile toplu güncelleme: içe aktarma "ekle veya güncelle" çalışır (şirket no / şirket no + işyeri no eşleşmesi);
  yalnızca dosyadaki sütunlar değişir, boş/eksik şifre sütunları korunur; önizlemede Yeni / Güncellenecek / Değişiklik yok;
  güncelleme için düzenleme yetkisi gerekir, başka firmanın ve çöp kutusundaki kayıtlar reddedilir

### 2.3 Web Sitesi (landing içeriği)
- ✅ Landing sayfası bölümleri (başlık, hizmetler, iletişim vb.) admin'den düzenlenebilir
- ✅ Landing sayfası tasarımı (hero, hizmetler, hakkımızda, iletişim, SEO)

### 2.3.1 Duyurular
- ✅ Admin → Duyurular: bilgi / uyarı / kritik; hedef herkes, müşteriler, HRD personeli veya seçili firmalar; başlangıç/bitiş;
  panelde sayfa üstünde gösterilir, kullanıcı kapatabilir (kritik kapatılamaz), kaç kişinin kapattığı görünür
- ✅ Duyurular sayfası (panel → Yönetim → Duyurular, prototip 34-duyurular): kategori filtresi (Mevzuat, Sistem, Bakım, Yeni Özellik),
  sabitlenenler üstte, okunmamışlar işaretli, "Tümünü okundu say", bağlantı düğmesi (panel yolu veya https), Güncel / Arşiv (son 1 yıl)
- ✅ Üst şerit tıklanınca sayfada o duyuruyu açar ve okundu sayar; menüde okunmamış sayısı; "Üst şeritte gösterme" sayfadan
- ✅ Admin: kategori, sabitleme, bağlantı, "Bildirim gönder" (yayına girince hedef kullanıcılara zil + e-posta, planlı olanlar
  başlangıçta; her duyuru bir kez); listede okuyan / şeritten kaldıran sayısı ve bildirim durumu
- ✅ Bildirim tercihlerinde "Duyurular" kategorisi; kritik duyurular kapatılamaz

### 2.3.2 Bildirimler
- ✅ Sistem içi bildirimler: kenar çubuğunda zil + Bildirimler sayfası (okunmamış/tümü, tümünü okundu say)
- ✅ Konular: firma onay/red (firma yetkilileri + uzman), onay bekleyen firma (süper adminler), KVKK yanıtı, sorumlu uzman ataması,
  belge süresi doluyor/doldu ve sözleşme bitiyor (her gün 08:00, her durum için bir kez)
- ⏸ E-posta: altyapı hazır; MAIL_MAILER SMTP yapılınca aynı bildirimler e-postayla da gider (kuyruk önerilir)

### 2.4 Sistem Ayarları
- ✅ Sektör listesi yönetimi
- ✅ Risk sınıfı listesi yönetimi
- ✅ Genel ayarlar (site adı, iletişim bilgileri vb.)
- ✅ İşyeri şifre görüntüleme logları

### 2.6 Bordro tanımları
- ✅ Yasal parametreler: yürürlük tarihli; 2026 değerleri kaynaklarıyla yüklendi (admin doğrulamalı)
- ✅ Bordro kodları: belge türleri (45), eksik gün nedenleri, işten çıkış kodları, teşvik kanunları (7 doğrulanmış kod); meslek kodları ve bankalar Excel ile yüklenir
- ✅ Çalışma takvimi: sabit tatiller her yıl otomatik, dini bayramlar 2026–2027 yüklendi, yarım günler
- ✅ KVKK modülü (Admin → KVKK)
  - Sürümlü aydınlatma / açık rıza metinleri (yer tutucu TASLAK — hukuki onayla yeni sürüm yayımlanmalı)
  - Herkesten onay: iki portalda da giriş sonrası güncel sürüm için karar zorunlu; yeni sürüm herkese yeniden sorulur.
    Aydınlatma "okudum" ile onaylanır; açık rıza isteğe bağlıdır (reddedilebilir, Ayarlar → KVKK'dan geri alınır). Kararlar IP/tarih ile saklanır.
  - Sistem üzerinden başvuru (md. 11): Ayarlar → KVKK → Başvurularım; admin 30 gün süre takibi, yanıt, JSON veri dökümü, hesabı anonimleştirme
  - ⬜ Çalışanlar: çalışan portalı gelince aynı onay akışına bağlanacak

### 2.5 Güvenlik
- ✅ Olay kayıtları (audit log): giriş/çıkış, firma, yetki, kullanıcı, ayar, aktarım, şifre görüntüleme işlemleri
- ✅ Başarısız giriş kayıtları (e-posta, IP, tarayıcı, sebep) ve kilitlenmeler
- ✅ Aktif oturumlar ve oturum sonlandırma
- ✅ Güvenlik ayarları: admin için 2FA zorunluluğu, IP kısıtı, hareketsizlikte çıkış, giriş deneme sınırı
- ✅ Güvenlik başlıkları (clickjacking, MIME sniffing, referrer, HSTS)
- ✅ Destek görünümü (kullanıcı yerine geçme): yalnızca süper admin; tek kullanımlık 60 sn'lik anahtar, panelde ayrı oturum,
  en fazla 30 dk, her sayfada uyarı + bitir; hesap ayarları ve KVKK kararları kapalı; bu sürede yapılan işlemler adminin adıyla kayıtlı

### 2.7 Sistem Sağlığı
- ✅ Kontroller: PHP/Laravel sürümü, ortam, hata ayıklama, uygulama anahtarı, HTTPS çerezleri, admin 2FA, IP kısıtı,
  veritabanı bağlantı/boyut/bekleyen güncelleme, disk alanı, günlük boyutu, zamanlayıcı, kuyruk, e-posta, son yedek
- ✅ Yedekleme: `php artisan hrd:yedek`, her gece 02:30 otomatik, gzip, son 14 yedek; private depoda, panelden indirilemez
- ✅ Son hatalar (uygulama günlüğünden)
- ⬜ Canlı sunucu: cron'a `* * * * * php artisan schedule:run`, `.env`'e `BACKUP_MYSQLDUMP` yolu; yedeklerin sunucu dışına kopyalanması

## 3. Panel (panel.siteadi.com)

### 3.0 Panel tasarımı (kaynak: `export/` prototipi, kurallar: ARAYUZ_KURALLARI.md §3)
- ✅ Tasarım sistemi: renk tokenları, Manrope, `x-panel.*` bileşen kütüphanesi, Flux bileşenlerinin panel teması
- ✅ Kabuk: koyu kenar menü, üst çubukta genel arama (şirket / işyeri / SGK sicil), duyuru şeridi, firma seçici
  (firmalar + şirket kısayolları), bildirim zili (tümünü okundu say), kullanıcı menüsü, mobil menü
- ✅ Gösterge paneli: KPI kartları, kurulum adımları, firma yapısı, uyarılar & eksik veri, önemli tarihler (beyanname + resmi tatiller)
- ✅ Şirketler / İşyerleri listeleri (sayaçlar, segment filtre, kurulum ilerleme çubuğu), detay ve form sayfaları
- ✅ İşyeri kurulum tamamlanma oranı: bordro için gerekli isteğe bağlı alanlar (SGK sicil, İŞKUR/TÜİK, iletişim, risk/işkolu)
- ✅ Panel giriş ekranı (bölünmüş düzen) ve hata sayfaları (403, 404, 419, 429, 500, 503)
- ⬜ Prototipteki diğer ekranlar ilgili modüllerle birlikte: Personel, Tanımlar, Bordro Dönemleri, İzinler,
  Avans & Borçlar, Toplu İşlemler, Hesaplamalar, Raporlar, İşlem Geçmişi, Abonelik, Destek, Duyurular, Ayarlar, Kurulum Sihirbazı, Çalışan Portalı
- ⬜ Kalan mevcut sayfaların tam yeniden düzeni (Kullanıcılar, Firma Erişimleri, Belgeler, Çöp Kutusu, Excel aktarımı, Ayarlar) —
  şu an panel temasını ve kart görünümlü tabloları alıyorlar
- ✅ İşyeri ekranları ve Excel aktarımı müşteri kurulum dosyasına (Firma Bilgileri, 36 zorunlu alan) uyarlandı:
  yeni alanlar (NACE, SGK kullanıcı adı, Dijital VD, Emniyet bildirimi, BES), sekmeler Genel / Vergi / SGK / İŞKUR / Emniyet & BES / Adres,
  müşteri dosyası olduğu gibi yüklenebilir — ayrıntı: docs/KURULUM_DOSYASI.md
- ✅ Tanımlar (firma bazlı): üst birim, birim, iş ailesi, unvan, pozisyon, seviye, masraf grubu; kullanılan tanım silinemez, pasife alınır
- ✅ Personel: liste (durum / eksik veri filtreleri), 8 sekmeli kart ve form, kayıt tamamlanma; TCKN / IBAN / hesap no şifreli;
  yeni yetkiler employee.view/create/update/delete/import (mevcut kullanıcılara işyeri yetkilerinin karşılığı verildi)
- ✅ Excel ile personel: kurulum dosyasının Personel Bilgileri sayfası olduğu gibi; sicil no ile güncelleme; eksik tanımlar otomatik — docs/KURULUM_DOSYASI.md §2
- ✅ Kurulum Sihirbazı: şirket → işyeri → tanımlar → personel → özet, gerçek durumla; gösterge panelinde "Sihirbazla kur"
- ✅ İşlem Geçmişi (panel → Yönetim): her kayıt firma / şirket / şube kapsamıyla; güncellemelerde alan bazlı "eski → yeni"
  (şifre, TCKN, IBAN yalnızca "değiştirildi"); Excel aktarımı ve destek görünümü kaynağı; şirket, şube, kullanıcı, modül, tarih,
  kayıt filtreleri; Excel çıktısı; şirket / işyeri / personel / kullanıcı sayfalarından geçmişe bağlantı.
  Yetki: "İşlem geçmişini görüntüleme" (firma geneli ya da yalnızca yetkili şirket / şube); kullanıcı yönetimi yetkisi olanlara verildi
- ✅ Kurulum asistanı (OigoAsistan, sağ alttaki yüzen düğme): yalnızca kurulum soruları (şirket, işyeri, tanımlar, personel,
  kurulum dosyası / Excel aktarımı, sihirbaz); bordro hesaplama ve mevzuat sorularını uzmana yönlendirir. Claude (Anthropic API),
  `ANTHROPIC_API_KEY` yoksa görünmez. Firmanın kurulum durumunu (eksik alanlar, sicil no) kullanıcının görebildiği kadarıyla bilir;
  ad, TCKN, IBAN, şifre gönderilmez, yazılan TCKN / IBAN maskelenir. Sohbet oturumda, firma bazlı; kullanıcı başına 5 dakikada 20 soru
- ✅ Asistan kısayolları (açılır pencere): **Not ekle** (kişisel, firma bazlı, şifreli saklanır; düzenle / sil),
  **Hatırlatıcı** (başlık, zaman, not; hızlı seçim 1 saat sonra / yarın / Pazartesi; zamanı gelince bildirim ziline düşer —
  zamanlayıcı her dakika ve zil yoklamasında teslim eder, her hatırlatma bir kez), **Hesap makinesi** (tarayıcıda; Türkçe sayı
  biçimi, yüzde, klavye, son 4 işlem, sonucu kopyala). API anahtarı yokken asistan yalnızca kısayollarla görünür
- ✅ Personel işten çıkış: çıkış tarihi, SGK işten çıkış kodu, not; kayıt saklanır, "Çıkışı geri al" ile iptal
- ✅ Personel Excel çıktısı: kurulum dosyası sütunlarıyla (TCKN / IBAN / hesap no hariç), düzeltilip yeniden yüklenebilir;
  admin genel raporlarında personel yok
- ✅ Çöp kutusunda personel: geri alma (işyeri / şirket geri alındıktan sonra), admin kalıcı silme;
  personeli olan işyeri / şirket silinemez
- ✅ Tanımlar için Excel aktarımı: tüm türler tek sayfada, tür + kod (yoksa ad) ile güncelleme, eksik üst tanım oluşturulur
- ✅ SGK meslek kodu, Admin → Bordro Kodları'na meslek listesi yüklendiyse listeye göre doğrulanır.
  Meslek kodları ve bankalar listesi resmi kaynaktan (İŞKUR meslek listesi / E-Bildirge, TCMB) Excel ile yüklenmeli — henüz boş
- ✅ Kurulum onayı: tüm adımlar tamamlanınca sorumlu bordro uzmanı sihirbazın özet adımında onaylar; firma kullanıcılarına bildirim,
  gösterge panelinde "onay bekleniyor", onaydan sonra işyeri / şirket / personel formlarında uyarı; onay kaldırılabilir
- ⬜ Gerçek müşteri verisiyle deneme aktarımı (eldeki KURULUM DOSYASI boş şablon)
- ⬜ KVKK aydınlatma onayı (çalışan portalıyla)
- ❓ Üst çubuktaki bağlam seçici: prototip firma içinde **şirket** seçtiriyor; şimdilik **firma** seçici + şirket kısayolları

### 3.1 Şirketler (Word: 3. Aşama)
- ✅ Şirket listesi (arama, işyeri sayısı)
- ✅ Manuel şirket oluşturma (7 zorunlu + diğer alanlar) ve düzenleme
- ✅ Excel ile toplu şirket: şablon indir → yükle → kontrol/önizleme → onayla
- ✅ İşyeri olmayan şirket uyarısı

### 3.2 İşyerleri (Word: 4. Aşama)
- ✅ İşyeri listesi (şirkete göre)
- ✅ Manuel işyeri oluşturma / düzenleme (tüm alanlar, il → ilçe filtreli, elle giriş)
- ✅ Şifre alanları maskeli, yetkiyle görüntüleme (loglu)
- ✅ Excel ile toplu işyeri: şablon indir → yükle → kontrol/önizleme → onayla

### 3.2.3 Abonelik / Lisans (panel → Yönetim → Abonelik, admin → Faturalar)
- ✅ Fatura sözleşmeden (Admin → Sözleşmeler): her dönem başında otomatik; aylık sabit, aylık çalışan başı (aktif personel sayısı),
  yıllık, tek seferlik; ücret KDV hariç, KDV oranı config/billing.php (varsayılan %20); numara HRD-YYYY-000001
- ✅ Kart: iyzico'nun ödeme sayfasında (Checkout Form, 3D Secure) "Kartımı kaydet" ile; sistemde yalnızca iyzico token'ları (şifreli)
  ve kartın markası / son 4 hanesi. Fatura yokken kart eklemek için 1 TL çekilip hemen iade edilir
- ✅ Otomatik ödeme: vadesi gelen fatura kayıtlı karttan çekilir (her gün 07:00); başarısızsa 3 gün arayla en fazla 3 deneme,
  firma yöneticilerine ve HRD'ye bildirim; firma otomatik ödemeyi kapatabilir; "Kartla öde" ile elle ödeme
- ✅ Admin → Faturalar: ödenmemiş / başarısız / ödenen; karttan şimdi çek, havale ile ödendi, iptal; faturaları şimdi oluştur
- ✅ Yetki: firma düzenleme yetkisi olan müşteri kullanıcıları ve HRD
- ⬜ Canlıya alma: iyzico üye işyeri hesabı, IYZICO_API_KEY / IYZICO_SECRET_KEY, önce sandbox'ta uçtan uca deneme;
  kayıtlı kartla 3D'siz (tekrarlayan) çekim izni iyzico'dan açtırılmalı
- ⬜ e-Fatura / e-Arşiv entegrasyonu (resmi fatura), fatura PDF'i; paket / limit tanımları

### 3.2.2 Ayarlar (panel → Yönetim → Ayarlar, prototip 35-ayarlar)
- ✅ Şirket Bilgileri: unvan / VKN (HRD yönetir, salt okunur), yetkili, telefon, e-posta, KEP, web, MERSİS, yazışma adresi
- ✅ Bordro Varsayılanları: ödeme günü, ücret tipi, net yuvarlama, FM çarpanı, aylık gün, Hazine indirimi, asgari ücret istisnası,
  otomatik BES — ücret tipi / istisna / BES yeni personel formunu doldurur, diğerleri bordro hesaplamasında kullanılacak
- ⏸ Onay Akışı: bordro modülüyle (YAKINDA)
- ✅ Bildirimler: kullanıcı bazında kategori × (e-posta, panel); KVKK ve zorunlu bildirimler kapatılamaz
- ✅ Güvenlik: firma için iki adımlı doğrulama zorunluluğu (zorunluysa kullanıcı önce 2FA kurar; destek görünümü muaf),
  hassas veri maskeleme bilgisi, kendi hesabının 2FA durumu ve güvenlik sayfası
- ✅ Marka & Logo: firma logosu (yetkili erişimli), üst çubuktaki firma seçicide görünür; PDF üst bilgi önizlemesi
- ✅ Tercihlerim: ad, e-posta, görünüm (açık / koyu / sistem); şifre ve KVKK sayfalarına geçiş
- Firma bölümleri yalnızca firma düzenleme yetkisiyle değişir; diğer kullanıcılar salt okunur görür

### 3.2.1 Destek (panel → Yönetim → Destek, admin → Destek Talepleri)
- ✅ Firma kullanıcısı talep açar: konu, kategori, modül, öncelik, açıklama, ek dosya (PDF / görsel / Excel, en fazla 10 MB)
- ✅ Yeni talep: HRD süper adminlerine ve firmanın sorumlu bordro uzmanına bildirim + e-posta; Sistem Ayarları'ndaki
  destek e-posta adresine de e-posta; açan kişiye "talebiniz alındı"
- ✅ Karşılıklı yazışma: HRD admin portaldan (sorumlu uzman panelden) yanıtlar → müşteriye bildirim + e-posta, durum "Yanıt bekleniyor";
  müşteri yanıtlar → talep yeniden "Açık", ilgilenen uzmana bildirim + e-posta
- ✅ Durumlar: Açık, İnceleniyor, Yanıt bekleniyor, Çözüldü, Kapalı; HRD durum ve ilgilenen kişiyi değiştirir, açan kişi kapatabilir
- ✅ Görünürlük: kullanıcı kendi taleplerini, firma yöneticisi ve HRD firmanın tüm taleplerini görür; admin menüsünde bekleyen sayısı
- ⬜ E-posta gönderimi için canlıda SMTP (MAIL_MAILER) ayarlanmalı; ayarlanana kadar yalnızca sistem içi bildirim
- ⬜ E-postaya yanıt vererek talebe mesaj ekleme (gelen e-posta işleme) — ileride

### 3.3 Firma kullanıcıları (Word: 1. Aşama – Müşteri Firma Kullanıcısı)
- ✅ Müşteri, kendi firmasına kullanıcı ekler ve firma / şirket / işyeri düzeyinde yetkilendirir (yalnızca sahip olduğu yetkileri verebilir; kendi ve HRD yetkilerine dokunamaz)

### 3.4 Çalışan Portalı (Word: 1. Aşama – Çalışan)
- ✅ Karar: ayrı alan adı yok, çalışanlar panel.siteadi.com üzerinden giriş yapar (yalnızca kendi bilgileri)
- ⬜ Çalışan modeli, girişi; kendi bilgileri ve bordrolarını görüntüleme/indirme
  (bordro verisi hesaplama aşamasına bağlı)

## 4. Bordro hesaplamaları
- ⬜ Word dosyası tamamlandıktan sonra detaylandırılacak
