CREATE TABLE IF NOT EXISTS `job_scorecards` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`       INT UNSIGNED NOT NULL,
  `vacancy_id`      BIGINT UNSIGNED NOT NULL,
  `application_id`  BIGINT UNSIGNED NOT NULL,
  `reviewer_id`     INT UNSIGNED NOT NULL,
  `criteria`        JSON NOT NULL COMMENT 'array of {label, score, max_score} objects',
  `total_score`     DECIMAL(5,2) NOT NULL DEFAULT 0,
  `max_score`       DECIMAL(5,2) NOT NULL DEFAULT 100,
  `notes`           TEXT NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scorecard` (`application_id`, `reviewer_id`),
  INDEX `idx_scorecard_vacancy` (`tenant_id`, `vacancy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
