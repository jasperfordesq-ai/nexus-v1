-- Expand mood ENUM to include all frontend mood options
ALTER TABLE goal_checkins
    MODIFY COLUMN mood ENUM('great','good','neutral','okay','struggling','stuck','motivated','grateful') DEFAULT NULL;
