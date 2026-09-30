<?php

namespace Database\Seeders;

use App\Models\LaborSector;
use App\Models\Province;
use App\Models\Sector;
use Illuminate\Database\Seeder;

/**
 * Lookup lists: iller (plaka kodu ile), ÇSGB işkolları and a starter sector list.
 *
 * İlçeler are loaded separately by DistrictSeeder from database/data/districts.json.
 * Risk sınıfları are managed by HRD and are intentionally not seeded.
 */
class ReferenceDataSeeder extends Seeder
{
    public const PROVINCES = [
        1 => 'Adana', 2 => 'Adıyaman', 3 => 'Afyonkarahisar', 4 => 'Ağrı', 5 => 'Amasya', 6 => 'Ankara',
        7 => 'Antalya', 8 => 'Artvin', 9 => 'Aydın', 10 => 'Balıkesir', 11 => 'Bilecik', 12 => 'Bingöl',
        13 => 'Bitlis', 14 => 'Bolu', 15 => 'Burdur', 16 => 'Bursa', 17 => 'Çanakkale', 18 => 'Çankırı',
        19 => 'Çorum', 20 => 'Denizli', 21 => 'Diyarbakır', 22 => 'Edirne', 23 => 'Elazığ', 24 => 'Erzincan',
        25 => 'Erzurum', 26 => 'Eskişehir', 27 => 'Gaziantep', 28 => 'Giresun', 29 => 'Gümüşhane', 30 => 'Hakkari',
        31 => 'Hatay', 32 => 'Isparta', 33 => 'Mersin', 34 => 'İstanbul', 35 => 'İzmir', 36 => 'Kars',
        37 => 'Kastamonu', 38 => 'Kayseri', 39 => 'Kırklareli', 40 => 'Kırşehir', 41 => 'Kocaeli', 42 => 'Konya',
        43 => 'Kütahya', 44 => 'Malatya', 45 => 'Manisa', 46 => 'Kahramanmaraş', 47 => 'Mardin', 48 => 'Muğla',
        49 => 'Muş', 50 => 'Nevşehir', 51 => 'Niğde', 52 => 'Ordu', 53 => 'Rize', 54 => 'Sakarya',
        55 => 'Samsun', 56 => 'Siirt', 57 => 'Sinop', 58 => 'Sivas', 59 => 'Tekirdağ', 60 => 'Tokat',
        61 => 'Trabzon', 62 => 'Tunceli', 63 => 'Şanlıurfa', 64 => 'Uşak', 65 => 'Van', 66 => 'Yozgat',
        67 => 'Zonguldak', 68 => 'Aksaray', 69 => 'Bayburt', 70 => 'Karaman', 71 => 'Kırıkkale', 72 => 'Batman',
        73 => 'Şırnak', 74 => 'Bartın', 75 => 'Ardahan', 76 => 'Iğdır', 77 => 'Yalova', 78 => 'Karabük',
        79 => 'Kilis', 80 => 'Osmaniye', 81 => 'Düzce',
    ];

    /**
     * 6356 sayılı Sendikalar ve Toplu İş Sözleşmesi Kanunu, 4. madde işkolları.
     */
    public const LABOR_SECTORS = [
        1 => 'Avcılık, balıkçılık, tarım ve ormancılık',
        2 => 'Gıda sanayi',
        3 => 'Madencilik ve taş ocakları',
        4 => 'Petrol, kimya, lastik, plastik ve ilaç',
        5 => 'Dokuma, hazır giyim ve deri',
        6 => 'Ağaç ve kağıt',
        7 => 'İletişim',
        8 => 'Basın, yayın ve gazetecilik',
        9 => 'Banka, finans ve sigorta',
        10 => 'Ticaret, büro, eğitim ve güzel sanatlar',
        11 => 'Çimento, toprak ve cam',
        12 => 'Metal',
        13 => 'İnşaat',
        14 => 'Enerji',
        15 => 'Taşımacılık',
        16 => 'Gemi yapımı ve deniz taşımacılığı, ardiye ve antrepoculuk',
        17 => 'Sağlık ve sosyal hizmetler',
        18 => 'Konaklama ve eğlence işleri',
        19 => 'Savunma ve güvenlik',
        20 => 'Genel işler',
    ];

    /**
     * Starter list; HRD can edit it later.
     */
    public const SECTORS = [
        'Bilgi Teknolojileri', 'İmalat', 'İnşaat', 'Perakende', 'Toptan Ticaret', 'Lojistik ve Taşımacılık',
        'Gıda', 'Turizm ve Konaklama', 'Sağlık', 'Eğitim', 'Finans ve Sigorta', 'Enerji', 'Tekstil',
        'Otomotiv', 'Kimya ve İlaç', 'Tarım', 'Medya ve İletişim', 'Danışmanlık', 'Gayrimenkul',
        'Güvenlik', 'Temizlik ve Tesis Yönetimi', 'Diğer',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::PROVINCES as $id => $name) {
            Province::updateOrCreate(['id' => $id], ['name' => $name]);
        }

        foreach (self::LABOR_SECTORS as $id => $name) {
            LaborSector::updateOrCreate(['id' => $id], ['name' => $name]);
        }

        foreach (self::SECTORS as $name) {
            Sector::firstOrCreate(['name' => $name]);
        }
    }
}
