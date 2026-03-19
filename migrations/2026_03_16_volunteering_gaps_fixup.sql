-- Fixup migration: Add columns missing from initial gap migration
-- that services need for full functionality
-- 2026-03-16

-- vol_safeguarding_incidents: add title, incident_date, involved_user_id, category, assigned_to, resolved_at
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vol_safeguarding_incidents' AND COLUMN_NAME = 'title');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `vol_safeguarding_incidents` ADD COLUMN `title` VARCHAR(255) DEFAULT NULL AFTER `reported_by`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vol_safeguarding_incidents' AND COLUMN_NAME = 'incident_date');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `vol_safeguarding_incidents` ADD COLUMN `incident_date` DATE DEFAULT NULL AFTER `severity`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vol_safeguarding_incidents' AND COLUMN_NAME = 'involved_user_id');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `vol_safeguarding_incidents` ADD COLUMN `involved_user_id` INT UNSIGNED DEFAULT NULL AFTER `subject_user_id`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vol_safeguarding_incidents' AND COLUMN_NAME = 'category');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `vol_safeguarding_incidents` ADD COLUMN `category` VARCHAR(100) DEFAULT ''general'' AFTER `incident_type`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vol_safeguarding_incidents' AND COLUMN_NAME = 'assigned_to');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `vol_safeguarding_incidents` ADD COLUMN `assigned_to` INT UNSIGNED DEFAULT NULL AFTER `dlp_notified_at`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vol_safeguarding_incidents' AND COLUMN_NAME = 'resolved_at');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `vol_safeguarding_incidents` ADD COLUMN `resolved_at` DATETIME DEFAULT NULL AFTER `status`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- vol_safeguarding_training: add training_name, certificate_url, notes columns
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vol_safeguarding_training' AND COLUMN_NAME = 'certificate_url');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `vol_safeguarding_training` ADD COLUMN `certificate_url` VARCHAR(500) DEFAULT NULL AFTER `document_path`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vol_safeguarding_training' AND COLUMN_NAME = 'notes');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `vol_safeguarding_training` ADD COLUMN `notes` TEXT DEFAULT NULL AFTER `certificate_url`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vol_safeguarding_training' AND COLUMN_NAME = 'updated_at');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `vol_safeguarding_training` ADD COLUMN `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- vol_donations: add giving_day_id column
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vol_donations' AND COLUMN_NAME = 'giving_day_id');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `vol_donations` ADD COLUMN `giving_day_id` INT UNSIGNED DEFAULT NULL AFTER `community_project_id`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- vol_giving_days: add goal_amount alias and raised_amount
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vol_giving_days' AND COLUMN_NAME = 'goal_amount');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `vol_giving_days` ADD COLUMN `goal_amount` DECIMAL(10,2) DEFAULT NULL AFTER `target_amount`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vol_giving_days' AND COLUMN_NAME = 'raised_amount');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `vol_giving_days` ADD COLUMN `raised_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `goal_amount`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- vol_giving_days: add created_by if missing
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vol_giving_days' AND COLUMN_NAME = 'created_by');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `vol_giving_days` ADD COLUMN `created_by` INT UNSIGNED DEFAULT NULL AFTER `is_active`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- vol_custom_field_values: add UNIQUE constraint for ON DUPLICATE KEY UPDATE to work
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vol_custom_field_values' AND INDEX_NAME = 'uk_field_entity');
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE `vol_custom_field_values` ADD UNIQUE KEY `uk_field_entity` (`custom_field_id`, `entity_type`, `entity_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- users: add date_of_birth for guardian consent minor detection
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'date_of_birth');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `date_of_birth` DATE DEFAULT NULL AFTER `email`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
