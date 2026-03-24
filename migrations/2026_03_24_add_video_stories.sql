-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Author: Jasper Ford
-- See NOTICE file for attribution and acknowledgements.
--
-- Migration: 2026_03_24_add_video_stories.sql
-- Purpose:   Add video support to stories — extends media_type ENUM and adds
--            video_duration column for storing actual video length in seconds.

DELIMITER $$

DROP PROCEDURE IF EXISTS add_video_stories$$

CREATE PROCEDURE add_video_stories()
BEGIN
    -- 1. Extend media_type ENUM to include 'video'.
    --    MariaDB requires restating the full ENUM; this is safe to re-run since
    --    existing values ('image','text','poll') are preserved in the same ordinal positions.
    ALTER TABLE stories
        MODIFY COLUMN media_type ENUM('image','text','poll','video') NOT NULL DEFAULT 'image';

    -- 2. Add video_duration (FLOAT, nullable) only if it does not already exist.
    --    Stores the actual video duration in seconds (e.g. 14.7).
    --    This is separate from the existing `duration` column which controls
    --    auto-advance timing for the story viewer.
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'stories'
          AND COLUMN_NAME  = 'video_duration'
    ) THEN
        ALTER TABLE stories
            ADD COLUMN video_duration FLOAT DEFAULT NULL
            AFTER duration;
    END IF;
END$$

DELIMITER ;

CALL add_video_stories();
DROP PROCEDURE IF EXISTS add_video_stories;
