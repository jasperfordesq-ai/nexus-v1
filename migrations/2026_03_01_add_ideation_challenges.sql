-- Migration: Ideation Challenges module
-- Date: 2026-03-01
-- Description: Creates tables for ideation challenges, ideas, votes, and comments

CREATE TABLE IF NOT EXISTS `ideation_challenges` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` int(11) UNSIGNED NOT NULL,
    `user_id` int(11) NOT NULL,
    `title` varchar(255) NOT NULL,
    `description` text NOT NULL,
    `category` varchar(100) DEFAULT NULL,
    `status` enum('draft','open','voting','closed') NOT NULL DEFAULT 'draft',
    `ideas_count` int(11) NOT NULL DEFAULT 0,
    `submission_deadline` datetime DEFAULT NULL,
    `voting_deadline` datetime DEFAULT NULL,
    `cover_image` varchar(500) DEFAULT NULL,
    `prize_description` text DEFAULT NULL,
    `max_ideas_per_user` int(11) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ideation_tenant_status` (`tenant_id`, `status`),
    INDEX `idx_ideation_tenant_user` (`tenant_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `challenge_ideas` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `challenge_id` int(11) UNSIGNED NOT NULL,
    `user_id` int(11) NOT NULL,
    `title` varchar(255) NOT NULL,
    `description` text NOT NULL,
    `votes_count` int(11) NOT NULL DEFAULT 0,
    `comments_count` int(11) NOT NULL DEFAULT 0,
    `status` enum('submitted','shortlisted','winner','withdrawn') NOT NULL DEFAULT 'submitted',
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ideas_challenge` (`challenge_id`),
    INDEX `idx_ideas_user` (`user_id`),
    INDEX `idx_ideas_votes` (`challenge_id`, `votes_count` DESC),
    CONSTRAINT `fk_idea_challenge` FOREIGN KEY (`challenge_id`) REFERENCES `ideation_challenges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `challenge_idea_votes` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `idea_id` int(11) UNSIGNED NOT NULL,
    `user_id` int(11) NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_idea_user_vote` (`idea_id`, `user_id`),
    CONSTRAINT `fk_vote_idea` FOREIGN KEY (`idea_id`) REFERENCES `challenge_ideas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `challenge_idea_comments` (
    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `idea_id` int(11) UNSIGNED NOT NULL,
    `user_id` int(11) NOT NULL,
    `body` text NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_comments_idea` (`idea_id`),
    CONSTRAINT `fk_comment_idea` FOREIGN KEY (`idea_id`) REFERENCES `challenge_ideas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
