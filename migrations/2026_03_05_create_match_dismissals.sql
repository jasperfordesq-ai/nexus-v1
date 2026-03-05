-- Match dismissal tracking
-- Stores negative signals from users who explicitly dismiss a match.
-- Used by MatchLearningService to reduce that listing's future score for this user.
CREATE TABLE IF NOT EXISTS match_dismissals (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    listing_id  INT UNSIGNED NOT NULL,
    reason      VARCHAR(50)  NULL,        -- optional: 'not_relevant', 'too_far', 'already_done', etc.
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY  uk_user_listing (tenant_id, user_id, listing_id),
    INDEX       idx_user        (tenant_id, user_id),
    INDEX       idx_listing     (tenant_id, listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
