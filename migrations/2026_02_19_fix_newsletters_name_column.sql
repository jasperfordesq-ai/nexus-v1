-- Fix newsletters table: add name column and update target_audience enum
-- Root cause: Controller tried to INSERT into non-existent `name` column → SQL error → "Failed to create newsletter"

-- Add name column (campaign name, separate from email subject line)
ALTER TABLE newsletters ADD COLUMN IF NOT EXISTS name VARCHAR(255) DEFAULT NULL AFTER tenant_id;

-- Update target_audience enum to include 'segment' (form sends 'segment' but enum only had 3 values)
ALTER TABLE newsletters MODIFY COLUMN target_audience ENUM('all_members','subscribers_only','both','segment') DEFAULT 'all_members';
