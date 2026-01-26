-- ============================================================================
-- ADD EXTERNAL PARTNER MESSAGE COLUMNS TO FEDERATION_MESSAGES
-- Enables storing messages sent to external federation partners
-- Date: January 2026
-- ============================================================================

-- Add columns for external partner messages
-- These allow storing messages sent to members on external partner systems

-- External partner ID (references federation_external_partners.id)
ALTER TABLE federation_messages
ADD COLUMN IF NOT EXISTS external_partner_id INT DEFAULT NULL
AFTER reference_message_id;

-- Store receiver name for external messages (since we can't join to their user table)
ALTER TABLE federation_messages
ADD COLUMN IF NOT EXISTS external_receiver_name VARCHAR(255) DEFAULT NULL
AFTER external_partner_id;

-- Store the message ID returned by the external partner's API
ALTER TABLE federation_messages
ADD COLUMN IF NOT EXISTS external_message_id VARCHAR(255) DEFAULT NULL
AFTER external_receiver_name;

-- Add index for external partner queries
ALTER TABLE federation_messages
ADD INDEX IF NOT EXISTS idx_external_partner (external_partner_id);

-- ============================================================================
-- VERIFICATION
-- ============================================================================
-- DESCRIBE federation_messages;
