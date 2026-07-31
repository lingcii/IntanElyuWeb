<?php

namespace Tests\Feature;

use App\Models\Classification;
use App\Models\TouristSpot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ClassificationPointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('classification_points');
    }

    public function test_can_fetch_classification_points()
    {
        $user = User::first() ?? User::create([
            'name' => 'Test User',
            'email' => 'test@launion.gov.ph',
            'password' => bcrypt('password'),
            'role' => 'lupto'
        ]);

        $response = $this->actingAs($user)
            ->withSession(['user_id' => $user->id])
            ->getJson('/api/classification-points');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'points' => ['EXISTING', 'EMERGING', 'POTENTIAL'],
            ]);
    }

    public function test_lupto_user_can_update_classification_points()
    {
        $luptoUser = User::where('role', 'lupto')->first() ?? User::factory()->create(['role' => 'lupto']);

        $payload = [
            'EXISTING'  => 60,
            'EMERGING'  => 120,
            'POTENTIAL' => 90,
        ];

        $response = $this->actingAs($luptoUser)
            ->withSession(['user_id' => $luptoUser->id, 'user_role' => 'lupto'])
            ->putJson('/api/lupto/classification-points', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Classification points updated successfully.',
                'points'  => $payload,
            ]);

        $this->assertEquals(60, Classification::where('name', 'EXISTING')->value('points'));
        $this->assertEquals(120, Classification::where('name', 'EMERGING')->value('points'));
        $this->assertEquals(90, Classification::where('name', 'POTENTIAL')->value('points'));

        // Test TouristSpot::getDefaultPointsForClassification uses the updated points
        $this->assertEquals(60, TouristSpot::getDefaultPointsForClassification('EXISTING'));
        $this->assertEquals(120, TouristSpot::getDefaultPointsForClassification('EMERGING'));
        $this->assertEquals(90, TouristSpot::getDefaultPointsForClassification('POTENTIAL'));
    }

    public function test_validates_classification_point_inputs()
    {
        $luptoUser = User::where('role', 'lupto')->first() ?? User::factory()->create(['role' => 'lupto']);

        $invalidPayload = [
            'EXISTING'  => -10,
            'EMERGING'  => 'abc',
            'POTENTIAL' => null,
        ];

        $response = $this->actingAs($luptoUser)
            ->withSession(['user_id' => $luptoUser->id, 'user_role' => 'lupto'])
            ->putJson('/api/lupto/classification-points', $invalidPayload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['EXISTING', 'EMERGING', 'POTENTIAL']);
    }
}
