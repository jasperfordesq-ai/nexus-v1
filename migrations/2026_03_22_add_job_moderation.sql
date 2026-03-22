-- Migration: Add moderation columns to job_vacancies
-- Date: 2026-03-22
-- Feature: Job Moderation Queue & Spam Detection

ALTER TABLE job_vacancies ADD COLUMN IF NOT EXISTS moderation_status ENUM('pending_review','approved','rejected','flagged') DEFAULT NULL;
ALTER TABLE job_vacancies ADD COLUMN IF NOT EXISTS moderation_notes TEXT DEFAULT NULL;
ALTER TABLE job_vacancies ADD COLUMN IF NOT EXISTS moderated_by INT DEFAULT NULL;
ALTER TABLE job_vacancies ADD COLUMN IF NOT EXISTS moderated_at DATETIME DEFAULT NULL;
ALTER TABLE job_vacancies ADD COLUMN IF NOT EXISTS spam_score INT DEFAULT NULL;
ALTER TABLE job_vacancies ADD COLUMN IF NOT EXISTS spam_flags JSON DEFAULT NULL;

-- Index for efficient moderation queue queries
ALTER TABLE job_vacancies ADD INDEX IF NOT EXISTS idx_moderation_status (tenant_id, moderation_status);
ALTER TABLE job_vacancies ADD INDEX IF NOT EXISTS idx_moderated_at (tenant_id, moderated_at);
