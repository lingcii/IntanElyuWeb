<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\TouristSpot;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class RbacWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rbac_and_approval_workflow()
    {
        $municipal = User::where('role', 'san_fernando_mto')->first();
        $picto = User::where('role', 'pitco')->first();
        $lupto = User::where('role', 'lupto')->first();

        $this->assertNotNull($municipal, 'Municipal user seeded');
        $this->assertNotNull($picto, 'PICTO user seeded');
        $this->assertNotNull($lupto, 'LUPTO user seeded');

        $spotName = "Automated Test Spot " . rand(1000, 9999);

        // 1. Create spot as Municipal
        $response = $this->actingAs($municipal)
            ->withSession([
                'user_id' => $municipal->id,
                'user_name' => $municipal->name,
                'user_email' => $municipal->email,
                'user_role' => $municipal->role,
                'user_municipality_id' => $municipal->municipality_id,
            ])
            ->postJson('/api/tourist-spots', [
                'name' => $spotName,
                'barangay' => 'Test Barangay',
                'category' => 'Beach',
                'classification_status' => 'EMERGING',
                'entrance_fee' => 50.00,
                'description' => 'A test spot description.',
                'latitude' => 16.6,
                'longitude' => 120.3,
                'municipality_id' => $municipal->municipality_id,
                'images' => [
                    ['photo_url' => '/images/placeholder.jpg']
                ]
            ]);

        $response->assertStatus(201);

        $spot = TouristSpot::where('name', $spotName)->first();
        $this->assertNotNull($spot);
        $this->assertEquals('pending', $spot->status);

        // 2. Check PICTO visibility
        $response = $this->actingAs($picto)
            ->withSession([
                'user_id' => $picto->id,
                'user_name' => $picto->name,
                'user_email' => $picto->email,
                'user_role' => $picto->role,
                'user_municipality_id' => $picto->municipality_id,
            ])
            ->getJson('/api/tourist-spots');

        $response->assertStatus(200);
        $visibleSpots = $response->json();
        $found = false;
        foreach ($visibleSpots as $s) {
            if ($s['id'] == $spot->id) {
                $found = true;
            }
        }
        $this->assertFalse($found, 'Pending spot should be hidden from PICTO');

        // 3. Check LUPTO pending approval list
        $response = $this->actingAs($lupto)
            ->withSession([
                'user_id' => $lupto->id,
                'user_name' => $lupto->name,
                'user_email' => $lupto->email,
                'user_role' => $lupto->role,
                'user_municipality_id' => $lupto->municipality_id,
            ])
            ->getJson('/api/lupto/dashboard/pending-spots');

        $response->assertStatus(200);
        $pendingSpots = $response->json('spots') ?? [];
        $found = false;
        foreach ($pendingSpots as $s) {
            if ($s['id'] == $spot->id) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Pending spot should show in LUPTO pending list');

        // 4. Reject spot as LUPTO with reason
        $response = $this->actingAs($lupto)
            ->withSession([
                'user_id' => $lupto->id,
                'user_role' => $lupto->role,
            ])
            ->postJson('/api/lupto/dashboard/reject-spot', [
                'id' => $spot->id,
                'rejection_reason' => 'Incorrect coordinates'
            ]);

        $response->assertStatus(200);
        $spot->refresh();
        $this->assertEquals('rejected', $spot->status);
        $this->assertEquals('Incorrect coordinates', $spot->rejection_reason);

        // 5. Municipal view details shows rejection reason
        $response = $this->actingAs($municipal)
            ->withSession([
                'user_id' => $municipal->id,
                'user_role' => $municipal->role,
                'user_municipality_id' => $municipal->municipality_id,
            ])
            ->getJson("/api/tourist-spots/{$spot->id}");

        $response->assertStatus(200);
        $this->assertEquals('Incorrect coordinates', $response->json('rejection_reason'));

        // 6. Resubmit spot
        $response = $this->actingAs($municipal)
            ->withSession([
                'user_id' => $municipal->id,
                'user_role' => $municipal->role,
                'user_municipality_id' => $municipal->municipality_id,
            ])
            ->putJson("/api/tourist-spots/{$spot->id}", [
                'name' => $spotName . " Resubmitted",
                'barangay' => 'Test Barangay',
                'category' => 'Beach',
                'classification_status' => 'EMERGING',
                'entrance_fee' => 50.00,
                'description' => 'A test spot description resubmitted.',
                'latitude' => 16.61,
                'longitude' => 120.31,
                'images' => [
                    ['photo_url' => '/images/placeholder.jpg']
                ]
            ]);

        $response->assertStatus(200);
        $spot->refresh();
        $this->assertEquals('pending', $spot->status);
        $this->assertNull($spot->rejection_reason);

        // 7. Approve spot as LUPTO
        $muni = Municipality::find($spot->municipality_id);
        $oldCount = $muni->attraction_count;

        $response = $this->actingAs($lupto)
            ->withSession([
                'user_id' => $lupto->id,
                'user_role' => $lupto->role,
            ])
            ->postJson('/api/lupto/dashboard/approve-spot', [
                'id' => $spot->id
            ]);

        $response->assertStatus(200);
        $spot->refresh();
        $muni->refresh();
        $this->assertEquals('approved', $spot->status);
        $this->assertEquals($oldCount + 1, $muni->attraction_count);

        // 8. PICTO visibility check of approved spot
        $response = $this->actingAs($picto)
            ->withSession([
                'user_id' => $picto->id,
                'user_role' => $picto->role,
                'user_municipality_id' => $picto->municipality_id,
            ])
            ->getJson('/api/tourist-spots');

        $response->assertStatus(200);
        $found = false;
        foreach ($response->json() as $s) {
            if ($s['id'] == $spot->id) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Approved spot should be visible to PICTO');

        // 9. LUPTO Create restriction (Should block emerging/potential)
        $response = $this->actingAs($lupto)
            ->withSession([
                'user_id' => $lupto->id,
                'user_role' => $lupto->role,
            ])
            ->postJson('/api/tourist-spots', [
                'name' => 'LUPTO Bad Spot',
                'barangay' => 'Test Barangay',
                'category' => 'Mountain',
                'classification_status' => 'EMERGING',
                'entrance_fee' => 0.00,
                'description' => 'A test spot.',
                'latitude' => 16.7,
                'longitude' => 120.4,
                'municipality_id' => $municipal->municipality_id,
                'images' => [
                    ['photo_url' => '/images/placeholder.jpg']
                ]
            ]);

        $response->assertStatus(422);

        // 10. Delete controls
        $response = $this->actingAs($municipal)
            ->withSession([
                'user_id' => $municipal->id,
                'user_role' => $municipal->role,
                'user_municipality_id' => $municipal->municipality_id,
            ])
            ->deleteJson("/api/tourist-spots/{$spot->id}");

        $response->assertStatus(403);

        $response = $this->actingAs($picto)
            ->withSession([
                'user_id' => $picto->id,
                'user_role' => $picto->role,
                'user_municipality_id' => $picto->municipality_id,
            ])
            ->deleteJson("/api/tourist-spots/{$spot->id}");

        $response->assertStatus(403);

        $oldCountDelete = $muni->attraction_count;
        $response = $this->actingAs($lupto)
            ->withSession([
                'user_id' => $lupto->id,
                'user_role' => $lupto->role,
            ])
            ->deleteJson("/api/tourist-spots/{$spot->id}");

        $response->assertStatus(200);
        $muni->refresh();
        $this->assertNull(TouristSpot::find($spot->id));
        $this->assertEquals($oldCountDelete - 1, $muni->attraction_count);
    }
}
