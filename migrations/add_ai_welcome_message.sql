-- Add default AI welcome message setting
-- This migration adds a configurable welcome message for the AI chat interface

-- Insert default welcome message for each tenant
-- Note: This will only insert if the setting doesn't already exist (due to UNIQUE constraint)
INSERT IGNORE INTO ai_settings (tenant_id, setting_key, setting_value, is_encrypted)
SELECT
    t.id as tenant_id,
    'ai_welcome_message' as setting_key,
    'Hello! I am your new Platform Assistant. 🧠\n\nI am currently in Learning Mode and digesting the database of Members and Listings. Please bear with me while I learn the ropes—I will do my best to connect you with the right offers!' as setting_value,
    0 as is_encrypted
FROM tenants t
WHERE NOT EXISTS (
    SELECT 1 FROM ai_settings
    WHERE tenant_id = t.id
    AND setting_key = 'ai_welcome_message'
);
