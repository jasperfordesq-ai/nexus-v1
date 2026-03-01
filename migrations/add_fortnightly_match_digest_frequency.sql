-- Migration: Add 'fortnightly' and 'never' to match_preferences notification_frequency
-- Date: 2026-03-01
-- Purpose: Support fortnightly digest frequency; align ENUM values with application code

-- Step 1: Expand ENUM to include new values, change default to 'fortnightly'
ALTER TABLE match_preferences
    MODIFY COLUMN notification_frequency ENUM('instant','daily','weekly','fortnightly','off','never')
    DEFAULT 'fortnightly';

-- Step 2: Migrate legacy 'off' values to 'never'
UPDATE match_preferences SET notification_frequency = 'never' WHERE notification_frequency = 'off';

-- Step 3: Migrate legacy 'instant' values to 'daily' (instant was never properly supported)
UPDATE match_preferences SET notification_frequency = 'daily' WHERE notification_frequency = 'instant';

-- Step 4: Remove legacy values from ENUM now that data is migrated
ALTER TABLE match_preferences
    MODIFY COLUMN notification_frequency ENUM('daily','weekly','fortnightly','never')
    DEFAULT 'fortnightly';
