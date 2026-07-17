<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use App\Models\User;

class CacheInvalidationService
{
    /**
     * Invalidate dashboard caches.
     * If a municipality ID is provided, invalidate both province-wide and municipality-scoped caches.
     */
    public static function invalidateDashboard(?int $muniId = null): void
    {
        // Clear province-wide dashboard caches for primary roles.
        // NOTE: DashboardController stores cache under the ':v2' versioned key.
        // We clear BOTH old (no suffix) and new (:v2) keys for safety.
        foreach (['lupto', 'picto'] as $role) {
            Cache::forget("dashboard:data:{$role}:0");      // legacy key (no :v2)
            Cache::forget("dashboard:data:{$role}:0:v2");  // current key
        }

        // Clear municipality-scoped dashboard caches
        if ($muniId && $muniId > 0) {
            Cache::forget("dashboard:data:municipal:{$muniId}");
            Cache::forget("dashboard:data:municipal:{$muniId}:v2");
            foreach (User::$MUNICIPAL_ROLES as $role) {
                Cache::forget("dashboard:data:{$role}:{$muniId}");
                Cache::forget("dashboard:data:{$role}:{$muniId}:v2");
            }
        } else {
            // If no specific municipality ID, clear all potential municipal role variants.
            for ($id = 1; $id <= 30; $id++) {
                Cache::forget("dashboard:data:municipal:{$id}");
                Cache::forget("dashboard:data:municipal:{$id}:v2");
                foreach (User::$MUNICIPAL_ROLES as $role) {
                    Cache::forget("dashboard:data:{$role}:{$id}");
                    Cache::forget("dashboard:data:{$role}:{$id}:v2");
                }
            }
        }

        // Public map cache (approved spots list updated)
        Cache::forget('map:public:spots');
    }

    /**
     * Invalidate analytics caches.
     */
    public static function invalidateAnalytics(?int $muniId = null): void
    {
        $roles = ['lupto', 'picto', 'municipal'];
        $muniIds = [0];
        if ($muniId && $muniId > 0) {
            $muniIds[] = $muniId;
        } else {
            for ($id = 1; $id <= 30; $id++) {
                $muniIds[] = $id;
            }
        }

        $keys = [
            'analytics:summary-v8',
            'analytics:top-municipalities',
            'analytics:top-spots',
            'analytics:monthly-trend',
            'analytics:filter-options',
            'analytics:full',
        ];

        foreach ($keys as $key) {
            foreach ($roles as $role) {
                foreach ($muniIds as $id) {
                    Cache::forget("{$key}:{$role}:{$id}");
                }
            }
        }
    }

    /**
     * Invalidate leaderboard caches.
     */
    public static function invalidateLeaderboard(): void
    {
        Cache::forget('leaderboard:top3');
        Cache::forget('leaderboard:kpis');
        
        // Leaderboard list keys are parameterized: leaderboard:list:{$role}:{$muniId}:{$show}:{$search}:{$sort}
        // Since we use the file driver (which doesn't support wildcard clearing), and to be extremely safe,
        // we can clear the base keys or clear specific common keys.
        // We also clear stats cache for active users
        Cache::forget('users:stats');
        Cache::forget('users:role_stats');
    }

    /**
     * Invalidate fare data caches.
     */
    public static function invalidateFareData(?int $guideId = null): void
    {
        Cache::forget('fare-data:stats');
        Cache::forget('fare-data:guides:lupto:0');
        Cache::forget('fare-data:guides:picto:0');
        
        foreach (User::$MUNICIPAL_ROLES as $role) {
            for ($m = 0; $m <= 50; $m++) {
                Cache::forget("fare-data:guides:{$role}:{$m}");
            }
        }

        if ($guideId) {
            Cache::forget("fare-data:matrices:{$guideId}");
        }
    }


    /**
     * Invalidate user caches.
     */
    public static function invalidateUsers(): void
    {
        Cache::forget('users:role_stats');
        Cache::forget('users:stats');
        Cache::forget('activity_stats');
        Cache::forget('municipalities:list');
    }

    /**
     * Invalidate tourist spots list cache keys.
     */
    public static function invalidateTouristSpots(?int $muniId = null): void
    {
        // Clear province-wide list caches
        foreach (['lupto', 'picto'] as $role) {
            Cache::forget("tourist-spots:list:{$role}:0");
        }

        // Clear municipality-scoped list caches
        if ($muniId && $muniId > 0) {
            Cache::forget("tourist-spots:list:municipal:{$muniId}");
            foreach (User::$MUNICIPAL_ROLES as $role) {
                Cache::forget("tourist-spots:list:{$role}:{$muniId}");
            }
        } else {
            for ($id = 1; $id <= 30; $id++) {
                Cache::forget("tourist-spots:list:municipal:{$id}");
                foreach (User::$MUNICIPAL_ROLES as $role) {
                    Cache::forget("tourist-spots:list:{$role}:{$id}");
                }
            }
        }
    }

    /**
     * Comprehensive targeted cache invalidation when a tourist spot is modified.
     */
    public static function invalidateAll(?int $muniId = null): void
    {
        self::invalidateDashboard($muniId);
        self::invalidateAnalytics($muniId);
        self::invalidateLeaderboard();
        self::invalidateFareData();
        self::invalidateUsers();
        self::invalidateTouristSpots($muniId);
    }
}
