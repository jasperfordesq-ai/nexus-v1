<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplate;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SocialNotificationService — Native Laravel service for social interaction notifications.
 *
 * Handles platform and email notifications for social interactions
 * (likes, comments, shares) across all feed pages and profiles.
 *
 * DB facade / Eloquent instead of legacy Database class.
 */
class SocialNotificationService
{
    public function __construct()
    {
    }

    /**
     * Notify user when someone likes their content.
     */
    public static function notifyLike($contentOwnerId, $likerId, $contentType, $contentId, $contentPreview = null): void
    {
        if ($contentOwnerId == $likerId) {
            return;
        }

        try {
            $tenantId = TenantContext::getId();

            $liker = DB::table('users')
                ->where('id', $likerId)
                ->where('tenant_id', $tenantId)
                ->select(['name', 'avatar_url'])
                ->first();
            $likerName = $liker->name ?? 'Someone';

            $owner = DB::table('users')
                ->where('id', $contentOwnerId)
                ->where('tenant_id', $tenantId)
                ->select(['email', 'name', 'first_name'])
                ->first();
            if (!$owner) {
                return;
            }

            $ownerEmail = $owner->email ?? null;

            $contentLabel = self::getContentLabel($contentType);
            $message = "$likerName liked your $contentLabel";
            $link = self::getContentLink($contentType, $contentId);

            // 1. Create platform notification (bell)
            Notification::createNotification((int) $contentOwnerId, $message, $link, 'like');

            // 2. Send email notification (if user has email and hasn't opted out)
            if ($ownerEmail && self::shouldSendEmail($contentOwnerId, 'like')) {
                $emailLink = TenantContext::getSlugPrefix() . $link;
                self::sendLikeEmail($owner, $liker, $contentType, $contentId, $contentPreview, $emailLink);
            }
        } catch (\Throwable $e) {
            Log::warning("SocialNotificationService::notifyLike error: " . $e->getMessage());
        }
    }

    /**
     * Notify user when someone comments on their content.
     */
    public static function notifyComment($contentOwnerId, $commenterId, $contentType, $contentId, $commentText): void
    {
        if ($contentOwnerId == $commenterId) {
            return;
        }

        try {
            $tenantId = TenantContext::getId();

            $commenter = DB::table('users')
                ->where('id', $commenterId)
                ->where('tenant_id', $tenantId)
                ->select(['name', 'avatar_url'])
                ->first();
            $commenterName = $commenter->name ?? 'Someone';

            $owner = DB::table('users')
                ->where('id', $contentOwnerId)
                ->where('tenant_id', $tenantId)
                ->select(['email', 'name', 'first_name'])
                ->first();
            if (!$owner) {
                return;
            }

            $ownerEmail = $owner->email ?? null;

            $contentLabel = self::getContentLabel($contentType);
            $shortComment = strlen($commentText) > 50 ? substr($commentText, 0, 50) . '...' : $commentText;
            $message = "$commenterName commented on your $contentLabel: \"$shortComment\"";
            $link = self::getContentLink($contentType, $contentId);

            // 1. Create platform notification (bell)
            Notification::createNotification((int) $contentOwnerId, $message, $link, 'comment');

            // 2. Send email notification (if user has email and hasn't opted out)
            if ($ownerEmail && self::shouldSendEmail($contentOwnerId, 'comment')) {
                $emailLink = TenantContext::getSlugPrefix() . $link;
                self::sendCommentEmail($owner, $commenter, $contentType, $contentId, $commentText, $emailLink);
            }
        } catch (\Throwable $e) {
            Log::warning("SocialNotificationService::notifyComment error: " . $e->getMessage());
        }
    }

    /**
     * Notify user when someone shares their content.
     */
    public static function notifyShare($contentOwnerId, $sharerId, $contentType, $contentId): void
    {
        if ($contentOwnerId == $sharerId) {
            return;
        }

        try {
            $tenantId = TenantContext::getId();

            $sharer = DB::table('users')
                ->where('id', $sharerId)
                ->where('tenant_id', $tenantId)
                ->select(['name'])
                ->first();
            $sharerName = $sharer->name ?? 'Someone';

            $owner = DB::table('users')
                ->where('id', $contentOwnerId)
                ->where('tenant_id', $tenantId)
                ->select(['email', 'name', 'first_name'])
                ->first();
            if (!$owner) {
                return;
            }

            $ownerEmail = $owner->email ?? null;

            $contentLabel = self::getContentLabel($contentType);
            $message = "$sharerName shared your $contentLabel";
            $link = self::getContentLink($contentType, $contentId);

            // 1. Create platform notification (bell)
            Notification::createNotification((int) $contentOwnerId, $message, $link, 'share');

            // 2. Send email notification (if user has email and hasn't opted out)
            if ($ownerEmail && self::shouldSendEmail($contentOwnerId, 'share')) {
                $emailLink = TenantContext::getSlugPrefix() . $link;
                self::sendShareEmail($owner, $sharer, $contentType, $contentId, $emailLink);
            }
        } catch (\Throwable $e) {
            Log::warning("SocialNotificationService::notifyShare error: " . $e->getMessage());
        }
    }

