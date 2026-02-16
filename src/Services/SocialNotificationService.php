<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\Mailer;
use Nexus\Core\EmailTemplate;
use Nexus\Models\Notification;
use Nexus\Models\User;
use Nexus\Core\TenantContext;

/**
 * SocialNotificationService
 *
 * Handles platform and email notifications for social interactions
 * (likes, comments, shares) across all feed pages and profiles.
 */
class SocialNotificationService
{
    /**
     * Notify user when someone likes their content
     *
     * @param int $contentOwnerId The user who owns the content
     * @param int $likerId The user who liked
     * @param string $contentType 'post', 'listing', 'event', etc.
     * @param int $contentId The ID of the content
     * @param string|null $contentPreview Short preview of the content
     */
    public static function notifyLike($contentOwnerId, $likerId, $contentType, $contentId, $contentPreview = null)
    {
        // Don't notify if user likes their own content
        if ($contentOwnerId == $likerId) {
            return;
        }

        try {
            // Get liker info
            $liker = User::findById($likerId);
            $likerName = $liker['name'] ?? 'Someone';
            $likerAvatar = $liker['avatar_url'] ?? '/assets/img/defaults/default_avatar.png';

            // Get content owner info
            $owner = User::findById($contentOwnerId);
            if (!$owner) return;

            $ownerEmail = $owner['email'] ?? null;

            // Build notification message
            $contentLabel = self::getContentLabel($contentType);
            $message = "$likerName liked your $contentLabel";

            // Build link
            $basePath = class_exists('\Nexus\Core\TenantContext') ? TenantContext::getBasePath() : '';
            $link = self::getContentLink($basePath, $contentType, $contentId);

            // 1. Create platform notification (bell)
            if (class_exists('\Nexus\Models\Notification')) {
                Notification::create($contentOwnerId, $message, $link, 'like');
            }

            // 2. Send email notification (if user has email and hasn't opted out)
            if ($ownerEmail && self::shouldSendEmail($contentOwnerId, 'like')) {
                self::sendLikeEmail($owner, $liker, $contentType, $contentId, $contentPreview, $link);
            }

        } catch (\Throwable $e) {
            error_log("SocialNotificationService::notifyLike error: " . $e->getMessage());
        }
    }

    /**
     * Notify user when someone comments on their content
     *
     * @param int $contentOwnerId The user who owns the content
     * @param int $commenterId The user who commented
     * @param string $contentType 'post', 'listing', 'event', etc.
     * @param int $contentId The ID of the content
     * @param string $commentText The comment text
     */
    public static function notifyComment($contentOwnerId, $commenterId, $contentType, $contentId, $commentText)
    {
        // Don't notify if user comments on their own content
        if ($contentOwnerId == $commenterId) {
            return;
        }

        try {
            // Get commenter info
            $commenter = User::findById($commenterId);
            $commenterName = $commenter['name'] ?? 'Someone';
            $commenterAvatar = $commenter['avatar_url'] ?? '/assets/img/defaults/default_avatar.png';

            // Get content owner info
            $owner = User::findById($contentOwnerId);
            if (!$owner) return;

            $ownerEmail = $owner['email'] ?? null;

            // Build notification message
            $contentLabel = self::getContentLabel($contentType);
            $shortComment = strlen($commentText) > 50 ? substr($commentText, 0, 50) . '...' : $commentText;
            $message = "$commenterName commented on your $contentLabel: \"$shortComment\"";

            // Build link
            $basePath = class_exists('\Nexus\Core\TenantContext') ? TenantContext::getBasePath() : '';
            $link = self::getContentLink($basePath, $contentType, $contentId);

            // 1. Create platform notification (bell)
            if (class_exists('\Nexus\Models\Notification')) {
                Notification::create($contentOwnerId, $message, $link, 'comment');
            }

            // 2. Send email notification (if user has email and hasn't opted out)
            if ($ownerEmail && self::shouldSendEmail($contentOwnerId, 'comment')) {
                self::sendCommentEmail($owner, $commenter, $contentType, $contentId, $commentText, $link);
            }

        } catch (\Throwable $e) {
            error_log("SocialNotificationService::notifyComment error: " . $e->getMessage());
        }
    }

