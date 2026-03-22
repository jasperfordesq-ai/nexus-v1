-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later

CREATE TABLE IF NOT EXISTS job_interviews (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       INT UNSIGNED    NOT NULL,
    vacancy_id      BIGINT UNSIGNED NOT NULL,
    application_id  BIGINT UNSIGNED NOT NULL,
    proposed_by     BIGINT UNSIGNED NOT NULL,   -- user_id of whoever proposed
    interview_type  VARCHAR(30)     NOT NULL DEFAULT 'video',  -- video|phone|in_person
    scheduled_at    DATETIME        NOT NULL,
    duration_mins   SMALLINT        NOT NULL DEFAULT 60,
    location_notes  TEXT            NULL,        -- meeting link or address
    status          VARCHAR(30)     NOT NULL DEFAULT 'proposed',  -- proposed|accepted|declined|completed|cancelled
    candidate_notes TEXT            NULL,
    interviewer_notes TEXT          NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ji_tenant_vacancy (tenant_id, vacancy_id),
    INDEX idx_ji_application (application_id),
    FOREIGN KEY (application_id) REFERENCES job_vacancy_applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
