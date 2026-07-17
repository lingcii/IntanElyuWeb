-- ============================================================
-- Performance Indexes for intan_elyu_db
-- Run once via phpMyAdmin or: mysql -u root intan_elyu_db < performance_indexes.sql
-- All statements use IF NOT EXISTS so this file is safe to re-run.
-- ============================================================

-- tourist_spots: status index
-- Used by: DashboardController WHERE status='approved'/'pending'
--          AnalyticsController WHERE status='approved', MapController WHERE status='approved'
ALTER TABLE `tourist_spots`
    ADD INDEX IF NOT EXISTS `idx_ts_status` (`status`);

-- tourist_spots: composite (municipality_id, status)
-- Used by: DashboardController scoped to municipality + status, TouristSpotController
ALTER TABLE `tourist_spots`
    ADD INDEX IF NOT EXISTS `idx_ts_muni_status` (`municipality_id`, `status`);

-- tourist_spots: category index
-- Used by: AnalyticsController GROUP BY category, WHERE category = ?
ALTER TABLE `tourist_spots`
    ADD INDEX IF NOT EXISTS `idx_ts_category` (`category`);

-- tourist_spots: classification_status index
-- Used by: AnalyticsController, TouristSpotController filters
ALTER TABLE `tourist_spots`
    ADD INDEX IF NOT EXISTS `idx_ts_classification` (`classification_status`);

-- analytics: composite (year, month)
-- Used by: AnalyticsController WHERE year=? AND month=?, DashboardController visitor trends
ALTER TABLE `analytics`
    ADD INDEX IF NOT EXISTS `idx_analytics_year_month` (`year`, `month`);

-- analytics: composite (municipality_id, year)
-- Used by: AnalyticsController municipality-scoped year queries
ALTER TABLE `analytics`
    ADD INDEX IF NOT EXISTS `idx_analytics_muni_year` (`municipality_id`, `year`);

-- users: composite (role, status)
-- Used by: UserController WHERE role=? AND status=?, DashboardController activeUsers count
ALTER TABLE `users`
    ADD INDEX IF NOT EXISTS `idx_users_role_status` (`role`, `status`);

-- alerts: is_read + created_at
-- Used by: DashboardController WHERE is_read=false ORDER BY created_at DESC
ALTER TABLE `alerts`
    ADD INDEX IF NOT EXISTS `idx_alerts_unread` (`is_read`, `created_at`);
