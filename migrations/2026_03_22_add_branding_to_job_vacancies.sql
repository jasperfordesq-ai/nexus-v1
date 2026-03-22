-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Author: Jasper Ford
-- See NOTICE file for attribution and acknowledgements.

ALTER TABLE `job_vacancies`
  ADD COLUMN `tagline`        VARCHAR(160)  NULL AFTER `description`,
  ADD COLUMN `video_url`      VARCHAR(500)  NULL AFTER `tagline`,
  ADD COLUMN `culture_photos` JSON          NULL AFTER `video_url`,
  ADD COLUMN `company_size`   VARCHAR(50)   NULL AFTER `culture_photos`,
  ADD COLUMN `benefits`       JSON          NULL AFTER `company_size`;
