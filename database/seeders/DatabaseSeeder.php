<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            DistrictSeeder::class,
            PermissionTemplateSeeder::class,
            LegalParameterSeeder::class,
            PayrollCodeSeeder::class,
            HolidaySeeder::class,
        ]);

        if (app()->isLocal() && ! User::where('email', 'admin@hrd.test')->exists()) {
            User::factory()->superAdmin()->create([
                'name' => 'HRD Super Admin',
                'email' => 'admin@hrd.test',
            ]);
        }
    }
}
