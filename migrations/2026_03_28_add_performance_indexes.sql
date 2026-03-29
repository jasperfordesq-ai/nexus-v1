-- Performance audit 2026-03-28: Add missing composite indexes for hot-path queries
-- Run via: sudo docker exec -i nexus-php-db mysql -u nexus -pHpW4H99dd2BNXjtl5FhHlIEitzAkjmm nexus < migrations/2026_03_28_add_performance_indexes.sql

-- messages: composite for conversation grouping & unread queries
ALTER TABLE `messages` ADD INDEX IF NOT EXISTS `idx_msg_tenant_sender_receiver` (`tenant_id`, `sender_id`, `receiver_id`);
ALTER TABLE `messages` ADD INDEX IF NOT EXISTS `idx_msg_tenant_receiver_unread` (`tenant_id`, `receiver_id`, `is_read`);

-- notifications: composite for the /poll endpoint and notification listing
ALTER TABLE `notifications` ADD INDEX IF NOT EXISTS `idx_notif_tenant_user_read_deleted` (`tenant_id`, `user_id`, `is_read`, `deleted_at`);

-- transactions: composite for WalletService balance & transaction list queries
ALTER TABLE `transactions` ADD INDEX IF NOT EXISTS `idx_txn_sender_status` (`sender_id`, `status`);
ALTER TABLE `transactions` ADD INDEX IF NOT EXISTS `idx_txn_receiver_status` (`receiver_id`, `status`);

-- likes: composite for feed batch-loading counts
ALTER TABLE `likes` ADD INDEX IF NOT EXISTS `idx_likes_tenant_target` (`tenant_id`, `target_type`, `target_id`);

-- listings: composite for the primary listing index query
ALTER TABLE `listings` ADD INDEX IF NOT EXISTS `idx_listings_tenant_status_id` (`tenant_id`, `status`, `id`);

-- events: composite for the primary event list query
ALTER TABLE `events` ADD INDEX IF NOT EXISTS `idx_events_tenant_status_start` (`tenant_id`, `status`, `start_time`);
