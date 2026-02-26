<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Models\Poll;
use Nexus\Models\ActivityLog;

class PollController
{
    private function checkFeature()
    {
        if (!TenantContext::hasFeature('polls')) {
            header("HTTP/1.0 404 Not Found");
            echo "Polls module is not enabled.";
            exit;
        }
    }

    public function index()
    {
        $this->checkFeature();
        $polls = Poll::all(TenantContext::getId());

        \Nexus\Core\SEO::setTitle('Community Polls');
        \Nexus\Core\SEO::setDescription('Voice your opinion on community matters.');

        View::render('polls/index', ['polls' => $polls]);
    }

    public function show($id)
    {
        $this->checkFeature();
        $poll = Poll::find($id);
        if (!$poll) die("Poll not found");

        // Handle AJAX actions for likes/comments
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $this->handlePollAjax($poll);
            exit;
        }

        $options = Poll::getOptions($id);
        $hasVoted = isset($_SESSION['user_id']) ? Poll::hasVoted($id, $_SESSION['user_id']) : false;

        // Calculate total for percentages
        $totalVotes = 0;
        foreach ($options as $opt) {
            $totalVotes += $opt['vote_count'];
        }

        \Nexus\Core\SEO::setTitle($poll['question']);

        View::render('polls/show', [
            'poll' => $poll,
            'options' => $options,
            'hasVoted' => $hasVoted,
            'totalVotes' => $totalVotes
        ]);
    }

    public function create()
    {
        $this->checkFeature();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        \Nexus\Core\SEO::setTitle('Create Poll');
        View::render('polls/create');
    }

    public function store()
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $question = $_POST['question'];
        $desc = $_POST['description'];
        $options = $_POST['options']; // Array
        $endDate = $_POST['end_date'] ? $_POST['end_date'] . ' 23:59:59' : null;

        // Validation
        if (empty($question) || count($options) < 2) {
            die("Question and at least 2 options required.");
        }

        $id = Poll::create(TenantContext::getId(), $_SESSION['user_id'], $question, $desc, $endDate);

        foreach ($options as $optLabel) {
            if (!empty(trim($optLabel))) {
                Poll::addOption($id, trim($optLabel));
            }
        }

        // Log to Feed
        ActivityLog::log($_SESSION['user_id'], 'created a Poll 🗳️', $question, true, '/polls/' . $id);

        header('Location: ' . TenantContext::getBasePath() . '/polls/' . $id);
    }

    public function vote()
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $pollId = $_POST['poll_id'];
        $optionId = $_POST['option_id'];

        if (Poll::castVote($pollId, $optionId, $_SESSION['user_id'])) {
            // Log vote? Maybe too noisy. Let's skip personal vote logging to feed for privacy, or make it generic.
            // ActivityLog::log($_SESSION['user_id'], 'voted in a Poll'); 
            header('Location: ' . TenantContext::getBasePath() . '/polls/' . $pollId . '?msg=voted');
        } else {
            die("You have already voted.");
        }
    }
    public function edit($id)
    {
        $this->checkFeature();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $poll = Poll::find($id);
        if (!$poll) die("Poll not found");

        if ($poll['user_id'] != $_SESSION['user_id'] && empty($_SESSION['is_super_admin'])) {
            die("Unauthorized");
        }

        \Nexus\Core\SEO::setTitle('Edit Poll');
        View::render('polls/edit', ['poll' => $poll]);
    }

    public function update($id)
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $poll = Poll::find($id);
        if (!$poll) die("Poll not found");
        if ($poll['user_id'] != $_SESSION['user_id'] && empty($_SESSION['is_super_admin'])) {
            die("Unauthorized");
        }

        $question = $_POST['question'];
        $desc = $_POST['description'];
        $endDate = $_POST['end_date'] ? $_POST['end_date'] . ' 23:59:59' : null;

        Poll::update($id, $question, $desc, $endDate);

        header('Location: ' . TenantContext::getBasePath() . '/polls/' . $id . '?msg=updated');
    }

    public function destroy($id)
    {
        $this->checkFeature();
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) die("Login required");

        $poll = Poll::find($id);
        if (!$poll) die("Poll not found");
        if ($poll['user_id'] != $_SESSION['user_id'] && empty($_SESSION['is_super_admin'])) {
            die("Unauthorized");
        }

        Poll::delete($id);
        header('Location: ' . TenantContext::getBasePath() . '/polls?msg=deleted');
    }

    /**
     * Handle AJAX actions for poll likes/comments
     */
    private function handlePollAjax($poll)
    {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Clear any buffered output before JSON response
        if (ob_get_level()) ob_clean();
        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? 0;
        $tenantId = TenantContext::getId();

        if (!$userId) {
            echo json_encode(['error' => 'Login required', 'redirect' => '/login']);
            return;
        }

        $action = $_POST['action'] ?? '';
        $targetType = 'poll';
        $targetId = (int)$poll['id'];

        try {
            // Get PDO instance directly - DatabaseWrapper adds tenant constraints that break JOINs
            $pdo = \Nexus\Core\Database::getInstance();

            // TOGGLE LIKE
            if ($action === 'toggle_like') {
                $stmt = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND target_type = ? AND target_id = ? AND tenant_id = ?");
                $stmt->execute([$userId, $targetType, $targetId, $tenantId]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $stmt = $pdo->prepare("DELETE FROM likes WHERE id = ? AND tenant_id = ?");
                    $stmt->execute([$existing['id'], $tenantId]);

                    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ? AND tenant_id = ?");
                    $stmt->execute([$targetType, $targetId, $tenantId]);
                    $countResult = $stmt->fetch();
                    echo json_encode(['status' => 'unliked', 'likes_count' => (int)($countResult['cnt'] ?? 0)]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO likes (user_id, target_type, target_id, tenant_id) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$userId, $targetType, $targetId, $tenantId]);

                    // Send notification to poll creator
                    if (class_exists('\Nexus\Services\SocialNotificationService')) {
                        $contentOwnerId = $poll['user_id'] ?? null;
                        if ($contentOwnerId && $contentOwnerId != $userId) {
                            $contentPreview = $poll['question'] ?? '';
                            \Nexus\Services\SocialNotificationService::notifyLike(
                                $contentOwnerId, $userId, $targetType, $targetId, $contentPreview
                            );
                        }
                    }

                    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM likes WHERE target_type = ? AND target_id = ?");
                    $stmt->execute([$targetType, $targetId]);
                    $countResult = $stmt->fetch();
                    echo json_encode(['status' => 'liked', 'likes_count' => (int)($countResult['cnt'] ?? 0)]);
                }
            }

            // SUBMIT COMMENT
            elseif ($action === 'submit_comment') {
                $content = trim($_POST['content'] ?? '');
                if (empty($content)) {
                    echo json_encode(['error' => 'Comment cannot be empty']);
                    return;
                }

                $stmt = $pdo->prepare("INSERT INTO comments (user_id, tenant_id, target_type, target_id, content, created_at) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $tenantId, $targetType, $targetId, $content, date('Y-m-d H:i:s')]);

                // Send notification to poll creator
                if (class_exists('\Nexus\Services\SocialNotificationService')) {
                    $contentOwnerId = $poll['user_id'] ?? null;
                    if ($contentOwnerId && $contentOwnerId != $userId) {
                        \Nexus\Services\SocialNotificationService::notifyComment(
                            $contentOwnerId, $userId, $targetType, $targetId, $content
                        );
                    }
                }

                echo json_encode(['status' => 'success', 'message' => 'Comment added']);
            }

            // FETCH COMMENTS (with nested replies and reactions)
            elseif ($action === 'fetch_comments') {
                $comments = \Nexus\Services\CommentService::fetchComments($targetType, $targetId, $userId);
                echo json_encode([
                    'status' => 'success',
                    'comments' => $comments,
                    'available_reactions' => \Nexus\Services\CommentService::getAvailableReactions()
                ]);
            }

            // DELETE COMMENT
            elseif ($action === 'delete_comment') {
                $commentId = (int)($_POST['comment_id'] ?? 0);
                $isSuperAdmin = !empty($_SESSION['is_super_admin']);
                $result = \Nexus\Services\CommentService::deleteComment($commentId, $userId, $isSuperAdmin);
                echo json_encode($result);
            }

            // EDIT COMMENT
            elseif ($action === 'edit_comment') {
                $commentId = (int)($_POST['comment_id'] ?? 0);
                $newContent = $_POST['content'] ?? '';
                $result = \Nexus\Services\CommentService::editComment($commentId, $userId, $newContent);
                echo json_encode($result);
            }

            // REPLY TO COMMENT
            elseif ($action === 'reply_comment') {
                $parentId = (int)($_POST['parent_id'] ?? 0);
                $content = trim($_POST['content'] ?? '');
                $result = \Nexus\Services\CommentService::addComment($userId, $tenantId, $targetType, $targetId, $content, $parentId);

                // Notify parent comment author
                if (isset($result['status']) && $result['status'] === 'success') {
                    $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
                    $stmt->execute([$parentId]);
                    $parent = $stmt->fetch();
                    if ($parent && $parent['user_id'] != $userId) {
                        if (class_exists('\Nexus\Services\SocialNotificationService')) {
                            \Nexus\Services\SocialNotificationService::notifyComment(
                                $parent['user_id'], $userId, 'reply', $parentId, $content
                            );
                        }
                    }
                }
                echo json_encode($result);
            }

            // TOGGLE REACTION ON COMMENT
            elseif ($action === 'toggle_reaction') {
                $commentId = (int)($_POST['comment_id'] ?? 0);
                $emoji = $_POST['emoji'] ?? '';
                $result = \Nexus\Services\CommentService::toggleReaction($userId, $tenantId, $commentId, $emoji);
                echo json_encode($result);
            }

            // SEARCH USERS FOR @MENTION
            elseif ($action === 'search_users') {
                $query = $_POST['query'] ?? '';
                $users = \Nexus\Services\CommentService::searchUsersForMention($query, $tenantId);
                echo json_encode(['status' => 'success', 'users' => $users]);
            }

            // SHARE POLL TO FEED
            elseif ($action === 'share_poll') {
                // Create a new post in feed_posts that references this poll
                $stmt = $pdo->prepare("INSERT INTO feed_posts (user_id, tenant_id, content, parent_id, parent_type, visibility, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $shareContent = "Check out this poll: " . ($poll['question'] ?? 'Poll');
                $stmt->execute([
                    $userId,
                    $tenantId,
                    $shareContent,
                    $targetId,
                    'poll',
                    'public',
                    date('Y-m-d H:i:s')
                ]);

                // Notify poll owner
                if (class_exists('\Nexus\Services\SocialNotificationService')) {
                    $contentOwnerId = $poll['user_id'] ?? null;
                    if ($contentOwnerId && $contentOwnerId != $userId) {
                        \Nexus\Services\SocialNotificationService::notifyLike(
                            $contentOwnerId, $userId, 'poll', $targetId, 'shared your poll'
                        );
                    }
                }

                echo json_encode(['status' => 'success', 'message' => 'Poll shared to feed']);
            }

            else {
                echo json_encode(['error' => 'Unknown action']);
            }

        } catch (\Exception $e) {
            error_log("PollController AJAX error: " . $e->getMessage());
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
