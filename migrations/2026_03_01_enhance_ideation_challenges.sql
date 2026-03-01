-- ============================================================================
-- IDEATION CHALLENGES ENHANCEMENT MIGRATION
-- ============================================================================
-- Adds: tags, favorites/bookmarks, idea images, engagement tracking
-- Date: 2026-03-01
-- ============================================================================

-- 1. Add tags column to challenges (JSON array of skill/interest tags)
ALTER TABLE ideation_challenges
ADD COLUMN IF NOT EXISTS tags JSON DEFAULT NULL COMMENT 'Array of tags/skills e.g. ["design","technology","sustainability"]';

-- 2. Add views tracking
ALTER TABLE ideation_challenges
ADD COLUMN IF NOT EXISTS views_count INT UNSIGNED NOT NULL DEFAULT 0;

-- 3. Add featured flag for admin curation
ALTER TABLE ideation_challenges
ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) NOT NULL DEFAULT 0;

-- 4. Add idea image support
ALTER TABLE challenge_ideas
ADD COLUMN IF NOT EXISTS image_url VARCHAR(500) DEFAULT NULL COMMENT 'Optional image attachment for the idea';

-- 5. Challenge favorites/bookmarks table
CREATE TABLE IF NOT EXISTS challenge_favorites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    challenge_id INT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_challenge_user (challenge_id, user_id),
    INDEX idx_user (user_id),

    CONSTRAINT fk_fav_challenge FOREIGN KEY (challenge_id) REFERENCES ideation_challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Add favorites_count to challenges for fast display
ALTER TABLE ideation_challenges
ADD COLUMN IF NOT EXISTS favorites_count INT UNSIGNED NOT NULL DEFAULT 0;

-- ============================================================================
-- VERIFICATION
-- ============================================================================
-- DESCRIBE ideation_challenges;
-- DESCRIBE challenge_ideas;
-- SHOW TABLES LIKE 'challenge_favorites';
