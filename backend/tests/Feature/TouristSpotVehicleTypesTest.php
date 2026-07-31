<?php

namespace Tests\Feature;

use App\Models\Municipality;
use App\Models\TouristSpot;
use App\Models\User;
use App\Models\VehicleType;
use Database\Seeders\VehicleTypeSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TouristSpotVehicleTypesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VehicleTypeSeeder::class);
    }

    private function createTestUser(string $role, int $municipalityId): User
    {
        return User::create([
            'name' => 'Test User ' . uniqid(),
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'municipality_id' => $municipalityId,
            'status' => 'active',
        ]);
    }

    private function createTestMunicipality(): Municipality
    {
        return Municipality::create([
            'name' => 'Muni ' . uniqid(),
            'code' => 'M' . rand(100, 999),
        ]);
    }

    public function test_vehicle_type_seeder_creates_11_distinct_records(): void
    {
        $this->assertEquals(11, VehicleType::count());

        $publicTricycle = VehicleType::where('category', 'Public Vehicle')->where('name', 'Tricycle')->first();
        $privateTricycle = VehicleType::where('category', 'Private Vehicle')->where('name', 'Tricycle')->first();

        $this->assertNotNull($publicTricycle);
        $this->assertNotNull($privateTricycle);
        $this->assertNotEquals($publicTricycle->id, $privateTricycle->id);
    }

    public function test_vehicle_type_role_filtering(): void
    {
        $luptoTypes = VehicleType::getAllowedForRole('lupto');
        $municipalTypes = VehicleType::getAllowedForRole('municipal');

        $this->assertCount(10, $luptoTypes);
        $this->assertCount(5, $municipalTypes);

        $luptoPublicNames = $luptoTypes->where('category', 'Public Vehicle')->pluck('name')->toArray();
        $this->assertContains('TAXI', $luptoPublicNames);
        $this->assertContains('PUB_Aircon', $luptoPublicNames);
        $this->assertNotContains('Tricycle', $luptoPublicNames);

        $municipalPublicNames = $municipalTypes->where('category', 'Public Vehicle')->pluck('name')->toArray();
        $this->assertContains('Tricycle', $municipalPublicNames);
        $this->assertNotContains('TAXI', $municipalPublicNames);
    }

    public function test_lupto_can_create_spot_with_valid_vehicle_types(): void
    {
        $muni = $this->createTestMunicipality();
        $user = $this->createTestUser('lupto', $muni->id);

        $validLuptoIds = VehicleType::getAllowedForRole('lupto')->pluck('id')->take(3)->toArray();

        $response = $this->withSession([
            'user_id' => $user->id,
            'user_role' => 'lupto',
            'user_municipality_id' => $muni->id,
        ])->postJson('/api/tourist-spots', [
            'name' => 'Surfing Beach Spot ' . uniqid(),
            'category' => 'Beach',
            'description' => 'A famous surfing spot in San Juan.',
            'classification_status' => 'EXISTING',
            'municipality_id' => $muni->id,
            'points' => 50,
            'vehicle_type_ids' => $validLuptoIds,
        ]);

        $response->assertStatus(201);
        $spotId = $response->json('id');
        $this->assertNotNull($spotId);

        $spot = TouristSpot::with('vehicleTypes')->find($spotId);
        $this->assertCount(3, $spot->vehicleTypes);
        $this->assertEqualsCanonicalizing($validLuptoIds, $spot->vehicleTypes->pluck('id')->toArray());
    }

    public function test_municipal_user_can_create_spot_with_municipal_vehicle_types(): void
    {
        $muni = $this->createTestMunicipality();
        $user = $this->createTestUser('bacnotan_mto', $muni->id);

        $validMunicipalIds = VehicleType::getAllowedForRole('municipal')->pluck('id')->toArray();

        $response = $this->withSession([
            'user_id' => $user->id,
            'user_role' => 'bacnotan_mto',
            'user_municipality_id' => $muni->id,
        ])->postJson('/api/tourist-spots', [
            'name' => 'Agoo Eco Park ' . uniqid(),
            'category' => 'Eco-Tourism',
            'description' => 'Beautiful eco park.',
            'classification_status' => 'POTENTIAL',
            'municipality_id' => $muni->id,
            'points' => 75,
            'vehicle_type_ids' => $validMunicipalIds,
        ]);

        $response->assertStatus(201);
        $spotId = $response->json('id');
        $spot = TouristSpot::with('vehicleTypes')->find($spotId);
        $this->assertCount(count($validMunicipalIds), $spot->vehicleTypes);
    }

    public function test_rejects_unauthorized_vehicle_types(): void
    {
        $muni = $this->createTestMunicipality();
        $user = $this->createTestUser('bangar_mto', $muni->id);

        // TAXI (id 1) is allowed for LUPTO but NOT for Municipal users
        $taxiVt = VehicleType::where('category', 'Public Vehicle')->where('name', 'TAXI')->first();

        $response = $this->withSession([
            'user_id' => $user->id,
            'user_role' => 'bangar_mto',
            'user_municipality_id' => $muni->id,
        ])->postJson('/api/tourist-spots', [
            'name' => 'Bangar Craft Village ' . uniqid(),
            'category' => 'Cultural Heritage',
            'description' => 'Handwoven products village.',
            'classification_status' => 'EMERGING',
            'municipality_id' => $muni->id,
            'points' => 100,
            'vehicle_type_ids' => [$taxiVt->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'One or more selected vehicle types are not permitted for your role.']);
    }

    public function test_updating_vehicle_types_synchronizes_relationship_without_duplicates(): void
    {
        $muni = $this->createTestMunicipality();
        $user = $this->createTestUser('lupto', $muni->id);

        $spot = TouristSpot::create([
            'name' => 'Bauang Grape Farm ' . uniqid(),
            'category' => 'Farm',
            'description' => 'Grape picking destination.',
            'classification_status' => 'EXIST',
            'municipality_id' => $muni->id,
            'points' => 50,
            'status' => 'approved',
            'created_by' => $user->id,
            'creator_role' => 'lupto',
        ]);

        $allLuptoIds = VehicleType::getAllowedForRole('lupto')->pluck('id')->toArray();
        $initialIds = [$allLuptoIds[0], $allLuptoIds[1]];
        $updatedIds = [$allLuptoIds[1], $allLuptoIds[2]];

        // Initial sync
        $spot->vehicleTypes()->sync($initialIds);
        $this->assertEqualsCanonicalizing($initialIds, $spot->fresh()->vehicleTypes->pluck('id')->toArray());

        // Update request removing first, keeping second, adding third
        $response = $this->withSession([
            'user_id' => $user->id,
            'user_role' => 'lupto',
            'user_municipality_id' => $muni->id,
        ])->putJson("/api/tourist-spots/{$spot->id}", [
            'name' => 'Bauang Grape Farm Updated',
            'category' => 'Farm',
            'description' => 'Grape picking destination updated.',
            'classification_status' => 'EXISTING',
            'points' => 50,
            'vehicle_type_ids' => $updatedIds,
        ]);

        $response->assertStatus(200);

        /** @var TouristSpot $freshSpot */
        $freshSpot = $spot->fresh(['vehicleTypes']);
        $this->assertNotNull($freshSpot);
        $this->assertCount(2, $freshSpot->vehicleTypes);
        $this->assertEqualsCanonicalizing($updatedIds, $freshSpot->vehicleTypes->pluck('id')->toArray());
    }

    public function test_existing_spots_without_vehicle_types_continue_working(): void
    {
        $muni = $this->createTestMunicipality();
        $user = $this->createTestUser('lupto', $muni->id);

        $spot = TouristSpot::create([
            'name' => 'Baluarte Watchtower ' . uniqid(),
            'category' => 'Historical',
            'description' => 'Historic watchtower in Luna.',
            'classification_status' => 'EXIST',
            'municipality_id' => $muni->id,
            'points' => 50,
            'status' => 'approved',
        ]);

        $response = $this->withSession([
            'user_id' => $user->id,
            'user_role' => 'lupto',
            'user_municipality_id' => $muni->id,
        ])->getJson("/api/tourist-spots/{$spot->id}");

        $response->assertStatus(200);
        $this->assertIsArray($response->json('vehicle_types'));
        $this->assertEmpty($response->json('vehicle_types'));
    }
}
