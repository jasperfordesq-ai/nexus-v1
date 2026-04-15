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
            $message = __('notifications.liked_your_content', ['name' => $likerName, 'content_type' => $contentLabel]);
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
            $message = __('notifications.commented_on_your_content', ['name' => $commenterName, 'content_type' => $contentLabel, 'comment' => $shortComment]);
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
            $message = __('notifications.shared_your_content', ['name' => $sharerName, 'content_type' => $contentLabel]);
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
        $key = 'notifications.content_type_' . $contentType;
        $translated = __($key);
        // If the translation key doesn't exist, fall back to the default
        return $translated !== $key ? $translated : __('notifications.content_type_default');
    }

    /**
     * Get link to content.
     */
    private static function getContentLink($contentType, $contentId): string
    {
        $routes = [
            'post' => '/feed',
            'feed_post' => '/feed',
            'blog_post' => '/blog/' . $contentId,
            'blog' => '/blog/' . $contentId,
            'listing' => '/listings/' . $contentId,
            'event' => '/events/' . $contentId,
            'goal' => '/goals',
            'poll' => '/polls',
            'resource' => '/resources',
            'volunteering' => '/volunteering/opportunities/' . $contentId,
            'ideation_challenge' => '/ideation/' . $contentId,
            'challenge_idea' => '/ideation/' . $contentId,
            'review' => '/dashboard',
            'comment' => '/feed',
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

            $title = __('notifications.email_new_like_title', ['content_type' => ucfirst($contentLabel)]);
            $subtitle = __('notifications.email_liked_subtitle', ['name' => $likerName, 'content_type' => $contentLabel]);
            $body = $contentPreview ? "\"" . htmlspecialchars($contentPreview) . "\"" : __('notifications.content_getting_attention', ['content_type' => $contentLabel]);

            $html = \App\Core\EmailTemplateBuilder::make()
                ->theme('brand')
                ->title($title)
                ->paragraph($subtitle)
                ->paragraph($body)
                ->button(__('notifications.email_view_content', ['content_type' => ucfirst($contentLabel)]), $fullLink)
                ->render();

            $mailer = Mailer::forCurrentTenant();
            $mailer->send($owner->email, $title . ' — ' . $tenantName, $html);
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

            $title = __('notifications.email_new_comment_title', ['content_type' => ucfirst($contentLabel)]);
            $subtitle = __('notifications.email_commented_subtitle', ['name' => $commenterName, 'content_type' => $contentLabel]);
            $body = "\"" . htmlspecialchars($commentText) . "\"";

            $html = \App\Core\EmailTemplateBuilder::make()
                ->theme('brand')
                ->title($title)
                ->paragraph($subtitle)
                ->paragraph($body)
                ->button(__('notifications.email_view_comment'), $fullLink)
                ->render();

            $mailer = Mailer::forCurrentTenant();
            $mailer->send($owner->email, $title . ' — ' . $tenantName, $html);
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

            $title = __('notifications.email_shared_title', ['content_type' => ucfirst($contentLabel)]);
            $subtitle = __('notifications.email_shared_subtitle', ['name' => $sharerName, 'content_type' => $contentLabel]);
            $body = __('notifications.content_reaching_more');

            $html = \App\Core\EmailTemplateBuilder::make()
                ->theme('brand')
                ->title($title)
                ->paragraph($subtitle)
                ->paragraph($body)
                ->button(__('notifications.email_view_content', ['content_type' => ucfirst($contentLabel)]), $fullLink)
                ->render();

            $mailer = Mailer::forCurrentTenant();
            $mailer->send($owner->email, $title . ' — ' . $tenantName, $html);
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
                case 'feed_post':
                    $userId = DB::table('feed_posts')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->value('user_id');
                    return $userId ? (int) $userId : null;

                case 'blog_post':
                case 'blog':
                    $userId = DB::table('blog_posts')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->value('author_id');
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
                case 'feed_post':
                    $text = (string) DB::table('feed_posts')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->value('content');
                    break;

                case 'blog_post':
                case 'blog':
                    $row = DB::table('blog_posts')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->select(['title', 'excerpt'])
                        ->first();
                    $text = $row ? ($row->title . ': ' . ($row->excerpt ?? '')) : '';
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

                case 'goal':
                    $row = DB::table('goals')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->select(['title', 'description'])
                        ->first();
                    $text = $row ? ($row->title . ': ' . ($row->description ?? '')) : '';
                    break;

                case 'poll':
                    $text = (string) DB::table('polls')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->value('question');
                    break;

                case 'resource':
                    $row = DB::table('resources')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->select(['title', 'description'])
                        ->first();
                    $text = $row ? ($row->title . ': ' . ($row->description ?? '')) : '';
                    break;

                case 'volunteering':
                    $row = DB::table('vol_opportunities')
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

                case 'comment':
                    $text = (string) DB::table('comments')
                        ->where('id', $contentId)
                        ->where('tenant_id', $tenantId)
                        ->value('content');
                    break;

                default:
                    $text = '';
            }

            if (strlen($text) > $maxLength) {
                $text = substr($text, 0, $maxLength) . '...';
            }

            return $text;
        } catch (\Throwable $e) {
            Log::debug('[SocialNotification] getPostSnippet failed: ' . $e->getMessage());
            return '';
        }
    }
}
