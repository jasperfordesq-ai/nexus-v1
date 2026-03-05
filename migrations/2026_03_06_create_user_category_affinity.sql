-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Author: Jasper Ford
-- See NOTICE file for attribution and acknowledgements.
--
-- Migration: 2026_03_06_create_user_category_affinity
--
-- Stores per-user, per-category affinity scores (0.0–1.0) learned from
-- interaction history. Scores decay over time to reflect shifting interests.
-- Used by CrossModuleMatchingService to weight category relevance in ranking.

CREATE TABLE IF NOT EXISTS user_category_affinity (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           INT UNSIGNED NOT NULL,
    user_id             INT UNSIGNED NOT NULL,
    category_id         INT UNSIGNED NOT NULL,
    affinity_score      FLOAT NOT NULL DEFAULT 0.5 COMMENT 'Range 0.0-1.0, decays over time',
    interaction_count   INT UNSIGNED NOT NULL DEFAULT 0,
    last_interaction_at TIMESTAMP NULL DEFAULT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- One row per (tenant, user, category) triple
    UNIQUE KEY uk_user_category (tenant_id, user_id, category_id),

    INDEX idx_uca_tenant_user (tenant_id, user_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
