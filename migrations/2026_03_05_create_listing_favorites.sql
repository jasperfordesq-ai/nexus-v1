-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Author: Jasper Ford
-- See NOTICE file for attribution and acknowledgements.
--
-- Migration: 2026_03_05_create_listing_favorites
--
-- Creates the listing_favorites table used by CollaborativeFilteringService
-- to record explicit saves (strongest implicit-feedback signal for item-based CF).

CREATE TABLE IF NOT EXISTS listing_favorites (
  id         INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED   NOT NULL,
  listing_id INT UNSIGNED   NOT NULL,
  tenant_id  INT UNSIGNED   NOT NULL,
  created_at TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,

  -- One save per user/listing pair
  UNIQUE KEY uk_user_listing (user_id, listing_id),

  -- Fast lookups by listing (which users saved this?)
  INDEX idx_listing (listing_id),

  -- Fast lookups by tenant (for CF training queries)
  INDEX idx_tenant (tenant_id),

  -- Fast lookups by user (what has this user saved?)
  INDEX idx_user (user_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
