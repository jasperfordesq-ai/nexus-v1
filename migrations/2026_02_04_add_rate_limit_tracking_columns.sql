-- Add rate limit tracking columns to federation_api_keys
-- Date: 2026-02-04
-- Purpose: Enable proper sliding window rate limiting with automatic hourly reset

-- Add column to track the current hour for rate limiting
ALTER TABLE federation_api_keys
ADD COLUMN IF NOT EXISTS rate_limit_hour DATETIME DEFAULT NULL
COMMENT 'The hour (truncated to start of hour) for current rate limit window';

-- Add column for hourly request count (separate from total request_count)
ALTER TABLE federation_api_keys
ADD COLUMN IF NOT EXISTS hourly_request_count INT UNSIGNED DEFAULT 0
COMMENT 'Request count for current hour window';

-- Add index for efficient rate limit queries
CREATE INDEX IF NOT EXISTS idx_rate_limit_hour ON federation_api_keys(rate_limit_hour);

-- Add columns to logs table for auth method tracking (if not exists)
ALTER TABLE federation_api_logs
ADD COLUMN IF NOT EXISTS auth_method VARCHAR(20) DEFAULT 'api_key'
COMMENT 'Authentication method used: api_key, hmac, jwt';

ALTER TABLE federation_api_logs
ADD COLUMN IF NOT EXISTS signature_valid TINYINT(1) DEFAULT NULL
COMMENT 'For HMAC auth: whether signature was valid';

-- Verification query
-- SELECT id, name, rate_limit, hourly_request_count, rate_limit_hour FROM federation_api_keys LIMIT 5;
