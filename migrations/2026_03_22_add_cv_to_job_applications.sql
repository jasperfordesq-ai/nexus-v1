-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
ALTER TABLE job_vacancy_applications
    ADD COLUMN cv_path     VARCHAR(500)  NULL AFTER message,
    ADD COLUMN cv_filename VARCHAR(255)  NULL AFTER cv_path,
    ADD COLUMN cv_size     INT           NULL AFTER cv_filename;
