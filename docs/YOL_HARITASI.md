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
- ⏸ E-posta gönderimi (şifre sıfırlama, davet, onay/red bildirimi) — SMTP bilgileri sonra; şu an `log` sürücüsü

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

### 2.2 Kullanıcılar
- ✅ Liste (tip, durum, arama filtreleri)
- ✅ Kullanıcı oluşturma (Bordro Uzmanı / Müşteri Kullanıcısı / Super Admin)
- ✅ Düzenleme, pasife alma / aktifleştirme
- ✅ Şifre sıfırlama (geçici şifre; e-posta ile bağlantı gönderimi e-posta altyapısıyla birlikte)
- ✅ Yetki atama: firma / şirket / işyeri kapsamı + tek tek yetkiler veya şablon
- ✅ Yetki kaldırma
- ✅ Yetki şablonları yönetimi (oluştur / düzenle / sil)

### 2.3 Web Sitesi (landing içeriği)
- ✅ Landing sayfası bölümleri (başlık, hizmetler, iletişim vb.) admin'den düzenlenebilir
- ✅ Landing sayfası tasarımı (hero, hizmetler, hakkımızda, iletişim, SEO)

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

## 3. Panel (panel.siteadi.com)

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

### 3.3 Firma kullanıcıları (Word: 1. Aşama – Müşteri Firma Kullanıcısı)
- ✅ Müşteri, kendi firmasına kullanıcı ekler ve firma / şirket / işyeri düzeyinde yetkilendirir (yalnızca sahip olduğu yetkileri verebilir; kendi ve HRD yetkilerine dokunamaz)

### 3.4 Çalışan Portalı (Word: 1. Aşama – Çalışan)
- ✅ Karar: ayrı alan adı yok, çalışanlar panel.siteadi.com üzerinden giriş yapar (yalnızca kendi bilgileri)
- ⬜ Çalışan modeli, girişi; kendi bilgileri ve bordrolarını görüntüleme/indirme
  (bordro verisi hesaplama aşamasına bağlı)

## 4. Bordro hesaplamaları
- ⬜ Word dosyası tamamlandıktan sonra detaylandırılacak
