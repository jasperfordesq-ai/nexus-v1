-- Copyright (c) 2024-2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Author: Jasper Ford
-- See NOTICE file for attribution and acknowledgements.

-- ============================================================================
-- Migration: Fix volunteering module schema issues
-- Date: 2026-03-03
-- Fixes: nullable tenant_id on vol_opportunities, missing indexes,
--        missing tenant_id columns on child tables, missing tables for V5/V8
-- ============================================================================
-- This migration is fully idempotent — safe to run multiple times.
-- All operations use IF EXISTS / IF NOT EXISTS guards.
-- ============================================================================


-- =========================================================================
-- 1. Fix vol_opportunities.tenant_id — make NOT NULL
-- =========================================================================
-- The base schema defines tenant_id as INT(11) DEFAULT NULL which breaks
-- multi-tenant isolation. Backfill from organization, then enforce NOT NULL.

-- Step 1a: Backfill NULL tenant_ids from the associated organization
UPDATE vol_opportunities o
    JOIN vol_organizations org ON o.organization_id = org.id
SET o.tenant_id = org.tenant_id
WHERE o.tenant_id IS NULL;

-- Step 1b: Set any remaining NULLs to tenant 1 (default fallback)
UPDATE vol_opportunities SET tenant_id = 1 WHERE tenant_id IS NULL;

-- Step 1c: Alter column to NOT NULL with default
ALTER TABLE vol_opportunities
    MODIFY COLUMN tenant_id INT(11) NOT NULL DEFAULT 1;


-- =========================================================================
-- 2. Add index on vol_opportunities.tenant_id
-- =========================================================================
-- The base schema has no index on tenant_id, which hurts tenant-scoped queries.

CREATE INDEX IF NOT EXISTS idx_vol_opp_tenant ON vol_opportunities(tenant_id);


-- =========================================================================
-- 3. Add tenant_id to vol_shift_group_members
-- =========================================================================
-- This child table of vol_shift_group_reservations was created without
-- tenant_id, making tenant-scoped queries require a JOIN to the parent.

-- Step 3a: Add the column (nullable first for safety)
ALTER TABLE vol_shift_group_members
    ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER reservation_id;

-- Step 3b: Backfill from the parent reservation
UPDATE vol_shift_group_members gm
    JOIN vol_shift_group_reservations r ON gm.reservation_id = r.id
SET gm.tenant_id = r.tenant_id
WHERE gm.tenant_id = 1 AND r.tenant_id != 1;

-- Step 3c: Add index for tenant-scoped lookups
ALTER TABLE vol_shift_group_members
    ADD INDEX IF NOT EXISTS idx_gm_tenant (tenant_id);


-- =========================================================================
-- 4. Add tenant_id to vol_emergency_alert_recipients
-- =========================================================================
-- This child table of vol_emergency_alerts was created without tenant_id,
-- making tenant-scoped queries require a JOIN to the parent.

-- Step 4a: Add the column
ALTER TABLE vol_emergency_alert_recipients
    ADD COLUMN IF NOT EXISTS tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER alert_id;

-- Step 4b: Backfill from the parent alert
UPDATE vol_emergency_alert_recipients ar
    JOIN vol_emergency_alerts a ON ar.alert_id = a.id
SET ar.tenant_id = a.tenant_id
WHERE ar.tenant_id = 1 AND a.tenant_id != 1;

-- Step 4c: Add index for tenant-scoped lookups
ALTER TABLE vol_emergency_alert_recipients
    ADD INDEX IF NOT EXISTS idx_ear_tenant (tenant_id);


-- =========================================================================
-- 5. Create vol_credentials table (Credential Verification — Feature V5)
-- =========================================================================
-- Tracks uploaded credentials (garda vetting, first aid, safeguarding, etc.)
-- with verification workflow and expiry tracking.

CREATE TABLE IF NOT EXISTS vol_credentials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    credential_type VARCHAR(100) NOT NULL COMMENT 'e.g. garda_vetting, first_aid, safeguarding',
    file_url VARCHAR(500) DEFAULT NULL,
    file_name VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'verified', 'rejected', 'expired') NOT NULL DEFAULT 'pending',
    verified_by INT UNSIGNED DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    expires_at DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vol_cred_tenant (tenant_id),
    INDEX idx_vol_cred_user (user_id, tenant_id),
    INDEX idx_vol_cred_status (status, tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Volunteer credential verification (V5): uploaded certs with admin review workflow';


-- =========================================================================
-- 6. Create recurring_shift_patterns table (Recurring Shifts — Feature V8)
-- =========================================================================
-- Defines recurring patterns for automatic shift generation.
-- A cron job reads active patterns and generates future shifts.

CREATE TABLE IF NOT EXISTS recurring_shift_patterns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    opportunity_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    frequency ENUM('daily', 'weekly', 'biweekly', 'monthly') NOT NULL DEFAULT 'weekly',
    days_of_week JSON DEFAULT NULL COMMENT 'Array of day numbers 0-6 (0=Sun, 6=Sat)',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    spots_per_shift INT UNSIGNED NOT NULL DEFAULT 1,
    location VARCHAR(500) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_generated_at DATETIME DEFAULT NULL,
    generate_until DATE DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rsp_tenant (tenant_id),
    INDEX idx_rsp_opportunity (opportunity_id, tenant_id),
    INDEX idx_rsp_active (is_active, tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Recurring shift patterns (V8): auto-generate shifts on a schedule';


-- =========================================================================
-- 7. Create staffing_predictions table (PredictiveStaffingService)
-- =========================================================================
-- Stores ML-based predictions for shift staffing shortages.
-- Used by PredictiveStaffingService to alert coordinators proactively.

CREATE TABLE IF NOT EXISTS staffing_predictions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    shift_id INT UNSIGNED NOT NULL,
    predicted_shortage INT NOT NULL DEFAULT 0,
    confidence_score DECIMAL(5,2) DEFAULT NULL,
    factors JSON DEFAULT NULL COMMENT 'JSON object of contributing factors',
    recommendations JSON DEFAULT NULL COMMENT 'JSON array of suggested actions',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sp_tenant (tenant_id),
    INDEX idx_sp_shift (shift_id, tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Predictive staffing shortage analysis for volunteer shifts';


-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- Summary:
--   1. vol_opportunities.tenant_id backfilled and set to NOT NULL
--   2. Added index idx_vol_opp_tenant on vol_opportunities(tenant_id)
--   3. Added tenant_id to vol_shift_group_members with backfill from parent
--   4. Added tenant_id to vol_emergency_alert_recipients with backfill from parent
--   5. Created vol_credentials table (Credential Verification V5)
--   6. Created recurring_shift_patterns table (Recurring Shifts V8)
--   7. Created staffing_predictions table (PredictiveStaffingService)
-- ============================================================================

SELECT 'VOLUNTEERING SCHEMA FIX: All 7 fixes applied successfully' AS result;
