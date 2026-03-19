-- Volunteering Module Gap Analysis: All new tables
-- Phases 1-8,10: Expenses, Guardian Consent, Safeguarding Training/Incidents,
-- Reminders, Custom Forms, Accessibility, Outbound Webhooks, Community Projects, Donations
-- 2026-03-16

-- ============================================================================
-- PHASE 1: Expense Reimbursement
-- ============================================================================

CREATE TABLE IF NOT EXISTS `vol_expenses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `organization_id` INT UNSIGNED DEFAULT NULL,
  `opportunity_id` INT UNSIGNED DEFAULT NULL,
  `shift_id` INT UNSIGNED DEFAULT NULL,
  `expense_type` ENUM('travel','meals','supplies','equipment','parking','other') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'EUR',
  `description` TEXT DEFAULT NULL,
  `receipt_path` VARCHAR(500) DEFAULT NULL,
  `receipt_filename` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
  `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by` INT UNSIGNED DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `review_notes` TEXT DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `payment_reference` VARCHAR(100) DEFAULT NULL,
  INDEX `idx_vol_expenses_tenant_user` (`tenant_id`, `user_id`),
  INDEX `idx_vol_expenses_tenant_org` (`tenant_id`, `organization_id`),
  INDEX `idx_vol_expenses_status` (`tenant_id`, `status`),
  INDEX `idx_vol_expenses_date` (`tenant_id`, `submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vol_expense_policies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `organization_id` INT UNSIGNED DEFAULT NULL,
  `expense_type` VARCHAR(50) NOT NULL,
  `max_amount` DECIMAL(10,2) DEFAULT NULL,
  `max_monthly` DECIMAL(10,2) DEFAULT NULL,
  `requires_receipt_above` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `requires_approval` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_vol_expense_policy` (`tenant_id`, `organization_id`, `expense_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PHASE 2: Guardian Consent
-- ============================================================================

