<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(MunicipalitySeeder::class);
        $this->call(UserSeeder::class);
        $this->call(TouristSpotSeeder::class);
        $this->call(VehicleTypeSeeder::class);
    }
}
