CREATE TABLE IF NOT EXISTS `job_vacancy_team` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED NOT NULL,
  `vacancy_id`  BIGINT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `role`        ENUM('reviewer', 'manager') NOT NULL DEFAULT 'reviewer',
  `added_by`    INT UNSIGNED NOT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_team_member` (`vacancy_id`, `user_id`),
  INDEX `idx_team_vacancy` (`tenant_id`, `vacancy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
