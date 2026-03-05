-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Author: Jasper Ford
-- See NOTICE file for attribution and acknowledgements.
--
-- Migration: 2026_03_06_add_users_availability_interests
--
-- Adds availability and interests columns to users, expected by
-- CrossModuleMatchingService when building a user's preference profile.
-- Both columns are nullable so existing rows are unaffected.

ALTER TABLE users ADD COLUMN IF NOT EXISTS availability VARCHAR(255) NULL DEFAULT NULL
    COMMENT 'e.g. weekdays, weekends, flexible';

ALTER TABLE users ADD COLUMN IF NOT EXISTS interests TEXT NULL DEFAULT NULL
    COMMENT 'Comma-separated interest keywords';
