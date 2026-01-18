-- Add deleted_at column to notifications table for soft delete support
-- This migration adds soft delete functionality to the notifications system
-- Date: 2026-01-11

ALTER TABLE notifications
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL
AFTER is_read;

-- Add index for better query performance on soft-deleted notifications
ALTER TABLE notifications
ADD INDEX idx_deleted_at (deleted_at);
