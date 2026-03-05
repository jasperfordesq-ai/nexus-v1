-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Migration: Add hours_estimate column to listings table
-- Created: 2026-02-27
--
-- The listings table was missing the hours_estimate column that the React frontend
-- sends when creating/editing listings. ListingService.php also incorrectly referenced
-- 'estimated_hours' instead of 'hours_estimate', causing a PDOException fatal error
-- on GET /api/v2/listings.

ALTER TABLE `listings`
    ADD COLUMN IF NOT EXISTS `hours_estimate` DECIMAL(5,2) NULL DEFAULT NULL;
