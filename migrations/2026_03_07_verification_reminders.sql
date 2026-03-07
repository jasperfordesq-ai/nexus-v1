-- Add reminder_sent_at column for verification session reminders
-- Idempotent: safe to run multiple times

ALTER TABLE identity_verification_sessions
ADD COLUMN IF NOT EXISTS reminder_sent_at DATETIME DEFAULT NULL AFTER failure_reason;
