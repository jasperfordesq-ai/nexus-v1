-- Add source_idea_id to groups table for Idea→Group conversion tracking
-- Date: 2026-03-01

ALTER TABLE `groups`
ADD COLUMN IF NOT EXISTS `source_idea_id` INT UNSIGNED DEFAULT NULL COMMENT 'Links to challenge_ideas.id if group was created from an ideation idea';

ALTER TABLE `groups`
ADD COLUMN IF NOT EXISTS `source_challenge_id` INT UNSIGNED DEFAULT NULL COMMENT 'Links to ideation_challenges.id for context';
