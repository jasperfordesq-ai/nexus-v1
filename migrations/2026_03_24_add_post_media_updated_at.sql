-- Add updated_at column to post_media for audit trail on modifications
ALTER TABLE post_media
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    AFTER created_at;
