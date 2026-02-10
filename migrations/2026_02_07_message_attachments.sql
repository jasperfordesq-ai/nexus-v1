-- Migration: Add attachments support for messages
-- Date: 2026-02-07
-- Description: Adds a table for message attachments (images, documents)

-- Create message_attachments table
CREATE TABLE IF NOT EXISTS message_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    message_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL COMMENT 'Original file name',
    file_path VARCHAR(500) NOT NULL COMMENT 'Storage path',
    file_url VARCHAR(500) NOT NULL COMMENT 'Public URL',
    file_type VARCHAR(20) NOT NULL DEFAULT 'file' COMMENT 'image or file',
    mime_type VARCHAR(100) DEFAULT NULL COMMENT 'MIME type',
    file_size INT UNSIGNED DEFAULT 0 COMMENT 'Size in bytes',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_message_id (message_id),
    INDEX idx_tenant_id (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key if messages table uses InnoDB
-- ALTER TABLE message_attachments ADD CONSTRAINT fk_message_attachments_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE;
