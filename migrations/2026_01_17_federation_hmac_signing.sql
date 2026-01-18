-- Federation HMAC Signing Migration
-- Date: 2026-01-17
-- Adds signing secret column for HMAC-SHA256 request signing

-- Add signing_secret column to federation_api_keys
ALTER TABLE federation_api_keys
ADD COLUMN signing_secret VARCHAR(64) DEFAULT NULL COMMENT 'HMAC-SHA256 signing secret (hex encoded)' AFTER key_prefix,
ADD COLUMN signing_enabled TINYINT(1) DEFAULT 0 COMMENT 'Whether HMAC signing is required for this key' AFTER signing_secret,
ADD COLUMN platform_id VARCHAR(100) DEFAULT NULL COMMENT 'External platform identifier' AFTER signing_enabled;

-- Add index for platform lookups
ALTER TABLE federation_api_keys
ADD INDEX idx_platform (platform_id);

-- Add signature validation fields to logs
ALTER TABLE federation_api_logs
ADD COLUMN signature_valid TINYINT(1) DEFAULT NULL COMMENT 'NULL=not signed, 0=invalid, 1=valid' AFTER user_agent,
ADD COLUMN auth_method ENUM('api_key', 'hmac', 'jwt') DEFAULT 'api_key' AFTER signature_valid;

-- Example: Enable HMAC signing for a partner
-- UPDATE federation_api_keys
-- SET signing_secret = HEX(RANDOM_BYTES(32)), signing_enabled = 1, platform_id = 'hourworld'
-- WHERE id = 1;
