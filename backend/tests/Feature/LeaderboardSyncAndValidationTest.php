<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserPoint;
use App\Http\Controllers\LeaderboardController;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaderboardSyncAndValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        LeaderboardController::clearCache();
    }

    public function test_leaderboard_returns_one_entry_per_user_and_aggregates_multiple_point_records()
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin_test@launion.gov.ph'],
            ['name' => 'Admin Tester', 'password' => bcrypt('password'), 'role' => 'lupto', 'status' => 'active']
        );

        $tourist = User::create([
            'name' => 'Unique Leaderboard Tourist ' . uniqid(),
            'email' => 'tourist_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'tourist',
            'status' => 'active',
        ]);

        // Insert multiple point rows for the SAME tourist user
        DB::table('user_points')->insert([
            ['user_id' => $tourist->id, 'total_points' => 150, 'completed_activities' => 2, 'points_since' => now()],
            ['user_id' => $tourist->id, 'total_points' => 50,  'completed_activities' => 1, 'points_since' => now()],
        ]);

        $response = $this->withSession(['user_id' => $admin->id, 'user_role' => 'lupto'])
            ->getJson('/api/lupto/leaderboard?search=' . urlencode($tourist->name));

        $response->assertStatus(200);
        $data = $response->json();

        // Must appear exactly once in the search result
        $matches = array_filter($data['users'], fn($u) => $u['user_id'] === $tourist->id);
        $this->assertCount(1, $matches, 'User must appear exactly once on the leaderboard despite multiple point rows.');

        $entry = reset($matches);
        $this->assertEquals(200, $entry['total_points'], 'Points from multiple records must be combined (150 + 50 = 200).');
        $this->assertEquals(3, $entry['completed_activities'], 'Activities must be summed (2 + 1 = 3).');
    }

    public function test_deactivated_or_inactive_user_is_immediately_removed_from_leaderboard()
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin_test@launion.gov.ph'],
            ['name' => 'Admin Tester', 'password' => bcrypt('password'), 'role' => 'lupto', 'status' => 'active']
        );

        $tourist = User::create([
            'name' => 'Deactivation Test Tourist ' . uniqid(),
            'email' => 'deact_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'tourist',
            'status' => 'active',
        ]);

        DB::table('user_points')->insert([
            'user_id' => $tourist->id,
            'total_points' => 300,
            'completed_activities' => 5,
            'points_since' => now(),
        ]);

        // Verify user is visible when active
        $resActive = $this->withSession(['user_id' => $admin->id, 'user_role' => 'lupto'])
            ->getJson('/api/lupto/leaderboard?search=' . urlencode($tourist->name));
        $this->assertCount(1, $resActive->json('users'));

        // Deactivate user
        $tourist->update(['status' => 'inactive']);

        // Verify user is removed immediately from leaderboard
        $resInactive = $this->withSession(['user_id' => $admin->id, 'user_role' => 'lupto'])
            ->getJson('/api/lupto/leaderboard?search=' . urlencode($tourist->name));
        $this->assertCount(0, $resInactive->json('users'), 'Inactive user must not appear on the leaderboard.');

        // Reactivate user
        $tourist->update(['status' => 'active']);

        // Verify user reappears on leaderboard with points
        $resReactivated = $this->withSession(['user_id' => $admin->id, 'user_role' => 'lupto'])
            ->getJson('/api/lupto/leaderboard?search=' . urlencode($tourist->name));
        $this->assertCount(1, $resReactivated->json('users'), 'Reactivated user must reappear on the leaderboard.');
        $this->assertEquals(300, $resReactivated->json('users.0.total_points'));
    }

    public function test_kpis_and_total_count_match_active_tourists_only()
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin_test@launion.gov.ph'],
            ['name' => 'Admin Tester', 'password' => bcrypt('password'), 'role' => 'lupto', 'status' => 'active']
        );

        $expectedActiveTourists = User::where('role', 'tourist')->where('status', 'active')->count();

        $response = $this->withSession(['user_id' => $admin->id, 'user_role' => 'lupto'])
            ->getJson('/api/lupto/leaderboard/kpis');

        $response->assertStatus(200);
        $kpiTotal = $response->json('kpis.total_users');

        $this->assertEquals($expectedActiveTourists, $kpiTotal, 'Leaderboard KPI total_users must match active tourist user count in User Management.');
    }
}