    /**
     * Notify user when someone shares their content
     *
     * @param int $contentOwnerId The user who owns the content
     * @param int $sharerId The user who shared
     * @param string $contentType 'post', 'listing', 'event', etc.
     * @param int $contentId The ID of the content
     */
    public static function notifyShare($contentOwnerId, $sharerId, $contentType, $contentId)
    {
        // Don't notify if user shares their own content
        if ($contentOwnerId == $sharerId) {
            return;
        }

        try {
            // Get sharer info
            $sharer = User::findById($sharerId);
            $sharerName = $sharer['name'] ?? 'Someone';

            // Get content owner info
            $owner = User::findById($contentOwnerId);
            if (!$owner) return;

            $ownerEmail = $owner['email'] ?? null;

            // Build notification message
            $contentLabel = self::getContentLabel($contentType);
            $message = "$sharerName shared your $contentLabel";

            // Build link
            $basePath = class_exists('\Nexus\Core\TenantContext') ? TenantContext::getBasePath() : '';
            $link = self::getContentLink($basePath, $contentType, $contentId);

            // 1. Create platform notification (bell)
            if (class_exists('\Nexus\Models\Notification')) {
                Notification::create($contentOwnerId, $message, $link, 'share');
            }

            // 2. Send email notification (if user has email and hasn't opted out)
            if ($ownerEmail && self::shouldSendEmail($contentOwnerId, 'share')) {
                self::sendShareEmail($owner, $sharer, $contentType, $contentId, $link);
            }

        } catch (\Throwable $e) {
            error_log("SocialNotificationService::notifyShare error: " . $e->getMessage());
        }
    }

    /**
     * Get human-readable content label
     */
    private static function getContentLabel($contentType)
    {
        $labels = [
            'post' => 'post',
            'listing' => 'listing',
            'event' => 'event',
            'goal' => 'goal',
            'poll' => 'poll',
            'resource' => 'resource',
            'volunteering' => 'volunteering opportunity',
            'review' => 'review',
        ];
        return $labels[$contentType] ?? 'content';
    }

    /**
     * Get link to content
     */
    private static function getContentLink($basePath, $contentType, $contentId)
    {
        $routes = [
            'post' => '/post/' . $contentId,
            'listing' => '/listings/' . $contentId,
            'event' => '/events/' . $contentId,
            'goal' => '/goals/' . $contentId,
            'poll' => '/polls/' . $contentId,
            'resource' => '/resources/' . $contentId,
            'volunteering' => '/volunteering/' . $contentId,
            'review' => '/home', // Reviews appear in the home feed
        ];
        return $basePath . ($routes[$contentType] ?? '/');
    }

    /**
     * Check if user should receive email notifications for this type
     */
    private static function shouldSendEmail($userId, $notificationType)
    {
        try {
            // Check notification_settings table
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT frequency FROM notification_settings WHERE user_id = ? AND context_type = 'social' AND context_id = 0");
            $stmt->execute([$userId]);
            $setting = $stmt->fetch();

            if ($setting) {
                // If explicitly turned off, don't send
                if ($setting['frequency'] === 'off') {
                    return false;
                }
                // For 'instant', send immediately
                if ($setting['frequency'] === 'instant') {
                    return true;
                }
                // For 'daily' or 'weekly', queue instead (handled by cron)
                // For now, we'll send instant for likes/comments as they're important
                return true;
            }

            // Default: send email notifications
            return true;

        } catch (\Throwable $e) {
            // If settings table doesn't exist, default to sending
            return true;
        }
    }

    /**
     * Send like notification email
     */
    private static function sendLikeEmail($owner, $liker, $contentType, $contentId, $contentPreview, $link)
    {
        try {
            $tenant = TenantContext::get();
            $tenantName = $tenant['name'] ?? 'Project NEXUS';
            $fullLink = \Nexus\Core\TenantContext::getFrontendUrl() . $link;

            $likerName = $liker['name'] ?? 'Someone';
            $contentLabel = self::getContentLabel($contentType);

            $title = "New Like on Your " . ucfirst($contentLabel);
            $subtitle = "$likerName liked your $contentLabel";
            $body = $contentPreview ? "\"" . htmlspecialchars($contentPreview) . "\"" : "Your $contentLabel is getting attention!";

            $html = EmailTemplate::render(
                $title,
                $subtitle,
                $body,
                "View " . ucfirst($contentLabel),
                $fullLink,
                $tenantName
            );

            $mailer = new Mailer();
            $mailer->send($owner['email'], $title . " - $tenantName", $html);

        } catch (\Throwable $e) {
            error_log("sendLikeEmail error: " . $e->getMessage());
        }
    }

    /**
     * Send comment notification email
     */
    private static function sendCommentEmail($owner, $commenter, $contentType, $contentId, $commentText, $link)
    {
        try {
            $tenant = TenantContext::get();
            $tenantName = $tenant['name'] ?? 'Project NEXUS';
            $fullLink = \Nexus\Core\TenantContext::getFrontendUrl() . $link;

            $commenterName = $commenter['name'] ?? 'Someone';
            $contentLabel = self::getContentLabel($contentType);

            $title = "New Comment on Your " . ucfirst($contentLabel);
            $subtitle = "$commenterName commented on your $contentLabel";
            $body = "\"" . htmlspecialchars($commentText) . "\"";

            $html = EmailTemplate::render(
                $title,
                $subtitle,
                $body,
                "View Comment",
                $fullLink,
                $tenantName
            );

            $mailer = new Mailer();
            $mailer->send($owner['email'], $title . " - $tenantName", $html);

        } catch (\Throwable $e) {
            error_log("sendCommentEmail error: " . $e->getMessage());
        }
    }

