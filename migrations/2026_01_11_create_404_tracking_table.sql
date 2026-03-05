-- Migration: Create 404 Tracking Table
-- Date: 2026-01-11
-- Purpose: Track 404 errors for analysis and redirect creation

CREATE TABLE IF NOT EXISTS error_404_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(1000) NOT NULL,
    referer VARCHAR(1000) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_id INT DEFAULT NULL,
    hit_count INT DEFAULT 1,
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    resolved TINYINT(1) DEFAULT 0,
    redirect_id INT DEFAULT NULL,
    INDEX idx_url (url(255)),
    INDEX idx_resolved (resolved),
    INDEX idx_last_seen (last_seen_at),
    INDEX idx_hit_count (hit_count),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add notes field for admin comments (only if it doesn't exist)
ALTER TABLE error_404_log
ADD COLUMN IF NOT EXISTS notes TEXT DEFAULT NULL AFTER redirect_id;
