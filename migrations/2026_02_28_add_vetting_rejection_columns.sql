-- Add dedicated rejection tracking columns to vetting_records
-- Fixes: verified_by was being reused for rejections (ambiguous semantics)
-- Fixes: rejection reason was appended to notes instead of stored separately
-- Created: 2026-02-28

ALTER TABLE vetting_records ADD COLUMN IF NOT EXISTS rejected_by INT DEFAULT NULL COMMENT 'Admin/broker who rejected';
ALTER TABLE vetting_records ADD COLUMN IF NOT EXISTS rejected_at DATETIME DEFAULT NULL;
ALTER TABLE vetting_records ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL COMMENT 'Reason for rejection';

-- Add document upload path column (if not already present from original migration)
-- document_url already exists in original schema, no action needed

-- Add index for rejection lookups
ALTER TABLE vetting_records ADD INDEX IF NOT EXISTS idx_vetting_rejected_by (rejected_by);
