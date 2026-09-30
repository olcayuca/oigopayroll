<?php

namespace Database\Seeders;

use App\Models\District;
use Illuminate\Database\Seeder;

/**
 * Loads ilçeler from database/data/districts.json: {"<plaka kodu>": ["İlçe", ...], ...}.
 *
 * Skipped when the file is missing; workplaces can still store a manually typed ilçe.
 */
class DistrictSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = database_path('data/districts.json');

        if (! is_file($path)) {
            $this->command->warn('database/data/districts.json bulunamadı; ilçe listesi yüklenmedi.');

            return;
        }

        /** @var array<string, list<string>> $districts */
        $districts = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        foreach ($districts as $provinceId => $names) {
            foreach ($names as $name) {
                District::firstOrCreate(['province_id' => (int) $provinceId, 'name' => $name]);
            }
        }
    }
}
