<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Models;

use Nexus\Core\Database;
use PDO;

class GroupDiscussionSubscriber
{
    public static function subscribe($userId, $discussionId)
    {
        // Upsert: Set to 'instant'
        $sql = "INSERT INTO notification_settings (user_id, context_type, context_id, frequency) 
                VALUES (?, 'thread', ?, 'instant')
                ON DUPLICATE KEY UPDATE frequency = 'instant'";
        Database::query($sql, [$userId, $discussionId]);
    }

    public static function unsubscribe($userId, $discussionId)
    {
        // Explicit Mute
        $sql = "UPDATE notification_settings SET frequency = 'off' 
                WHERE user_id = ? AND context_type = 'thread' AND context_id = ?";
        Database::query($sql, [$userId, $discussionId]);
    }

    public static function isSubscribed($userId, $discussionId)
    {
        // Considered subscribed if there is a row AND it is not 'off'.
        // Note: This ignores Global defaults to represent 'Explicit Subscription'.
        $sql = "SELECT id FROM notification_settings 
                WHERE user_id = ? AND context_type = 'thread' AND context_id = ? AND frequency != 'off'";
        return (bool) Database::query($sql, [$userId, $discussionId])->fetch();
    }

    public static function getSubscribers($discussionId)
    {
        // Fetch users with explicit settings for this thread that are NOT 'off'
        // AND potentially we should include those with 'Global' settings who haven't muted this?
        // But getSubscribers is used for the LOOP in Controller.
        // The NotificationDispatcher handles the logic of "Dispatch even if Global Daily".
        // BUT, we need to pass a list of CANDIDATES to the Dispatcher.
        // If I only select 'notification_settings' rows, I MISS users who rely on GLOBAL defaults!

        // CRITICAL FIX: The Controller loop must be broader if we want "Global Default" users to get notified.
        // However, GroupController assumes "Subscribers" are people who explicitly joined.

        // Wait. Current System: "Subscribed" = Row in table.
        // New System: "Subscribers" + "Anyone with Global On"?
        // If Global = Daily, I expect to get a digest even if I never clicked "Subscribe".
        // So I should loop ALL Group Members?
        // GroupController::replyDiscussion loops `getSubscribers`.
        // If I rely on Global, I won't be in that loop!

        // Correct Logic for Smart Notifications:
        // We should notify ALL Group Members (filtered by their settings in Dispatcher).
        // BUT, `replyDiscussion` logic was "Email Subscribers".
        // In most forums, reply notifications only go to people watching the thread.
        // You don't want to spam the whole group for every reply in every thread.

        // So: "Subscribed" means "Watching this thread".
        // Global setting "Daily" typically means "Daily Digest of 'Activity relevant to me'".
        // If I am not watching the thread, is it relevant?
        // Maybe "Mentions" are relevat.
        // But general replies? Only if I subscribed.

        // So, relying on `notification_settings` (Explicit Subscription) is CORRECT for Repliy Notifications.
        // Unless I set "Group Level = Instant".
        // If I set Group Level = Instant, receiving replies to ALL threads is implied?
        // No, typically Group Level = Instant means "New Topics".
        // Thread subscription is usually explicit.

        // So I stick to: subscribers are those with an entry for this thread.

        $sql = "SELECT u.email, u.id, u.first_name 
                FROM notification_settings ns
                JOIN users u ON ns.user_id = u.id
                WHERE ns.context_type = 'thread' 
                AND ns.context_id = ? 
                AND ns.frequency != 'off'";
        return Database::query($sql, [$discussionId])->fetchAll();
    }


    public static function getSubscription($userId, $discussionId)
    {
        $sql = "SELECT frequency FROM notification_settings 
                WHERE user_id = ? AND context_type = 'thread' AND context_id = ?";
        return Database::query($sql, [$userId, $discussionId])->fetch();
    }
}