    /**
     * Send share notification email
     */
    private static function sendShareEmail($owner, $sharer, $contentType, $contentId, $link)
    {
        try {
            $tenant = TenantContext::get();
            $tenantName = $tenant['name'] ?? 'Project NEXUS';
            $fullLink = \Nexus\Core\TenantContext::getFrontendUrl() . $link;

            $sharerName = $sharer['name'] ?? 'Someone';
            $contentLabel = self::getContentLabel($contentType);

            $title = "Your " . ucfirst($contentLabel) . " Was Shared";
            $subtitle = "$sharerName shared your $contentLabel with their network";
            $body = "Your content is reaching more people!";

            $html = EmailTemplate::render(
                $title,
                $subtitle,
                $body,
                "View " . ucfirst($contentLabel),
                $fullLink,
                $tenantName
            );

            $mailer = new Mailer();
            $mailer->send($owner['email'], $title . " - $tenantName", $html);

        } catch (\Throwable $e) {
            error_log("sendShareEmail error: " . $e->getMessage());
        }
    }

    /**
     * Get content owner ID from database
     * Helper method to fetch owner from various content types
     */
    public static function getContentOwnerId($contentType, $contentId)
    {
        try {
            $dbClass = class_exists('\Nexus\Core\DatabaseWrapper') ? '\Nexus\Core\DatabaseWrapper' : '\Nexus\Core\Database';

            switch ($contentType) {
                case 'post':
                    $result = $dbClass::query("SELECT user_id FROM feed_posts WHERE id = ?", [$contentId])->fetch();
                    return $result ? $result['user_id'] : null;

                case 'listing':
                    $result = $dbClass::query("SELECT user_id FROM listings WHERE id = ?", [$contentId])->fetch();
                    return $result ? $result['user_id'] : null;

                case 'event':
                    $result = $dbClass::query("SELECT user_id FROM events WHERE id = ?", [$contentId])->fetch();
                    return $result ? $result['user_id'] : null;

                case 'goal':
                    $result = $dbClass::query("SELECT user_id FROM goals WHERE id = ?", [$contentId])->fetch();
                    return $result ? $result['user_id'] : null;

                case 'poll':
                    $result = $dbClass::query("SELECT user_id FROM polls WHERE id = ?", [$contentId])->fetch();
                    return $result ? $result['user_id'] : null;

                case 'resource':
                    $result = $dbClass::query("SELECT user_id FROM resources WHERE id = ?", [$contentId])->fetch();
                    return $result ? $result['user_id'] : null;

                case 'volunteering':
                    $result = $dbClass::query("SELECT created_by as user_id FROM vol_opportunities WHERE id = ?", [$contentId])->fetch();
                    return $result ? $result['user_id'] : null;

                case 'review':
                    // For reviews, the owner is the person who wrote the review (reviewer_id)
                    $result = $dbClass::query("SELECT reviewer_id as user_id FROM reviews WHERE id = ?", [$contentId])->fetch();
                    return $result ? $result['user_id'] : null;

                default:
                    return null;
            }
        } catch (\Throwable $e) {
            error_log("getContentOwnerId error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get content preview text
     */
    public static function getContentPreview($contentType, $contentId, $maxLength = 100)
    {
        try {
            $dbClass = class_exists('\Nexus\Core\DatabaseWrapper') ? '\Nexus\Core\DatabaseWrapper' : '\Nexus\Core\Database';

            switch ($contentType) {
                case 'post':
                    $result = $dbClass::query("SELECT content FROM feed_posts WHERE id = ?", [$contentId])->fetch();
                    $text = $result ? $result['content'] : '';
                    break;

                case 'listing':
                    $result = $dbClass::query("SELECT title, description FROM listings WHERE id = ?", [$contentId])->fetch();
                    $text = $result ? ($result['title'] . ': ' . $result['description']) : '';
                    break;

                case 'event':
                    $result = $dbClass::query("SELECT title, description FROM events WHERE id = ?", [$contentId])->fetch();
                    $text = $result ? ($result['title'] . ': ' . ($result['description'] ?? '')) : '';
                    break;

                case 'review':
                    $result = $dbClass::query("SELECT comment FROM reviews WHERE id = ?", [$contentId])->fetch();
                    $text = $result ? $result['comment'] : '';
                    break;

                default:
                    $text = '';
            }

            if (strlen($text) > $maxLength) {
                $text = substr($text, 0, $maxLength) . '...';
            }

            return $text;

        } catch (\Throwable $e) {
            return '';
        }
    }
}
