<?php

namespace Tests\Feature\Payroll;

use App\Models\District;
use Database\Seeders\DistrictSeeder;
use Database\Seeders\ReferenceDataSeeder;

class ReferenceDataTest extends PayrollTestCase
{
    public function test_district_data_covers_every_province(): void
    {
        /** @var array<string, list<array{code: int, name: string}>> $data */
        $data = json_decode((string) file_get_contents(database_path('data/districts.json')), true);

        $this->assertSame(array_map('strval', array_keys(ReferenceDataSeeder::PROVINCES)), array_keys($data));
        $this->assertSame(973, array_sum(array_map('count', $data)));
        $this->assertCount(39, $data['34']);
    }

    public function test_district_seeder_is_idempotent_and_matches_workplace_input(): void
    {
        $this->seed(DistrictSeeder::class);
        $this->seed(DistrictSeeder::class);

        $this->assertSame(973, District::count());
        $this->assertSame(1421, District::where('province_id', 34)->where('name', 'Kadıköy')->value('code'));
        $this->assertSame('19 Mayıs', District::where('code', 1830)->value('name'));
    }
}