CREATE TABLE IF NOT EXISTS `vol_guardian_consents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `minor_user_id` INT UNSIGNED NOT NULL,
  `guardian_name` VARCHAR(255) NOT NULL,
  `guardian_email` VARCHAR(255) NOT NULL,
  `guardian_phone` VARCHAR(50) DEFAULT NULL,
  `relationship` VARCHAR(100) NOT NULL,
  `consent_token` VARCHAR(64) NOT NULL,
  `consent_given_at` DATETIME DEFAULT NULL,
  `consent_ip` VARCHAR(45) DEFAULT NULL,
  `consent_withdrawn_at` DATETIME DEFAULT NULL,
  `opportunity_id` INT UNSIGNED DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `status` ENUM('pending','active','expired','withdrawn') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_guardian_consent_minor` (`tenant_id`, `minor_user_id`),
  INDEX `idx_guardian_consent_token` (`consent_token`),
  INDEX `idx_guardian_consent_status` (`tenant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PHASE 3: Safeguarding Training and Incidents
-- ============================================================================

CREATE TABLE IF NOT EXISTS `vol_safeguarding_training` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `training_type` ENUM('children_first','vulnerable_adults','first_aid','manual_handling','other') NOT NULL,
  `training_name` VARCHAR(255) DEFAULT NULL,
  `provider` VARCHAR(255) DEFAULT NULL,
  `certificate_reference` VARCHAR(255) DEFAULT NULL,
  `completed_at` DATE NOT NULL,
  `expires_at` DATE DEFAULT NULL,
  `document_path` VARCHAR(500) DEFAULT NULL,
  `verified_by` INT UNSIGNED DEFAULT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `status` ENUM('pending','verified','expired','rejected') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_safeguarding_training_tenant_user` (`tenant_id`, `user_id`),
  INDEX `idx_safeguarding_training_expiry` (`tenant_id`, `expires_at`),
  INDEX `idx_safeguarding_training_status` (`tenant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vol_safeguarding_incidents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `shift_id` INT UNSIGNED DEFAULT NULL,
  `opportunity_id` INT UNSIGNED DEFAULT NULL,
  `organization_id` INT UNSIGNED DEFAULT NULL,
  `reported_by` INT UNSIGNED NOT NULL,
  `subject_user_id` INT UNSIGNED DEFAULT NULL,
  `incident_type` ENUM('concern','allegation','disclosure','near_miss','other') NOT NULL,
  `severity` ENUM('low','medium','high','critical') NOT NULL,
  `description` TEXT NOT NULL,
  `action_taken` TEXT DEFAULT NULL,
  `dlp_user_id` INT UNSIGNED DEFAULT NULL,
  `dlp_notified_at` DATETIME DEFAULT NULL,
  `tusla_notified` TINYINT(1) NOT NULL DEFAULT 0,
  `tusla_reference` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('open','investigating','resolved','escalated','closed') NOT NULL DEFAULT 'open',
  `resolution_notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_safeguarding_incidents_tenant` (`tenant_id`),
  INDEX `idx_safeguarding_incidents_shift` (`tenant_id`, `shift_id`),
  INDEX `idx_safeguarding_incidents_status` (`tenant_id`, `status`),
  INDEX `idx_safeguarding_incidents_severity` (`tenant_id`, `severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DLP role assignment per organization (safe idempotent ALTERs)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vol_organizations' AND COLUMN_NAME = 'dlp_user_id');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `vol_organizations` ADD COLUMN `dlp_user_id` INT UNSIGNED DEFAULT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vol_organizations' AND COLUMN_NAME = 'deputy_dlp_user_id');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `vol_organizations` ADD COLUMN `deputy_dlp_user_id` INT UNSIGNED DEFAULT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================================
-- PHASE 5: Automated Reminders
-- ============================================================================

CREATE TABLE IF NOT EXISTS `vol_reminder_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `reminder_type` ENUM('pre_shift','post_shift_feedback','lapsed_volunteer','credential_expiry','training_expiry') NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `hours_before` INT DEFAULT NULL,
  `hours_after` INT DEFAULT NULL,
  `days_inactive` INT DEFAULT NULL,
  `days_before_expiry` INT DEFAULT NULL,
  `email_template` TEXT DEFAULT NULL,
  `push_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `email_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `sms_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_vol_reminder_setting` (`tenant_id`, `reminder_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vol_reminders_sent` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `reminder_type` VARCHAR(50) NOT NULL,
  `reference_id` INT UNSIGNED DEFAULT NULL,
  `channel` ENUM('email','push','sms') NOT NULL,
  `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_vol_reminders_tenant_user` (`tenant_id`, `user_id`, `reminder_type`),
  INDEX `idx_vol_reminders_ref` (`tenant_id`, `reminder_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PHASE 6: Custom Registration Forms and Accessibility
-- ============================================================================

CREATE TABLE IF NOT EXISTS `vol_custom_fields` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `organization_id` INT UNSIGNED DEFAULT NULL,
  `field_label` VARCHAR(255) NOT NULL,
  `field_key` VARCHAR(100) NOT NULL,
  `field_type` ENUM('text','textarea','select','checkbox','radio','date','file','number','email','phone') NOT NULL,
  `field_options` JSON DEFAULT NULL,
  `placeholder` VARCHAR(255) DEFAULT NULL,
  `help_text` TEXT DEFAULT NULL,
  `validation_rules` JSON DEFAULT NULL,
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `display_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `applies_to` ENUM('application','opportunity','shift','profile') NOT NULL DEFAULT 'application',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_vol_custom_fields_tenant_org` (`tenant_id`, `organization_id`),
  INDEX `idx_vol_custom_fields_applies` (`tenant_id`, `applies_to`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vol_custom_field_values` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `custom_field_id` INT UNSIGNED NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` INT UNSIGNED NOT NULL,
  `field_value` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_vol_field_values_field` (`custom_field_id`, `entity_type`, `entity_id`),
  INDEX `idx_vol_field_values_entity` (`tenant_id`, `entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vol_accessibility_needs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `need_type` ENUM('mobility','visual','hearing','cognitive','dietary','language','other') NOT NULL,
  `description` TEXT DEFAULT NULL,
  `accommodations_required` TEXT DEFAULT NULL,
  `emergency_contact_name` VARCHAR(255) DEFAULT NULL,
  `emergency_contact_phone` VARCHAR(50) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_vol_accessibility` (`tenant_id`, `user_id`, `need_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PHASE 7: Outbound Webhooks
-- ============================================================================

CREATE TABLE IF NOT EXISTS `outbound_webhooks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `url` VARCHAR(2048) NOT NULL,
  `secret` VARCHAR(255) DEFAULT NULL,
  `events` JSON NOT NULL,
  `headers` JSON DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_triggered_at` DATETIME DEFAULT NULL,
  `failure_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_retries` TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_outbound_webhooks_tenant` (`tenant_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `outbound_webhook_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `webhook_id` INT UNSIGNED NOT NULL,
  `event_type` VARCHAR(100) NOT NULL,
  `payload` JSON NOT NULL,
  `response_code` SMALLINT UNSIGNED DEFAULT NULL,
  `response_body` TEXT DEFAULT NULL,
  `response_time_ms` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('success','failed','pending','retrying') NOT NULL DEFAULT 'pending',
  `attempt_count` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `next_retry_at` DATETIME DEFAULT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_webhook_logs_webhook` (`webhook_id`, `status`),
  INDEX `idx_webhook_logs_tenant_date` (`tenant_id`, `attempted_at`),
  INDEX `idx_webhook_logs_retry` (`status`, `next_retry_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PHASE 8: Community-led Projects
-- ============================================================================

CREATE TABLE IF NOT EXISTS `vol_community_projects` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `proposed_by` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `latitude` DECIMAL(10,8) DEFAULT NULL,
  `longitude` DECIMAL(11,8) DEFAULT NULL,
  `target_volunteers` INT UNSIGNED DEFAULT NULL,
  `proposed_date` DATE DEFAULT NULL,
  `skills_needed` JSON DEFAULT NULL,
  `estimated_hours` DECIMAL(5,1) DEFAULT NULL,
  `status` ENUM('proposed','under_review','approved','rejected','active','completed','cancelled') NOT NULL DEFAULT 'proposed',
  `reviewed_by` INT UNSIGNED DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `review_notes` TEXT DEFAULT NULL,
  `opportunity_id` INT UNSIGNED DEFAULT NULL,
  `supporter_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_vol_community_projects_tenant` (`tenant_id`),
  INDEX `idx_vol_community_projects_status` (`tenant_id`, `status`),
  INDEX `idx_vol_community_projects_proposer` (`tenant_id`, `proposed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vol_community_project_supporters` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `project_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `message` TEXT DEFAULT NULL,
  `supported_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_project_supporter` (`project_id`, `user_id`),
  INDEX `idx_supporters_tenant` (`tenant_id`, `project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PHASE 10: Embedded Donations
-- ============================================================================

CREATE TABLE IF NOT EXISTS `vol_donations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `opportunity_id` INT UNSIGNED DEFAULT NULL,
  `community_project_id` INT UNSIGNED DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'EUR',
  `payment_method` VARCHAR(50) DEFAULT NULL,
  `payment_reference` VARCHAR(255) DEFAULT NULL,
  `donor_name` VARCHAR(255) DEFAULT NULL,
  `donor_email` VARCHAR(255) DEFAULT NULL,
  `message` TEXT DEFAULT NULL,
  `is_anonymous` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('pending','completed','refunded','failed') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_vol_donations_tenant` (`tenant_id`),
  INDEX `idx_vol_donations_opportunity` (`tenant_id`, `opportunity_id`),
  INDEX `idx_vol_donations_project` (`tenant_id`, `community_project_id`),
  INDEX `idx_vol_donations_status` (`tenant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vol_giving_days` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `target_amount` DECIMAL(10,2) DEFAULT NULL,
  `target_hours` DECIMAL(10,1) DEFAULT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_vol_giving_days_tenant` (`tenant_id`),
  INDEX `idx_vol_giving_days_dates` (`tenant_id`, `start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
