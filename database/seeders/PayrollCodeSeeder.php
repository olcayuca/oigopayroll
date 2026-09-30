<?php

namespace Database\Seeders;

use App\Enums\CodeList;
use App\Models\PayrollCode;
use Illuminate\Database\Seeder;

/**
 * Official payroll code lists (compiled 30.09.2026). Only missing codes are created;
 * edits made in Admin → Bordro Kodları are kept.
 *
 * Sources:
 * - Belge türleri: APHB belge türleri listesi (isvesosyalguvenlik.com)
 * - İşten çıkış kodları: SGK işten ayrılış kodları (muhasebetr.com); 42–50 metinleri 4857 s.K. md. 25/II
 * - Eksik gün nedenleri: SGK eksik gün nedeni kodları (birden fazla kaynak)
 * - Teşvik kanunları: SGK "Kolay İşverenlik Teşvik İşlemleri Kılavuzu"nda geçen belge numaraları
 * Meslek kodları ve bankalar Excel ile yüklenir.
 */
class PayrollCodeSeeder extends Seeder
{
    /**
     * @return array<string, list<array{0: string, 1: string, 2?: bool, 3?: string}>> list => [code, name, active, description]
     */
    public static function data(): array
    {
        return [
            CodeList::DocumentTypes->value => [
                ['01', 'Hizmet akdi ile tüm sigorta kollarına tabi çalışanlar (yabancı uyruklu sigortalılar dahil)'],
                ['02', 'Sosyal güvenlik destek primine tabi çalışanlar (emekli olduktan sonra çalışanlar)'],
                ['04', 'Yer altında sürekli çalışanlar (maden işyerlerinde 1/10/2008 öncesi çalışması olanlar)'],
                ['05', 'Yer altında gruplu çalışanlar (maden işyerlerinde 1/10/2008 öncesi çalışması olanlar)'],
                ['06', 'Yer üstü gruplu çalışanlar (maden işyerlerinde 1/10/2008 öncesi çalışması olanlar)'],
                ['07', '3308 sayılı Kanunda belirtilen aday çırak, çırak ve işletmelerde mesleki eğitim gören öğrencilerden bakmakla yükümlü olunanlar'],
                ['09', 'SSK topluluk sigortası'],
                ['12', 'Geçici 20 nci maddeye tabi olanlar'],
                ['13', 'Tüm sigorta kollarına tabi olup işsizlik sigortası primi kesilmeyenler'],
                ['14', "Libya'da çalışanlar"],
                ['19', 'Ceza infaz kurumları ile tutukevleri bünyesinde oluşturulan tesis, atölye ve benzeri ünitelerde çalıştırılan hükümlü ve tutuklular'],
                ['20', "İstisna akdine istinaden Almanya'ya götürülen Türk işçiler"],
                ['21', 'Türk işverenler tarafından sosyal güvenlik sözleşmesi imzalanmamış ülkelere götürülerek çalıştırılan Türk işçileri'],
                ['22', 'Meslek liselerinde okurken veya yükseköğrenim sırasında staja tabi tutulan öğrenciler ile 2547 s.K. uyarınca kısmi zamanlı çalıştırılan öğrencilerden bakmakla yükümlü olunanlar'],
                ['23', 'Harp malulleri ile 3713 ve 2330 s.K. göre vazife malullüğü aylığı alanlardan kısa vadeli sigorta kollarına tabi olanlar'],
                ['24', 'Harp malulleri ile 3713 ve 2330 s.K. göre vazife malullüğü aylığı alanlardan kısa ve uzun vadeli sigorta kollarına tabi olanlar'],
                ['25', 'Türkiye İş Kurumu tarafından düzenlenen eğitimlere katılan kursiyerlerden bakmakla yükümlü olunanlar'],
                ['28', '4046 sayılı Kanunun 21 inci maddesi kapsamında iş kaybı tazminatı alanlar'],
                ['29', 'Tüm sigorta kollarına tabi çalışıp 60 gün fiili hizmet süresi zammına tabi çalışanlar'],
                ['30', 'İşsizlik sigortası hariç 60 gün fiili hizmet süresi zammına tabi çalışanlar'],
                ['31', 'Harp malulleri ile 3713 ve 2330 s.K. göre vazife malullüğü aylığı alanlardan kısa ve uzun vadeli sigorta kollarına tabi olup 60 gün fiili hizmet süresi zammına tabi çalışanlar'],
                ['32', 'Tüm sigorta kollarına tabi çalışıp 90 gün fiili hizmet süresi zammına tabi çalışanlar'],
                ['33', 'İşsizlik sigortası hariç 90 gün fiili hizmet süresi zammına tabi çalışanlar'],
                ['34', 'Harp malulleri ile 3713 ve 2330 s.K. göre vazife malullüğü aylığı alanlardan kısa ve uzun vadeli sigorta kollarına tabi olup 90 gün fiili hizmet süresi zammına tabi çalışanlar'],
                ['35', 'Tüm sigorta kollarına tabi çalışıp 180 gün fiili hizmet süresi zammına tabi çalışanlar'],
                ['36', 'İşsizlik sigortası hariç 180 gün fiili hizmet süresi zammına tabi çalışanlar'],
                ['37', 'Harp malulleri ile 3713 ve 2330 s.K. göre vazife malullüğü aylığı alanlardan kısa ve uzun vadeli sigorta kollarına tabi olup 180 gün fiili hizmet süresi zammına tabi çalışanlar'],
                ['39', 'Birleşik Krallıkta ikamet edenler ve İsviçre vatandaşı olanlardan uzun vadeli sigorta kolunun uygulanmasını talep etmeyenler'],
                ['41', 'Kamu idarelerinde iş akdi askıda olanlar'],
                ['42', '3308 sayılı Kanunda belirtilen aday çırak, çırak ve işletmelerde mesleki eğitim gören öğrencilerden bakmakla yükümlü olunmayanlar'],
                ['43', 'Meslek liselerinde okurken veya yükseköğrenim sırasında staja tabi tutulan öğrenciler ile 2547 s.K. uyarınca kısmi zamanlı çalıştırılan öğrencilerden bakmakla yükümlü olunmayanlar'],
                ['44', 'Türkiye İş Kurumu tarafından düzenlenen eğitimlere katılan kursiyerlerden bakmakla yükümlü olunmayanlar'],
                ['46', 'Türkiye İş Kurumu tarafından düzenlenen eğitimlere katılan kursiyerler ile işbaşı eğitim programı kapsamında çalışanlar'],
                ['47', 'Doğum ve evlat edinme sonrası yarım çalışma ödeneği'],
                ['48', 'Yeraltında çalışanlar emekliler'],
                ['49', 'Mesleki ve teknik ortaöğretim sırasında tamamlayıcı eğitim ya da alan eğitimi gören öğrencilerden bakmakla yükümlü olunanlar'],
                ['50', 'Mesleki ve teknik ortaöğretim sırasında tamamlayıcı eğitim ya da alan eğitimi gören öğrencilerden bakmakla yükümlü olunmayanlar'],
                ['51', '5510 sayılı Kanunun ek 15 inci maddesi kapsamındaki güvenlik korucuları'],
                ['52', 'Malullük aylığı bağlanmamış olup 670 s. KHK kapsamında tazminat hakkından yararlananlar'],
                ['53', 'Malullük aylığı bağlanmamış olup 670 s. KHK kapsamında tazminat hakkından yararlananlardan 60 gün fiili hizmet süresi zammına tabi çalışanlar'],
                ['54', 'Malullük aylığı bağlanmamış olup 670 s. KHK kapsamında tazminat hakkından yararlananlardan 90 gün fiili hizmet süresi zammına tabi çalışanlar'],
                ['55', 'Malullük aylığı bağlanmamış olup 670 s. KHK kapsamında tazminat hakkından yararlananlardan 180 gün fiili hizmet süresi zammına tabi çalışanlar'],
                ['90', 'İtibari hizmet süresine tabi olarak çalışanlar'],
                ['91', '60 gün fiili hizmet süresi zammına tabi olanlardan itibari hizmet süresine tabi olarak çalışanlar'],
                ['92', '90 gün fiili hizmet süresi zammına tabi olanlardan itibari hizmet süresine tabi olarak çalışanlar'],
            ],

            CodeList::MissingDayReasons->value => [
                ['01', 'İstirahat'],
                ['02', 'Ücretsiz izin', false, 'Kullanımdan kaldırıldı; 19, 20 veya 21 kullanılır.'],
                ['03', 'Disiplin cezası'],
                ['04', 'Gözaltına alınma'],
                ['05', 'Tutukluluk'],
                ['06', 'Kısmi istihdam'],
                ['07', 'Puantaj kayıtları'],
                ['08', 'Grev'],
                ['09', 'Lokavt'],
                ['10', 'Genel hayatı etkileyen olaylar'],
                ['11', 'Doğal afet'],
                ['12', 'Birden fazla'],
                ['13', 'Diğer'],
                ['15', 'Devamsızlık'],
                ['16', 'Fesih tarihinde çalışmamış'],
                ['17', 'Ev hizmetlerinde 30 günden az çalışma'],
                ['18', 'Kısa çalışma ödeneği'],
                ['19', 'Ücretsiz doğum izni'],
                ['20', 'Ücretsiz yol izni'],
                ['21', 'Diğer ücretsiz izin'],
                ['22', '5434 SK ek 76, GM 192'],
                ['23', 'Yarım çalışma ödeneği'],
                ['24', 'Yarım çalışma ödeneği ve diğer nedenler'],
                ['25', 'Diğer belge/kanun türlerinden gün tamamlama'],
                ['26', 'Kısmi istihdama izin verilen yabancı uyruklu sigortalı'],
                ['27', 'Kısa çalışma ödeneği ve diğer nedenler'],
                ['28', 'Pandemi ücretsiz izin', false, 'Pandemi dönemine özgü; yeni dönemlerde kullanılmaz.'],
                ['29', 'Pandemi ücretsiz izin ve diğer nedenler', false, 'Pandemi dönemine özgü; yeni dönemlerde kullanılmaz.'],
            ],

            CodeList::TerminationReasons->value => [
                ['1', 'Deneme süreli iş sözleşmesinin işverence feshi'],
                ['2', 'Deneme süreli iş sözleşmesinin işçi tarafından feshi'],
                ['3', 'Belirsiz süreli iş sözleşmesinin işçi tarafından feshi (istifa)'],
                ['4', 'Belirsiz süreli iş sözleşmesinin işveren tarafından haklı sebep bildirilmeden feshi'],
                ['5', 'Belirli süreli iş sözleşmesinin sona ermesi'],
                ['8', 'Emeklilik (yaşlılık) veya toptan ödeme nedeniyle'],
                ['9', 'Malulen emeklilik nedeniyle'],
                ['10', 'Ölüm'],
                ['11', 'İş kazası sonucu ölüm'],
                ['12', 'Askerlik'],
                ['13', 'Kadın işçinin evlenmesi'],
                ['14', 'Emeklilik için yaş dışında diğer şartların tamamlanması'],
                ['15', 'Toplu işçi çıkarma'],
                ['16', 'Sözleşme sona ermeden sigortalının aynı işverene ait diğer işyerine nakli'],
                ['17', 'İşyerinin kapanması'],
                ['18', 'İşin sona ermesi'],
                ['19', 'Mevsim bitimi'],
                ['20', 'Kampanya bitimi'],
                ['21', 'Statü değişikliği'],
                ['22', 'Diğer nedenler'],
                ['23', 'İşçi tarafından zorunlu nedenle fesih'],
                ['24', 'İşçi tarafından sağlık nedeniyle fesih'],
                ['25', 'İşçi tarafından işverenin ahlak ve iyiniyet kurallarına aykırı davranışı nedeni ile fesih'],
                ['26', 'Disiplin kurulu kararı ile fesih'],
                ['27', 'İşveren tarafından zorunlu nedenlerle ve tutukluluk nedeniyle fesih'],
                ['28', 'İşveren tarafından sağlık nedeni ile fesih'],
                ['29', 'İşveren tarafından işçinin ahlak ve iyiniyet kurallarına aykırı davranışı nedeni ile fesih', false, 'Yerine 42–50 kodları kullanılır.'],
                ['30', 'Vize süresinin bitimi'],
                ['31', 'Borçlar Kanunu, Sendikalar Kanunu, Grev ve Lokavt Kanunu kapsamında kendi istek ve kusuru dışında fesih'],
                ['32', '4046 sayılı Kanunun 21 inci maddesine göre özelleştirme nedeni ile fesih'],
                ['33', 'Gazeteci tarafından sözleşmenin feshi'],
                ['34', 'İşyerinin devri, işin veya işyerinin niteliğinin değişmesi nedeniyle fesih'],
                ['35', '6495 sayılı Kanun nedeniyle devlet memurluğuna geçenler'],
                ['36', 'KHK ile işyerinin kapatılması'],
                ['37', 'KHK ile kamu görevinden çıkarma'],
                ['38', 'Doğum nedeniyle işten ayrılma'],
                ['39', '696 KHK ile kamu işçiliğine geçiş'],
                ['40', '696 KHK ile kamu işçiliğine geçilmemesi sebebiyle çıkış'],
                ['41', 'Resen işten ayrılış bildirgesi düzenlenenler'],
                ['42', '4857 s.K. 25/II-a: İşe girerken gerekli vasıflar veya şartlar konusunda işvereni yanıltma'],
                ['43', '4857 s.K. 25/II-b: İşveren veya aile üyelerinin şeref ve namusuna dokunacak söz/davranış ya da asılsız ihbar ve isnat'],
                ['44', '4857 s.K. 25/II-c: İşverenin aile üyelerinden birine veya başka bir işçisine cinsel tacizde bulunma'],
                ['45', '4857 s.K. 25/II-d: İşverene, ailesine veya başka bir işçiye sataşma; işyerine sarhoş ya da uyuşturucu almış gelme veya işyerinde kullanma'],
                ['46', '4857 s.K. 25/II-e: Güveni kötüye kullanma, hırsızlık, meslek sırlarını ortaya atma gibi doğruluk ve bağlılığa uymayan davranış'],
                ['47', '4857 s.K. 25/II-f: İşyerinde yedi günden fazla hapisle cezalandırılan ve ertelenmeyen bir suç işleme'],
                ['48', '4857 s.K. 25/II-g: İzinsiz veya haklı sebebe dayanmaksızın ardı ardına iki iş günü, ayda iki kez tatil sonrası iş günü ya da ayda üç iş günü devamsızlık'],
                ['49', '4857 s.K. 25/II-h: Hatırlatıldığı halde görevlerini yapmamakta ısrar etme'],
                ['50', '4857 s.K. 25/II-ı: İşin güvenliğini tehlikeye düşürme veya işyeri malını otuz günlük ücretle ödenemeyecek ölçüde hasara uğratma'],
            ],

            CodeList::IncentiveLaws->value => [
                ['00000', 'Teşvik uygulanmayan (kanun kapsamı dışı)'],
                ['02828', '2828 sayılı Kanun ek 1. madde'],
                ['03294', '3294 sayılı Kanun: sosyal yardım alanların istihdamı'],
                ['05510', '5510 sayılı Kanun md. 81/ı: prim indirimi', true, '2026: imalat sektöründe 5 puan, diğer sektörlerde 2 puan.'],
                ['06111', '4447 sayılı Kanun geçici 10. madde (6111)'],
                ['17103', '4447 sayılı Kanun geçici 19. madde (7103): 17103', true, 'Ek 9 kapsamındaki işyerleri yararlanamaz.'],
                ['27103', '4447 sayılı Kanun geçici 19. madde (7103): 27103'],
            ],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::data() as $list => $rows) {
            foreach ($rows as $row) {
                PayrollCode::firstOrCreate(
                    ['list' => $list, 'code' => $row[0]],
                    ['name' => $row[1], 'is_active' => $row[2] ?? true, 'description' => $row[3] ?? null],
                );
            }
        }
    }
}
