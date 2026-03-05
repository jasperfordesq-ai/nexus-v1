-- Migration: Add FULLTEXT indexes for improved search performance
-- Replaces LIKE '%term%' with MySQL native FULLTEXT search
-- MariaDB 10.11 InnoDB FULLTEXT is production-ready since 10.0+

-- Drop existing indexes if present (idempotent re-run safety)
DROP INDEX IF EXISTS ft_listings_search ON listings;
DROP INDEX IF EXISTS ft_users_search ON users;
DROP INDEX IF EXISTS ft_feed_search ON feed_activity;

-- Listings: index title + description for full-text search
ALTER TABLE listings
    ADD FULLTEXT INDEX ft_listings_search (title, description);

-- Users: index name fields + bio + skills for member search
ALTER TABLE users
    ADD FULLTEXT INDEX ft_users_search (first_name, last_name, bio, skills);

-- Feed activity: index title + content for feed search
ALTER TABLE feed_activity
    ADD FULLTEXT INDEX ft_feed_search (title, content);
