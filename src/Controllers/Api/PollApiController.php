<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Csrf;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class PollApiController extends BaseApiController
{

    public function index()
    {
        $userId = $this->getUserId();
        $db = Database::getConnection();

        // Fetch Active Polls
        $polls = $db->query("
            SELECT * FROM polls 
            WHERE tenant_id = " . TenantContext::getId() . " 
            AND expires_at > NOW() 
            ORDER BY created_at DESC
        ")->fetchAll();

        foreach ($polls as &$poll) {
            // Check if user has voted
            $hasVoted = $db->query("SELECT COUNT(*) FROM poll_votes WHERE poll_id = ? AND user_id = ?", [$poll['id'], $userId])->fetchColumn();
            $poll['has_voted'] = $hasVoted > 0;

            // Fetch Options
            $options = $db->query("SELECT * FROM poll_options WHERE poll_id = ?", [$poll['id']])->fetchAll();

            // Calc percentages
            $totalVotes = 0;
            foreach ($options as $opt) $totalVotes += $opt['votes'];

            foreach ($options as &$opt) {
                $opt['percent'] = $totalVotes > 0 ? round(($opt['votes'] / $totalVotes) * 100) : 0;
            }
            $poll['options'] = $options;
            $poll['total_votes'] = $totalVotes;
        }

        $this->jsonResponse(['status' => 'success', 'data' => $polls]);
    }

    public function vote()
    {
        // Security: Verify CSRF token for session-based requests
        Csrf::verifyOrDieJson();

        try {
            $userId = $this->getUserId();
            $input = json_decode(file_get_contents('php://input'), true);
            $pollId = $input['poll_id'] ?? null;
            $optionId = $input['option_id'] ?? null;

            if (!$pollId || !$optionId) {
                $this->jsonResponse(['error' => 'Missing fields'], 400);
            }

            // Check already voted (use static Database::query for prepared statements)
            $exists = Database::query("SELECT COUNT(*) FROM poll_votes WHERE poll_id = ? AND user_id = ?", [$pollId, $userId])->fetchColumn();
            if ($exists) {
                $this->jsonResponse(['error' => 'Already voted'], 400);
            }

            // Record Vote (matching Poll model schema: poll_id, option_id, user_id)
            Database::query("INSERT INTO poll_votes (poll_id, option_id, user_id) VALUES (?, ?, ?)", [$pollId, $optionId, $userId]);

            // Award Points (Gamification)
            try {
                \Nexus\Models\Gamification::awardPoints($userId, 2, 'Voted in Poll');
            } catch (\Exception $e) {
                // Gamification is optional, don't fail the vote
            }

            $this->jsonResponse(['success' => true]);
        } catch (\PDOException $e) {
            error_log("Poll vote error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            error_log("Poll vote error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Server error'], 500);
        }
    }
}
