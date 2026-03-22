-- Migration: Add blind hiring column to job_vacancies table
-- For: Anonymous / Blind Hiring Mode feature
-- Date: 2026-03-22

ALTER TABLE job_vacancies ADD COLUMN IF NOT EXISTS blind_hiring TINYINT(1) DEFAULT 0;
