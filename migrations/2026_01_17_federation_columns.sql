-- Federation Columns Migration
-- Date: 2026-01-17
-- Adds missing columns required for federation features

-- Add status column to reviews table
ALTER TABLE reviews
ADD COLUMN IF NOT EXISTS status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved' AFTER comment;

-- Add federation columns to transactions table
ALTER TABLE transactions
ADD COLUMN IF NOT EXISTS is_federated TINYINT(1) DEFAULT 0 AFTER status,
ADD COLUMN IF NOT EXISTS sender_tenant_id INT(11) DEFAULT NULL AFTER is_federated,
ADD COLUMN IF NOT EXISTS receiver_tenant_id INT(11) DEFAULT NULL AFTER sender_tenant_id;

-- Add is_federated column to messages table
ALTER TABLE messages
ADD COLUMN IF NOT EXISTS is_federated TINYINT(1) DEFAULT 0 AFTER is_read;

-- Add indexes for better query performance on federation lookups
ALTER TABLE transactions ADD INDEX IF NOT EXISTS idx_is_federated (is_federated);
ALTER TABLE transactions ADD INDEX IF NOT EXISTS idx_sender_tenant (sender_tenant_id);
ALTER TABLE transactions ADD INDEX IF NOT EXISTS idx_receiver_tenant (receiver_tenant_id);
ALTER TABLE messages ADD INDEX IF NOT EXISTS idx_msg_is_federated (is_federated);
ALTER TABLE reviews ADD INDEX IF NOT EXISTS idx_review_status (status);
