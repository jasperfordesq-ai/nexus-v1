-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Description: Add latitude/longitude to job_vacancies and vol_opportunities
--              so CrossModuleMatchingService can do geo-proximity scoring.

ALTER TABLE job_vacancies
    ADD COLUMN IF NOT EXISTS latitude  DECIMAL(10,8) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) NULL DEFAULT NULL;

ALTER TABLE vol_opportunities
    ADD COLUMN IF NOT EXISTS latitude  DECIMAL(10,8) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) NULL DEFAULT NULL;

CREATE INDEX IF NOT EXISTS idx_jv_geo  ON job_vacancies    (latitude, longitude);
CREATE INDEX IF NOT EXISTS idx_vol_geo ON vol_opportunities (latitude, longitude);
