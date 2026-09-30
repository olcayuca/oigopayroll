<?php

namespace Database\Seeders;

use App\Models\District;
use Illuminate\Database\Seeder;

/**
 * Loads the 973 ilçeler from database/data/districts.json:
 * {"<plaka kodu>": [{"code": <resmi ilçe kodu>, "name": "İlçe"}, ...], ...}
 *
 * Source: github.com/snrylmz/il-ilce-json, cross-checked against
 * github.com/volkansenturk/turkiye-iller-ilceler (identical 973 codes).
 */
class DistrictSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var array<string, list<array{code: int, name: string}>> $districts */
        $districts = json_decode((string) file_get_contents(database_path('data/districts.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($districts as $provinceId => $items) {
            foreach ($items as $item) {
                District::updateOrCreate(
                    ['province_id' => (int) $provinceId, 'name' => $item['name']],
                    ['code' => $item['code']],
                );
            }
        }
    }
}
