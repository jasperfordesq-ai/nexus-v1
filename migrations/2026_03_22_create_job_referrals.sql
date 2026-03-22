CREATE TABLE IF NOT EXISTS `job_referrals` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`       INT UNSIGNED NOT NULL,
  `vacancy_id`      BIGINT UNSIGNED NOT NULL,
  `referrer_user_id` INT UNSIGNED NULL COMMENT 'NULL = anonymous share',
  `referred_user_id` INT UNSIGNED NULL COMMENT 'set when referred user applies',
  `ref_token`       VARCHAR(64) NOT NULL,
  `applied`         TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `applied_at`      DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ref_token` (`ref_token`),
  INDEX `idx_referrals_vacancy` (`tenant_id`, `vacancy_id`),
  INDEX `idx_referrals_referrer` (`tenant_id`, `referrer_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
