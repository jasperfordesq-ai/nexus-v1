-- ============================================================
-- FEDERATION PARTNERSHIPS - COUNTER-PROPOSAL SUPPORT
-- ============================================================
-- Adds columns to support counter-proposals in partnership requests
-- ============================================================

-- Add counter-proposal columns
ALTER TABLE federation_partnerships
    ADD COLUMN counter_proposed_at TIMESTAMP NULL DEFAULT NULL AFTER notes,
    ADD COLUMN counter_proposed_by INT UNSIGNED NULL DEFAULT NULL AFTER counter_proposed_at,
    ADD COLUMN counter_proposal_message TEXT NULL DEFAULT NULL AFTER counter_proposed_by,
    ADD COLUMN counter_proposed_level INT UNSIGNED NULL DEFAULT NULL AFTER counter_proposal_message,
    ADD COLUMN counter_proposed_permissions JSON NULL DEFAULT NULL AFTER counter_proposed_level;

-- Add index for efficient counter-proposal queries
ALTER TABLE federation_partnerships
    ADD INDEX idx_counter_proposed (counter_proposed_at);

-- ============================================================
-- DONE!
-- ============================================================
