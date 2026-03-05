-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Author: Jasper Ford
-- See NOTICE file for attribution and acknowledgements.
--
-- Migration: 2026_03_06_fix_match_history_columns
--
-- Adds missing columns to match_history that MatchingService writes to
-- but that were absent from the original 2026_03_06_create_match_history migration.
-- Also creates the match_notification_sent deduplication table.

-- Add missing columns to match_history (idempotent via IF NOT EXISTS guards)

ALTER TABLE match_history
    ADD COLUMN IF NOT EXISTS match_score      TINYINT UNSIGNED NULL
        COMMENT 'Match score 0-100 at time of action' AFTER action,
    ADD COLUMN IF NOT EXISTS distance_km      FLOAT NULL
        COMMENT 'Haversine distance between user and listing in km' AFTER match_score,
    ADD COLUMN IF NOT EXISTS resulted_in_transaction TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1 if this interaction led to a completed transaction' AFTER distance_km,
    ADD COLUMN IF NOT EXISTS transaction_id   INT UNSIGNED NULL
        COMMENT 'FK to transactions.id when resulted_in_transaction=1' AFTER resulted_in_transaction,
    ADD COLUMN IF NOT EXISTS conversion_time  TIMESTAMP NULL
        COMMENT 'Timestamp when transaction was completed' AFTER transaction_id,
    ADD COLUMN IF NOT EXISTS match_reasons    JSON NULL
        COMMENT 'Array of human-readable match reason strings' AFTER conversion_time;

-- Rename legacy score column to avoid collision (score → algorithm_score)
-- Only needed if the original migration was already applied; safe to skip if not.
-- We keep the original `score` column as-is since existing data may use it.

-- Fix the action ENUM to match what MatchingService actually writes.
-- Original had: 'impression','view','save','contact','dismiss','accept','decline'
-- Service writes: 'viewed','contacted','saved','dismissed','completed' (+ original values)
-- Expand ENUM to cover both sets for backwards compatibility.
ALTER TABLE match_history
    MODIFY COLUMN action ENUM(
        'impression','view','save','contact','dismiss','accept','decline',
        'viewed','contacted','saved','dismissed','completed'
    ) NOT NULL;

-- Index for conversion analytics queries
ALTER TABLE match_history
    ADD INDEX IF NOT EXISTS idx_mh_conversion (tenant_id, resulted_in_transaction),
    ADD INDEX IF NOT EXISTS idx_mh_user_listing (user_id, listing_id, tenant_id);

-- ---------------------------------------------------------------------------
-- match_notification_sent
-- Deduplication table: prevents re-notifying a user about the same listing.
-- TTL: records older than 30 days are pruned by MatchNotificationService::cleanupOldRecords()
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS match_notification_sent (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id        INT UNSIGNED NOT NULL,
    listing_id       INT UNSIGNED NOT NULL,
    matched_user_id  INT UNSIGNED NOT NULL,
    match_score      TINYINT UNSIGNED NULL,
    sent_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_mns_listing_user (tenant_id, listing_id, matched_user_id),
    INDEX idx_mns_sent_at (sent_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
