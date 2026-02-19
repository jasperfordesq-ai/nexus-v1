<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Models\ActivityLog;
use Nexus\Helpers\UrlHelper;

class FeedController
{
    public function index()
    {
        $userId = $_SESSION['user_id'] ?? 0;

        // Fetch feed posts with author details (tenant-isolated)
        $tenantId = TenantContext::getId();
        $posts = \Nexus\Core\Database::query("
            SELECT p.*, u.name as author_name, u.avatar_url as author_avatar,
            (SELECT COUNT(*) FROM likes WHERE user_id = ? AND target_type = 'post' AND target_id = p.id) as is_liked,
            (SELECT COUNT(*) FROM likes WHERE target_type = 'post' AND target_id = p.id) as likes_count,
            (SELECT COUNT(*) FROM comments WHERE target_type = 'post' AND target_id = p.id) as comments_count
            FROM feed_posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.tenant_id = ?
            ORDER BY p.created_at DESC
            LIMIT 50
        ", [$userId, $tenantId])->fetchAll(\PDO::FETCH_ASSOC);

        // Fetch recent activity for sidebar
        $recentActivity = ActivityLog::getRecent(10);

        View::render('feed/index', [
            'posts' => $posts,
            'recentActivity' => $recentActivity
        ]);
    }

    public function show($id)
    {
        $userId = $_SESSION['user_id'] ?? 0;

        // Fetch single post with author details (tenant-isolated)
        $tenantId = TenantContext::getId();
        $post = \Nexus\Core\Database::query("
            SELECT p.*, u.name as author_name, u.avatar_url as author_avatar,
            (SELECT COUNT(*) FROM likes WHERE user_id = ? AND target_type = 'post' AND target_id = p.id) as is_liked,
            (SELECT COUNT(*) FROM likes WHERE target_type = 'post' AND target_id = p.id) as likes_count
            FROM feed_posts p
            JOIN users u ON p.user_id = u.id
            WHERE p.id = ? AND p.tenant_id = ?
        ", [$userId, $id, $tenantId])->fetch(\PDO::FETCH_ASSOC);

        if (!$post) {
            http_response_code(404);
            View::render('error/404');
            return;
        }

        // Force modern layout for feed post view (Gold Standard)
        View::render('feed/show', ['post' => $post]);
    }

    public function store()
    {
        \Nexus\Core\Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $content = trim($_POST['content'] ?? '');
        $emoji = $_POST['emoji'] ?? null;

        if (!empty($content)) {
            \Nexus\Models\FeedPost::create($_SESSION['user_id'], $content, $emoji);

            // Gamification: Check post badges
            try {
                \Nexus\Services\GamificationService::checkPostBadges($_SESSION['user_id']);
            } catch (\Throwable $e) {
                error_log("Gamification post error: " . $e->getMessage());
            }
        }

        header('Location: ' . UrlHelper::safeReferer(TenantContext::getBasePath() . '/dashboard'));
    }

    /**
     * Hide a post for the current user
     * POST /api/feed/hide
     */
    public function hidePost()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $postId = (int)($input['post_id'] ?? 0);

        if ($postId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
            return;
        }

        try {
            // Insert into user_hidden_posts (ignore if already exists)
            \Nexus\Core\Database::query(
                "INSERT IGNORE INTO user_hidden_posts (user_id, post_id, created_at) VALUES (?, ?, NOW())",
                [$_SESSION['user_id'], $postId]
            );

            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    }

    /**
     * Mute a user for the current user
     * POST /api/feed/mute
     */
    public function muteUser()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $mutedUserId = (int)($input['user_id'] ?? 0);

        if ($mutedUserId <= 0 || $mutedUserId === $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'error' => 'Invalid user']);
            return;
        }

        try {
            // Insert into user_muted_users (ignore if already exists)
            \Nexus\Core\Database::query(
                "INSERT IGNORE INTO user_muted_users (user_id, muted_user_id, created_at) VALUES (?, ?, NOW())",
                [$_SESSION['user_id'], $mutedUserId]
            );

            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    }

    /**
     * Report a post
     * POST /api/feed/report
     */
    public function reportPost()
    {
        header('Content-Type: application/json');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not logged in']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $postId = (int)($input['post_id'] ?? 0);
        $targetType = $input['target_type'] ?? 'post';

        if ($postId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
            return;
        }

        try {
            // Insert into reports table
            \Nexus\Core\Database::query(
                "INSERT INTO reports (reporter_id, target_type, target_id, reason, created_at) VALUES (?, ?, ?, ?, NOW())",
                [$_SESSION['user_id'], $targetType, $postId, 'Reported via feed']
            );

            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    }
}
