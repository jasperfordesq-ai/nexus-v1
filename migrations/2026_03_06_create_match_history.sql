-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Author: Jasper Ford
-- See NOTICE file for attribution and acknowledgements.
--
-- Migration: 2026_03_06_create_match_history
--
-- Records every user–listing interaction event produced by the matching
-- algorithm (impressions, views, saves, contacts, dismissals, accept/decline).
-- Drives collaborative-filtering training and per-user relevance feedback.

CREATE TABLE IF NOT EXISTS match_history (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id  INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    listing_id INT UNSIGNED NOT NULL,
    action     ENUM('impression','view','save','contact','dismiss','accept','decline') NOT NULL,
    score      TINYINT UNSIGNED NULL COMMENT 'Match score at time of impression (0-100)',
    metadata   JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_mh_user_tenant (user_id, tenant_id),
    INDEX idx_mh_listing     (listing_id),
    INDEX idx_mh_action      (action),
    INDEX idx_mh_created     (created_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
