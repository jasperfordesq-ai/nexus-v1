-- Migration: Add resume searchable columns to users table
-- For: Candidate Resume Database Search feature
-- Date: 2026-03-22

ALTER TABLE users ADD COLUMN IF NOT EXISTS resume_searchable TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS resume_headline VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS resume_summary TEXT DEFAULT NULL;
