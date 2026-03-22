CREATE TABLE IF NOT EXISTS `job_saved_profiles` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`   INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `cv_path`     VARCHAR(500) NOT NULL,
  `cv_filename` VARCHAR(255) NOT NULL,
  `cv_size`     INT UNSIGNED NOT NULL,
  `headline`    VARCHAR(160) NULL COMMENT 'saved cover letter headline',
  `cover_text`  TEXT NULL COMMENT 'saved cover letter body',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_saved_profile` (`tenant_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
