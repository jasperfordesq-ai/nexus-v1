-- Migration: Volunteering Module Features V1-V10
-- Purpose: Add tables and columns for volunteering module enhancements
-- Date: 2026-03-01
-- Features: Waitlist, Shift Swapping, Group Sign-ups, Skills Matching,
--           QR Check-in, Impact Certificates, Emergency Alerts, Burnout Detection

-- =========================================================================
-- V4: Skills-based volunteer matching
-- Add required_skills JSON column to vol_shifts
-- =========================================================================

-- Check if column exists before adding
SET @col_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'vol_shifts'
    AND COLUMN_NAME = 'required_skills'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `vol_shifts` ADD COLUMN `required_skills` JSON DEFAULT NULL COMMENT ''JSON array of required skill keywords''',
    'SELECT ''Column required_skills already exists on vol_shifts'' AS result'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =========================================================================
-- V7: Volunteer check-in (QR codes)
-- =========================================================================

CREATE TABLE IF NOT EXISTS `vol_shift_checkins` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tenant_id` int(11) NOT NULL,
    `shift_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `qr_token` varchar(64) NOT NULL COMMENT 'Unique QR code token',
    `checked_in_at` timestamp NULL DEFAULT NULL,
    `checked_out_at` timestamp NULL DEFAULT NULL,
    `status` enum('pending','checked_in','checked_out','no_show') NOT NULL DEFAULT 'pending',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_shift_user` (`shift_id`, `user_id`),
    UNIQUE KEY `unique_qr_token` (`qr_token`),
    KEY `idx_tenant_shift` (`tenant_id`, `shift_id`),
    KEY `idx_user_status` (`user_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='QR-based check-in tracking for volunteer shifts';

-- =========================================================================
-- V1: Shift waitlist automation
-- =========================================================================

CREATE TABLE IF NOT EXISTS `vol_shift_waitlist` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tenant_id` int(11) NOT NULL,
    `shift_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `position` int(11) NOT NULL DEFAULT 0 COMMENT 'Queue position (lower = higher priority)',
    `status` enum('waiting','notified','promoted','expired','cancelled') NOT NULL DEFAULT 'waiting',
    `notified_at` timestamp NULL DEFAULT NULL,
    `promoted_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_shift_user_waitlist` (`shift_id`, `user_id`),
    KEY `idx_tenant_shift` (`tenant_id`, `shift_id`),
    KEY `idx_status_position` (`shift_id`, `status`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Waitlist for full volunteer shifts';

-- =========================================================================
-- V2: Shift swapping
-- =========================================================================

CREATE TABLE IF NOT EXISTS `vol_shift_swap_requests` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tenant_id` int(11) NOT NULL,
    `from_user_id` int(11) NOT NULL COMMENT 'User requesting the swap',
    `to_user_id` int(11) NOT NULL COMMENT 'Target user to swap with',
    `from_shift_id` int(11) NOT NULL COMMENT 'Shift the requester is giving up',
    `to_shift_id` int(11) NOT NULL COMMENT 'Shift the requester wants',
    `status` enum('pending','accepted','rejected','admin_pending','admin_approved','admin_rejected','cancelled','expired') NOT NULL DEFAULT 'pending',
    `requires_admin_approval` tinyint(1) NOT NULL DEFAULT 0,
    `admin_id` int(11) DEFAULT NULL COMMENT 'Admin who approved/rejected',
    `message` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant` (`tenant_id`),
    KEY `idx_from_user` (`from_user_id`, `status`),
    KEY `idx_to_user` (`to_user_id`, `status`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Shift swap requests between volunteers';

-- =========================================================================
-- V3: Team/group sign-ups
-- =========================================================================

CREATE TABLE IF NOT EXISTS `vol_shift_group_reservations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tenant_id` int(11) NOT NULL,
    `shift_id` int(11) NOT NULL,
    `group_id` int(11) NOT NULL COMMENT 'References groups table',
    `reserved_slots` int(11) NOT NULL DEFAULT 1,
    `filled_slots` int(11) NOT NULL DEFAULT 0,
    `reserved_by` int(11) NOT NULL COMMENT 'Group leader who made reservation',
    `status` enum('active','cancelled','completed') NOT NULL DEFAULT 'active',
    `notes` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_shift_group` (`shift_id`, `group_id`),
    KEY `idx_tenant_group` (`tenant_id`, `group_id`),
    KEY `idx_shift_status` (`shift_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Group/team reservations for volunteer shift slots';

CREATE TABLE IF NOT EXISTS `vol_shift_group_members` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `reservation_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `status` enum('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_reservation_user` (`reservation_id`, `user_id`),
    KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Individual members within a group shift reservation';

-- =========================================================================
-- V6: Impact certificates
-- =========================================================================

CREATE TABLE IF NOT EXISTS `vol_certificates` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tenant_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `verification_code` varchar(32) NOT NULL COMMENT 'Unique code for QR verification',
    `total_hours` decimal(10,2) NOT NULL DEFAULT 0.00,
    `date_range_start` date NOT NULL,
    `date_range_end` date NOT NULL,
    `organizations` JSON DEFAULT NULL COMMENT 'JSON array of org names/hours',
    `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `downloaded_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_verification` (`verification_code`),
    KEY `idx_tenant_user` (`tenant_id`, `user_id`),
    KEY `idx_user_date` (`user_id`, `date_range_start`, `date_range_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Generated volunteer impact certificates';

-- =========================================================================
-- V9: Emergency/urgent volunteer alerts
-- =========================================================================

CREATE TABLE IF NOT EXISTS `vol_emergency_alerts` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tenant_id` int(11) NOT NULL,
    `shift_id` int(11) NOT NULL,
    `created_by` int(11) NOT NULL COMMENT 'Coordinator who created the alert',
    `priority` enum('normal','urgent','critical') NOT NULL DEFAULT 'urgent',
    `message` text NOT NULL,
    `required_skills` JSON DEFAULT NULL COMMENT 'Skills needed for this alert',
    `status` enum('active','filled','expired','cancelled') NOT NULL DEFAULT 'active',
    `filled_at` timestamp NULL DEFAULT NULL,
    `expires_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant_status` (`tenant_id`, `status`),
    KEY `idx_shift` (`shift_id`),
    KEY `idx_priority` (`priority`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Urgent volunteer fill requests sent to qualified volunteers';

CREATE TABLE IF NOT EXISTS `vol_emergency_alert_recipients` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `alert_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `notified_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `response` enum('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    `responded_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_alert_user` (`alert_id`, `user_id`),
    KEY `idx_user_response` (`user_id`, `response`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Recipients of emergency volunteer alerts';

-- =========================================================================
-- V10: Volunteer burnout detection
-- =========================================================================

CREATE TABLE IF NOT EXISTS `vol_wellbeing_alerts` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tenant_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `risk_level` enum('low','moderate','high','critical') NOT NULL DEFAULT 'moderate',
    `risk_score` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '0-100 risk score',
    `indicators` JSON NOT NULL COMMENT 'JSON object with detected risk indicators',
    `coordinator_notified` tinyint(1) NOT NULL DEFAULT 0,
    `coordinator_notes` text DEFAULT NULL,
    `status` enum('active','acknowledged','resolved','dismissed') NOT NULL DEFAULT 'active',
    `resolved_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant_user` (`tenant_id`, `user_id`),
    KEY `idx_tenant_status` (`tenant_id`, `status`),
    KEY `idx_risk_level` (`risk_level`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Burnout risk detection alerts for volunteers';
