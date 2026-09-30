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
