-- Add tagline column to users (used by admin user edit)
ALTER TABLE users ADD COLUMN IF NOT EXISTS tagline VARCHAR(255) DEFAULT NULL;

-- Add reset_token_expiry for timed password reset links
ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token_expiry DATETIME DEFAULT NULL;
