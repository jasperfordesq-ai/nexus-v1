-- Add meta_description column to pages table for SEO
-- This column was referenced by the PageBuilder UI but was missing from the schema

ALTER TABLE pages ADD COLUMN IF NOT EXISTS meta_description VARCHAR(500) DEFAULT NULL;
