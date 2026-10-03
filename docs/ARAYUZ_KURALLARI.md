# Arayüz Kuralları

Bu kurallar tüm admin ve panel ekranları için geçerlidir. Yeni ekran yapılırken ve mevcut ekran
değiştirilirken uyulur.

## 1. Bilgi grupları sekmelere bölünür (zorunlu)

Bir detay veya form sayfasında birden fazla önemli bilgi grubu varsa bunlar **alt alta
dizilmez, sekmelere (tab) bölünür**. Böylece her grup bütün olarak görünür, bilgi bölünmez ve
sayfa uzamaz.

- Sayfanın üstünde yalnızca **başlık, durum rozeti, ana işlem butonları ve kritik uyarılar**
  (onay bekliyor, pasif, işyeri yok vb.) sekmelerin dışında kalır.
- Her bilgi grubu (ör. Genel Bilgiler, Kullanıcılar, Şirketler, Firmalar Arası Yetki) ayrı
  bir sekmedir. Liste içeren sekmelerde başlıkta kayıt sayısı gösterilir.
- Açık sekme adres çubuğunda tutulur (`?sekme=...`); sayfa yenilenince veya bağlantı
  paylaşılınca aynı sekme açılır.
- Formlarda da aynı kural geçerlidir. Kaydetmede hata olursa hatalı alan içeren sekmeler
  işaretlenir ve ilk hatalı sekme otomatik açılır.
- Bileşen: `<x-tabs>` (resources/views/components/tabs.blade.php); hatalı sekmeler `:invalid` ile işaretlenir.

## 2. Genel

- Arayüz metinleri Türkçedir.
- Tek bir grup içeren kısa sayfalarda sekme kullanılmaz.

## 3. Panel tasarım sistemi (panel.siteadi.com)

Kaynak: `export/` klasöründeki tasarım prototipi (OigoPayroll). Admin portalı şimdilik eski
Flux görünümünde kalır; aşağıdakiler yalnızca panel içindir.

**Kabuk** — `resources/views/layouts/panel.blade.php` (`layouts/app.blade.php` panel alan adında buna yönlenir):
koyu lacivert kenar menü (KURULUM / OPERASYON / YÖNETİM; henüz olmayan modüller "YAKINDA"),
üst çubukta genel arama (`/` kısayolu), duyuru şeridi, firma seçici, bildirim zili ve kullanıcı menüsü.
Giriş ekranları `layouts/auth/panel.blade.php` (bölünmüş düzen), hata sayfaları `resources/views/errors/`.

**Renkler ve yazı** — `resources/css/app.css` içindeki tokenlar: `brand` #163B66 (ana buton), `mint` #11A795
(vurgu, aktif sekme çizgisi), `ink` metin, `muted` ikincil metin, `line` kenarlık, `canvas` sayfa zemini,
durum renkleri `st-green|amber|red|gray|navy|blue` (+ `-bg`, `-dot`). Yazı tipi Manrope.
Panelde `<html class="oigo">` vardır; Flux bileşenleri bu sınıf altında panel renklerini alır.

**Bileşenler** (`resources/views/components/panel/`):

| Bileşen | Kullanım |
|---|---|
| `x-panel.page-header` | Sayfa yolu, başlık, alt başlık, `badge` / `actions` slotları, detayda `back` + `initials` |
| `x-panel.stat` / `x-panel.kpi` | Liste üstü sayaçlar / gösterge paneli kartları |
| `x-panel.table` + `th` / `tr` / `td` | Liste kartı: `toolbar`, `head`, `empty` slotları; `:paginate` ile sayfalama; `tr :href` tüm satırı tıklanır yapar |
| `x-panel.search`, `x-panel.segmented` | Liste araç çubuğu: arama ve segment filtre (`?durum=`) |
| `x-panel.badge` | Durum rozeti (Flux renk adlarını da kabul eder) |
| `x-panel.card`, `x-panel.alert`, `x-panel.empty` | Kart, uyarı kutusu, boş durum |
| `x-panel.details`, `x-panel.record-meta` | Detay sekmelerinde alan listesi, sağ sütunda kayıt bilgisi |
| `x-panel.form-footer` | Sekmeli formda "‹ Önceki · Sekme 2/5 · Sonraki ›" |

**Sayfa kalıpları**
- Liste: page-header → 4'lü stat satırı → `x-panel.table` (arama + segment + tablo + sayfalama).
- Detay: page-header (geri, avatar, rozetler, Sil / Düzenle) → solda sekmeli kart, sağda 290px sütun
  (tamamlanma + kayıt bilgisi).
- Form: page-header'da Vazgeç / Kaydet (`form="..."` ile karttaki forma bağlı) → sekmeli kart → form-footer.
  Hatalı sekmeler kırmızı noktalı, üstte hata özeti.

**Teknik notlar**
- Livewire sayfa / bileşen görünümleri tek bir kök HTML elemanıyla başlamalı (kökte `@if` olmaz:
  Livewire `@if` bloklarına yorum işaretçisi ekler ve kök etiket bulunamaz).
- HTML etiketinin içinde (`<a @if(...) ... @endif>`) `@if` kullanılmaz; aynı işaretçiler etiketi bozar.
  Koşullu öznitelik için `@class`, `@disabled` ya da değer içinde üçlü ifade kullanılır.
- Aynı dosyada satır içi `@php(...)` ile `@php ... @endphp` bloğu birlikte kullanılmaz (Blade derleyicisi karıştırır).
- Yeni Tailwind sınıfları için `npm run build` (veya geliştirme sırasında `npm run dev`).
- Yerelde tasarımı denemek için: `php artisan db:seed --class=PanelDemoSeeder` (yalnızca local).
