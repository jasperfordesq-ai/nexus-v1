-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Author: Jasper Ford
-- See NOTICE file for attribution and acknowledgements.
--
-- Migration: 2026_03_06_add_category_hierarchy
--
-- Adds parent_id and slug to the categories table to support hierarchical
-- category matching. CrossModuleMatchingService uses the parent relationship
-- to apply partial-match scoring when an exact category hit is absent.
--
-- NOTE: No FK constraint on parent_id — self-referential FKs complicate
-- bulk inserts during seeding. Orphaned parents are handled gracefully in
-- application logic.

ALTER TABLE categories ADD COLUMN IF NOT EXISTS parent_id INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE categories ADD COLUMN IF NOT EXISTS slug VARCHAR(100) NULL DEFAULT NULL;

-- Index for fast parent-lookup queries (fetch all children of a parent)
ALTER TABLE categories ADD INDEX IF NOT EXISTS idx_cat_parent (parent_id);
