-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Author: Jasper Ford
-- See NOTICE file for attribution and acknowledgements.
--
-- Migration: 2026_03_06_add_last_login_at
--
-- Adds last_login_at to users and backfills from existing activity data.
-- Used by the matching algorithm to score user recency / activity level.
-- Backfill order: feed_activity → transactions → created_at (fallback).

ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL DEFAULT NULL;

-- Backfill from feed_activity (most recent post = proxy for last login)
UPDATE users u
SET u.last_login_at = (
    SELECT MAX(fa.created_at)
    FROM feed_activity fa
    WHERE fa.user_id = u.id
)
WHERE u.last_login_at IS NULL;

-- Backfill from transactions where still null
UPDATE users u
SET u.last_login_at = (
    SELECT MAX(t.created_at)
    FROM transactions t
    WHERE t.sender_id = u.id OR t.receiver_id = u.id
)
WHERE u.last_login_at IS NULL;

-- Fallback: use account creation date so the column is never null for existing users
UPDATE users
SET last_login_at = created_at
WHERE last_login_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_users_last_login ON users(last_login_at);
