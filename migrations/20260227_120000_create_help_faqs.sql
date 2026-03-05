-- Copyright © 2024–2026 Jasper Ford
-- SPDX-License-Identifier: AGPL-3.0-or-later
-- Migration: Create help_faqs table with system default FAQ data
-- Created: 2026-02-27

CREATE TABLE IF NOT EXISTS `help_faqs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED NOT NULL,
    `category` VARCHAR(100) NOT NULL DEFAULT 'General',
    `question` TEXT NOT NULL,
    `answer` TEXT NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_published` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tenant_published` (`tenant_id`, `is_published`),
    KEY `idx_tenant_category` (`tenant_id`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert system default FAQs (tenant_id = 0) extracted from the original HelpCenterPage.tsx hardcoded data.
-- These serve as fallback FAQs for all tenants that have not configured their own.

-- Getting Started
INSERT IGNORE INTO `help_faqs` (`tenant_id`, `category`, `question`, `answer`, `sort_order`, `is_published`) VALUES
(0, 'Getting Started', 'What is timebanking?', 'Timebanking is a community-based exchange system where everyone''s time is valued equally. You earn time credits by helping others and spend them to receive help. One hour of service equals one time credit, regardless of the type of service.', 1, 1),
(0, 'Getting Started', 'How do I create an account?', 'Click "Sign Up" on the home page, select your community, fill in your details, and verify your email. Your community coordinator may need to approve your account before you can start exchanging.', 2, 1),
(0, 'Getting Started', 'How do I get started after signing up?', 'Start by completing your profile with your skills and interests. Then browse listings to find services you need, or create your own listings to offer your skills. You can also join groups and events to meet other members.', 3, 1);

-- Listings & Exchanges
INSERT IGNORE INTO `help_faqs` (`tenant_id`, `category`, `question`, `answer`, `sort_order`, `is_published`) VALUES
(0, 'Listings & Exchanges', 'How do I create a listing?', 'Go to the Listings page and click "Create Listing". Choose whether you''re offering a service or requesting one, add a title, description, category, and estimated time. Your listing will be visible to other community members.', 1, 1),
(0, 'Listings & Exchanges', 'How does an exchange work?', 'When you find a listing you''re interested in, you can request an exchange. The other member accepts or declines. Once accepted, you coordinate the service, and when complete, time credits are transferred automatically.', 2, 1),
(0, 'Listings & Exchanges', 'What happens if a service takes longer than estimated?', 'The actual time spent is what gets recorded. Before confirming the exchange, both parties agree on the actual hours. The estimate is just a guide to help members plan.', 3, 1);

-- Wallet & Credits
INSERT IGNORE INTO `help_faqs` (`tenant_id`, `category`, `question`, `answer`, `sort_order`, `is_published`) VALUES
(0, 'Wallet & Credits', 'How do I earn time credits?', 'You earn credits by providing services to other members. When an exchange is completed and confirmed, the agreed-upon hours are added to your wallet balance.', 1, 1),
(0, 'Wallet & Credits', 'Can I transfer credits directly?', 'Yes! Go to your Wallet page and use the "Transfer" option. You can send credits to any member in your community. This is useful for gifting or adjusting balances.', 2, 1),
(0, 'Wallet & Credits', 'What if my balance is zero?', 'You can still request services even with a zero balance. Timebanking is built on trust and reciprocity. Most communities allow negative balances to encourage participation.', 3, 1);

-- Community Features
INSERT IGNORE INTO `help_faqs` (`tenant_id`, `category`, `question`, `answer`, `sort_order`, `is_published`) VALUES
(0, 'Community Features', 'How do groups work?', 'Groups are community spaces around shared interests. You can join existing groups, participate in discussions, and share resources. Some groups are open for anyone, while others require approval.', 1, 1),
(0, 'Community Features', 'How do I RSVP to events?', 'Browse the Events page, find an event you''re interested in, and click "RSVP". You''ll receive reminders before the event. You can cancel your RSVP at any time.', 2, 1),
(0, 'Community Features', 'How do I connect with other members?', 'Visit a member''s profile and click "Connect". They''ll receive a notification and can accept or decline. Once connected, you can message each other directly.', 3, 1);

-- Account & Privacy
INSERT IGNORE INTO `help_faqs` (`tenant_id`, `category`, `question`, `answer`, `sort_order`, `is_published`) VALUES
(0, 'Account & Privacy', 'How do I update my profile?', 'Go to Settings from the menu. You can update your name, bio, skills, location, and avatar. Your profile helps other members find and connect with you.', 1, 1),
(0, 'Account & Privacy', 'Is my personal information safe?', 'We take privacy seriously. Your email and personal details are only visible to community members. We comply with GDPR and provide tools for you to manage your data, including account deletion.', 2, 1),
(0, 'Account & Privacy', 'How do I change my password?', 'Go to Settings and find the Password section. Enter your current password and your new password. For extra security, consider enabling two-factor authentication.', 3, 1);

-- Settings & Preferences
INSERT IGNORE INTO `help_faqs` (`tenant_id`, `category`, `question`, `answer`, `sort_order`, `is_published`) VALUES
(0, 'Settings & Preferences', 'How do I enable dark mode?', 'Click the sun/moon icon in the top navigation bar to toggle between light and dark themes. Your preference is saved automatically.', 1, 1),
(0, 'Settings & Preferences', 'How do I manage notifications?', 'Go to Settings and find the Notifications section. You can control email notifications, push notifications, and in-app alerts for different types of activity.', 2, 1),
(0, 'Settings & Preferences', 'Can I delete my account?', 'Yes, you can delete your account from Settings. This action is permanent and will remove your profile, listings, and transaction history. Contact your community coordinator if you need help.', 3, 1);
