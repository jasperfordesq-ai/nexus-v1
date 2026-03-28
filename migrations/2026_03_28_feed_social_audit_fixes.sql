-- Feed & Social Audit Fixes (2026-03-28)
-- Adds missing unique constraint to prevent duplicate story reactions.

-- story_reactions: prevent duplicate reactions per user per story.
-- Application code now handles toggle logic, but the DB constraint
-- provides a safety net against race conditions.
ALTER TABLE story_reactions
ADD UNIQUE KEY `uk_story_user_reaction` (`story_id`, `user_id`);
