<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Models\User;
use Nexus\Models\Review;
use Nexus\Models\Transaction;

class ReviewController
{
    public function create($transactionId)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        // Logic to verify transaction ownership could go here
        // For now, we trust the ID or could add a check:
        // $txn = Transaction::findById($transactionId);
        // if ($txn['sender_id'] != $_SESSION['user_id']) die('Unauthorized');

        $receiverId = $_GET['receiver'] ?? 0;
        $receiver = User::findById($receiverId);

        if (!$receiver) {
            die("User not found");
        }

        View::render('reviews/create', [
            'transaction_id' => $transactionId,
            'receiver' => $receiver,
            'pageTitle' => 'Write a Review'
        ]);
    }

    public function store()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        // Verify CSRF token
        \Nexus\Core\Csrf::verifyOrDie();

        $transactionId = !empty($_POST['transaction_id']) ? $_POST['transaction_id'] : null;
        $receiverId = $_POST['receiver_id'];
        $rating = (int) $_POST['rating'];
        $comment = trim($_POST['comment'] ?? '');

        // Basic Validation
        if ($rating < 1 || $rating > 5) {
            die("Invalid rating");
        }

        // Save using Model
        Review::create($_SESSION['user_id'], $receiverId, $transactionId, $rating, $comment);

        // Gamification: Check review badges for reviewer and receiver
        try {
            \Nexus\Services\GamificationService::checkReviewBadges($_SESSION['user_id'], $receiverId, $rating);
        } catch (\Throwable $e) {
            error_log("Gamification review error: " . $e->getMessage());
        }

        // Notification
        $sender = User::findById($_SESSION['user_id']);
        $content = "You received a new {$rating}-star review from {$sender['first_name']}.";
        $html = "<h2>New Review</h2><p><strong>Rating: {$rating}/5</strong></p>" .
                ($comment ? "<p>\"{$comment}\"</p>" : "") .
                "<p>From: {$sender['first_name']} {$sender['last_name']}</p>";

        \Nexus\Services\NotificationDispatcher::dispatch(
            $receiverId,
            'global',
            0,
            'new_review',
            $content,
            '/profile?id=' . $receiverId,
            $html
        );

        // Redirect based on context
        $basePath = \Nexus\Core\TenantContext::getBasePath();
        if ($transactionId) {
            header("Location: {$basePath}/wallet?success=review_posted");
        } else {
            header("Location: {$basePath}/profile/{$receiverId}?success=review_posted");
        }
        exit;
    }
}
