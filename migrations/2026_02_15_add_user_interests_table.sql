-- User interests/categories for onboarding
-- Stores user interests, skill offers, and skill needs selected during onboarding wizard
CREATE TABLE IF NOT EXISTS user_interests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    interest_type ENUM('interest', 'skill_offer', 'skill_need') NOT NULL DEFAULT 'interest',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_category_type (user_id, category_id, interest_type),
    INDEX idx_user_id (user_id),
    INDEX idx_category_id (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure onboarding_completed column exists (it already does in production but be safe)
ALTER TABLE users ADD COLUMN IF NOT EXISTS onboarding_completed TINYINT(1) NOT NULL DEFAULT 0;
