-- Add org_note column to vol_applications for org owner's response message
ALTER TABLE vol_applications
  ADD COLUMN IF NOT EXISTS org_note VARCHAR(1000) NULL COMMENT 'Note from org owner when approving/declining' AFTER message;
