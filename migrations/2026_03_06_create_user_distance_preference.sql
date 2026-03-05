-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Author: Jasper Ford
-- See NOTICE file for attribution and acknowledgements.
--
-- Migration: 2026_03_06_create_user_distance_preference
--
-- Stores the maximum distance (km) a user is willing to travel, as learned
-- from their interaction history with location-bound listings.
-- CrossModuleMatchingService uses this to apply a soft distance penalty
-- rather than a hard cutoff, improving recall for sparse areas.

CREATE TABLE IF NOT EXISTS user_distance_preference (
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id              INT UNSIGNED NOT NULL,
    user_id                INT UNSIGNED NOT NULL,
    learned_max_distance_km FLOAT NOT NULL DEFAULT 25.0,
    sample_count           INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'How many interactions trained this',
    updated_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- One row per (tenant, user) pair
    UNIQUE KEY uk_user_distance (tenant_id, user_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
