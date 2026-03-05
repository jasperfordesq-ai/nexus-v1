-- Safeguarding categories for abuse_alerts table (TOL2 compliance)
ALTER TABLE abuse_alerts ADD COLUMN IF NOT EXISTS works_with_children TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE abuse_alerts ADD COLUMN IF NOT EXISTS works_with_vulnerable_adults TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE abuse_alerts ADD COLUMN IF NOT EXISTS home_visits TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE abuse_alerts ADD COLUMN IF NOT EXISTS requires_vetting TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE abuse_alerts ADD COLUMN IF NOT EXISTS safeguarding_category ENUM('general', 'children', 'vulnerable_adults', 'home_visit', 'financial_abuse', 'neglect', 'exploitation') DEFAULT 'general';
ALTER TABLE abuse_alerts ADD COLUMN IF NOT EXISTS risk_assessment_score INT DEFAULT NULL COMMENT 'Risk score 0-100';

-- Add safeguarding flags to users table for quick profile checks
ALTER TABLE users ADD COLUMN IF NOT EXISTS works_with_children TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS works_with_vulnerable_adults TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS requires_home_visits TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS safeguarding_notes TEXT DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS safeguarding_reviewed_by INT DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS safeguarding_reviewed_at DATETIME DEFAULT NULL;
