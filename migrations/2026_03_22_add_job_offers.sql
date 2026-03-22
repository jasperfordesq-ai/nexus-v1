-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later

CREATE TABLE IF NOT EXISTS job_offers (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED    NOT NULL,
    vacancy_id      BIGINT UNSIGNED NOT NULL,
    application_id  BIGINT UNSIGNED NOT NULL UNIQUE,  -- one offer per application
    salary_offered  DECIMAL(12,2)   NULL,
    salary_currency VARCHAR(3)      NULL DEFAULT 'EUR',
    salary_type     VARCHAR(20)     NULL,   -- hourly|monthly|annual
    start_date      DATE            NULL,
    message         TEXT            NULL,
    status          VARCHAR(30)     NOT NULL DEFAULT 'pending',  -- pending|accepted|rejected|withdrawn|expired
    responded_at    DATETIME        NULL,
    expires_at      DATETIME        NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_jo_tenant_vacancy (tenant_id, vacancy_id),
    INDEX idx_jo_application (application_id),
    FOREIGN KEY (application_id) REFERENCES job_vacancy_applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
