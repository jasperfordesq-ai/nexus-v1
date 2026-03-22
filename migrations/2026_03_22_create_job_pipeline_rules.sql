CREATE TABLE IF NOT EXISTS `job_pipeline_rules` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`       INT UNSIGNED NOT NULL,
  `vacancy_id`      BIGINT UNSIGNED NOT NULL,
  `name`            VARCHAR(160) NOT NULL,
  `trigger_stage`   VARCHAR(50) NOT NULL COMMENT 'stage that triggers evaluation: applied, screening, etc.',
  `condition_days`  SMALLINT UNSIGNED NOT NULL DEFAULT 7 COMMENT 'days in stage before rule fires',
  `action`          ENUM('move_stage','reject','notify_reviewer') NOT NULL DEFAULT 'move_stage',
  `action_target`   VARCHAR(50) NULL COMMENT 'target stage for move_stage action',
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `last_run_at`     DATETIME NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pipeline_vacancy` (`tenant_id`, `vacancy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
