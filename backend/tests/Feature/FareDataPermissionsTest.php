<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\FareGuide;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FareDataPermissionsTest extends TestCase
{
    use DatabaseTransactions;

    private User $luptoUser;
    private User $pictoUser;
    private User $municipalUser;

    protected function setUp(): void
    {
        parent::setUp();

        $municipality = Municipality::create([
            'name' => 'San Fernando',
            'code' => 'SF-01',
        ]);

        $this->luptoUser = User::factory()->create([
            'role' => 'lupto',
            'municipality_id' => $municipality->id,
        ]);

        $this->pictoUser = User::factory()->create([
            'role' => 'picto',
            'municipality_id' => $municipality->id,
        ]);

        $this->municipalUser = User::factory()->create([
            'role' => 'bacnotan_mto',
            'municipality_id' => $municipality->id,
        ]);
    }

    public function test_lupto_is_blocked_from_creating_fare_guides(): void
    {
        $response = $this->withSession([
            'user_id' => $this->luptoUser->id,
            'user_role' => 'lupto',
        ])->postJson('/api/lupto/fare-data', [
            'title' => 'LUPTO Test Guide',
            'vehicle_type' => 'Tricycle',
            'region' => 'San Fernando',
            'effective_date' => '2026-01-01',
            'fares' => [
                ['distance_km' => 1.0, 'regular_fare' => 15.0, 'discounted_fare' => 12.0]
            ]
        ]);

        $response->assertStatus(403);
    }

    public function test_picto_can_create_fare_guide_for_any_vehicle_type(): void
    {
        $response = $this->withSession([
            'user_id' => $this->pictoUser->id,
            'user_role' => 'picto',
        ])->postJson('/api/pitco/fare-data', [
            'title' => 'PICTO Jeepney Fare Matrix',
            'vehicle_type' => 'PUJ_Ordinary',
            'region' => 'La Union',
            'effective_date' => '2026-01-01',
            'fares' => [
                ['distance_km' => 4.0, 'regular_fare' => 13.0, 'discounted_fare' => 11.0]
            ]
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);

        $this->assertDatabaseHas('fare_guides', [
            'title' => 'PICTO Jeepney Fare Matrix',
            'vehicle_type' => 'PUJ_Ordinary'
        ]);
    }

    public function test_municipal_is_blocked_from_creating_non_tricycle_fare_guides(): void
    {
        $response = $this->withSession([
            'user_id' => $this->municipalUser->id,
            'user_role' => 'bacnotan_mto',
        ])->postJson('/api/municipal/fare-data', [
            'title' => 'Municipal Bus Guide',
            'vehicle_type' => 'PUB_Aircon',
            'region' => 'San Fernando',
            'effective_date' => '2026-01-01',
            'fares' => [
                ['distance_km' => 5.0, 'regular_fare' => 50.0, 'discounted_fare' => 40.0]
            ]
        ]);

        $response->assertStatus(403)
                 ->assertJson(['success' => false, 'error' => 'Municipal users can only create Tricycle fare guides.']);
    }

    public function test_municipal_can_create_tricycle_fare_guides(): void
    {
        $response = $this->withSession([
            'user_id' => $this->municipalUser->id,
            'user_role' => 'bacnotan_mto',
            'user_municipality_id' => $this->municipalUser->municipality_id,
        ])->postJson('/api/municipal/fare-data', [
            'title' => 'Municipal Tricycle Guide 2026',
            'vehicle_type' => 'Tricycle',
            'region' => 'San Fernando',
            'effective_date' => '2026-01-01',
            'fares' => [
                ['distance_km' => 1.5, 'regular_fare' => 20.0, 'discounted_fare' => 16.0]
            ]
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true]);

        $this->assertDatabaseHas('fare_guides', [
            'title' => 'Municipal Tricycle Guide 2026',
            'vehicle_type' => 'Tricycle'
        ]);
    }
}
