<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VehicleType;

class VehicleTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicleTypes = [
            // Public Vehicles
            ['category' => 'Public Vehicle', 'name' => 'TAXI'],
            ['category' => 'Public Vehicle', 'name' => 'UVE'],
            ['category' => 'Public Vehicle', 'name' => 'PUB_Regular'],
            ['category' => 'Public Vehicle', 'name' => 'PUB_Aircon'],
            ['category' => 'Public Vehicle', 'name' => 'MPUJ'],
            ['category' => 'Public Vehicle', 'name' => 'TPUJ'],
            ['category' => 'Public Vehicle', 'name' => 'Tricycle'],

            // Private Vehicles
            ['category' => 'Private Vehicle', 'name' => 'Car'],
            ['category' => 'Private Vehicle', 'name' => 'Motorcycle'],
            ['category' => 'Private Vehicle', 'name' => 'Van'],
            ['category' => 'Private Vehicle', 'name' => 'Tricycle'],
        ];

        foreach ($vehicleTypes as $vt) {
            VehicleType::firstOrCreate([
                'category' => $vt['category'],
                'name'     => $vt['name'],
            ]);
        }
    }
}
