CREATE TABLE IF NOT EXISTS `salary_benchmarks` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id`     INT UNSIGNED NULL COMMENT 'NULL = global benchmark',
  `role_keyword`  VARCHAR(100) NOT NULL COMMENT 'matched against job titles',
  `industry`      VARCHAR(100) NULL,
  `location`      VARCHAR(100) NULL COMMENT 'country or region',
  `salary_min`    DECIMAL(10,2) NOT NULL,
  `salary_max`    DECIMAL(10,2) NOT NULL,
  `salary_median` DECIMAL(10,2) NOT NULL,
  `salary_type`   ENUM('hourly','monthly','annual') NOT NULL DEFAULT 'annual',
  `currency`      VARCHAR(10) NOT NULL DEFAULT 'EUR',
  `year`          SMALLINT NOT NULL DEFAULT 2026,
  `source`        VARCHAR(200) NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_benchmark_role` (`role_keyword`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed a handful of baseline global benchmarks
INSERT INTO `salary_benchmarks` (`tenant_id`,`role_keyword`,`salary_min`,`salary_max`,`salary_median`,`salary_type`,`currency`,`year`,`source`) VALUES
(NULL,'developer',45000,95000,65000,'annual','EUR',2026,'NEXUS Platform Baseline'),
(NULL,'engineer',50000,100000,70000,'annual','EUR',2026,'NEXUS Platform Baseline'),
(NULL,'designer',35000,75000,50000,'annual','EUR',2026,'NEXUS Platform Baseline'),
(NULL,'manager',55000,110000,75000,'annual','EUR',2026,'NEXUS Platform Baseline'),
(NULL,'analyst',38000,80000,55000,'annual','EUR',2026,'NEXUS Platform Baseline'),
(NULL,'coordinator',28000,55000,38000,'annual','EUR',2026,'NEXUS Platform Baseline'),
(NULL,'assistant',24000,45000,32000,'annual','EUR',2026,'NEXUS Platform Baseline'),
(NULL,'director',75000,160000,110000,'annual','EUR',2026,'NEXUS Platform Baseline'),
(NULL,'administrator',26000,52000,36000,'annual','EUR',2026,'NEXUS Platform Baseline'),
(NULL,'consultant',45000,120000,72000,'annual','EUR',2026,'NEXUS Platform Baseline');