    /**
     * Get human-readable content label.
     */
    private static function getContentLabel($contentType): string
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
     * Get link to content.
     */
    private static function getContentLink($contentType, $contentId): string
    {
        $routes = [
            'post' => '/feed',
            'listing' => '/listings/' . $contentId,
            'event' => '/events/' . $contentId,
            'goal' => '/goals',
            'poll' => '/polls',
            'resource' => '/resources',
            'volunteering' => '/volunteering/opportunities/' . $contentId,
            'review' => '/dashboard',
        ];
        return $routes[$contentType] ?? '/';
    }

    /**
     * Check if user should receive email notifications for this type.
     */
    private static function shouldSendEmail($userId, $notificationType): bool
    {
        try {
            $setting = DB::table('notification_settings')
                ->where('user_id', $userId)
                ->where('context_type', 'social')
                ->where('context_id', 0)
                ->value('frequency');

            if ($setting !== null) {
                if ($setting === 'off') {
                    return false;
                }
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
     * Send like notification email.
     */
    private static function sendLikeEmail($owner, $liker, $contentType, $contentId, $contentPreview, $link): void
    {
        try {
            $tenant = TenantContext::get();
            $tenantName = $tenant['name'] ?? 'Project NEXUS';
            $fullLink = TenantContext::getFrontendUrl() . $link;

            $likerName = $liker->name ?? 'Someone';
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

            $mailer = Mailer::forCurrentTenant();
            $mailer->send($owner->email, $title . " - $tenantName", $html);
        } catch (\Throwable $e) {
            Log::warning("sendLikeEmail error: " . $e->getMessage());
        }
    }

    /**
     * Send comment notification email.
     */
    private static function sendCommentEmail($owner, $commenter, $contentType, $contentId, $commentText, $link): void
    {
        try {
            $tenant = TenantContext::get();
            $tenantName = $tenant['name'] ?? 'Project NEXUS';
            $fullLink = TenantContext::getFrontendUrl() . $link;

            $commenterName = $commenter->name ?? 'Someone';
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

            $mailer = Mailer::forCurrentTenant();
            $mailer->send($owner->email, $title . " - $tenantName", $html);
        } catch (\Throwable $e) {
            Log::warning("sendCommentEmail error: " . $e->getMessage());
        }
    }

    /**
     * Send share notification email.
     */
    private static function sendShareEmail($owner, $sharer, $contentType, $contentId, $link): void
    {
        try {
            $tenant = TenantContext::get();
            $tenantName = $tenant['name'] ?? 'Project NEXUS';
            $fullLink = TenantContext::getFrontendUrl() . $link;

            $sharerName = $sharer->name ?? 'Someone';
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

            $mailer = Mailer::forCurrentTenant();
            $mailer->send($owner->email, $title . " - $tenantName", $html);
        } catch (\Throwable $e) {
            Log::warning("sendShareEmail error: " . $e->getMessage());
        }
    }

    /**
     * Get content owner ID from database.
     */
    public static function getContentOwnerId($contentType, $contentId): ?int
    {
        try {
            $tenantId = TenantContext::getId();

            switch ($contentType) {
                case 'post':
                    $userId = DB::table('feed_posts')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->value('user_id');
                    return $userId ? (int) $userId : null;

                case 'listing':
                    $userId = DB::table('listings')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->value('user_id');
                    return $userId ? (int) $userId : null;

                case 'event':
                    $userId = DB::table('events')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->value('user_id');
                    return $userId ? (int) $userId : null;

                case 'goal':
                    $userId = DB::table('goals')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->value('user_id');
                    return $userId ? (int) $userId : null;

                case 'poll':
                    $userId = DB::table('polls')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->value('user_id');
                    return $userId ? (int) $userId : null;

                case 'resource':
                    $userId = DB::table('resources')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->value('user_id');
                    return $userId ? (int) $userId : null;

                case 'volunteering':
                    $userId = DB::table('vol_opportunities')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->value('created_by');
                    return $userId ? (int) $userId : null;

                case 'review':
                    $userId = DB::table('reviews')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->value('reviewer_id');
                    return $userId ? (int) $userId : null;

                case 'comment':
                    $userId = DB::table('comments')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->value('user_id');
                    return $userId ? (int) $userId : null;

                default:
                    return null;
            }
        } catch (\Throwable $e) {
            Log::warning("getContentOwnerId error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get content preview text.
     */
    public static function getContentPreview($contentType, $contentId, $maxLength = 100): string
    {
        try {
            $tenantId = TenantContext::getId();
            $text = '';

            switch ($contentType) {
                case 'post':
                    $text = (string) DB::table('feed_posts')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->value('content');
                    break;

                case 'listing':
                    $row = DB::table('listings')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->select(['title', 'description'])
                        ->first();
                    $text = $row ? ($row->title . ': ' . ($row->description ?? '')) : '';
                    break;

                case 'event':
                    $row = DB::table('events')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->select(['title', 'description'])
                        ->first();
                    $text = $row ? ($row->title . ': ' . ($row->description ?? '')) : '';
                    break;

                case 'review':
                    $text = (string) DB::table('reviews')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->value('comment');
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
