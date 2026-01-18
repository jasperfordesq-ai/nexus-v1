<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\Csrf;

class GovernanceController
{
    /**
     * Show a single proposal and its voting status
     */
    public function show($id)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $db = Database::getConnection();

        // 1. Fetch Proposal
        $stmt = $db->prepare("
            SELECT p.*, g.name asgroup_name, u.name as author_name 
            FROM proposals p
            JOIN groups g ON p.group_id = g.id
            JOIN users u ON p.user_id = u.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $proposal = $stmt->fetch();

        if (!$proposal) {
            http_response_code(404);
            echo "Proposal not found";
            exit;
        }

        // 2. Fetch Votes Stats
        $statsStmt = $db->prepare("
            SELECT choice, COUNT(*) as count 
            FROM proposal_votes 
            WHERE proposal_id = ? 
            GROUP BY choice
        ");
        $statsStmt->execute([$id]);
        $rawStats = $statsStmt->fetchAll(\PDO::FETCH_KEY_PAIR); // ['yes' => 5, 'no' => 2]

        // Normalize Stats
        $stats = [
            'yes' => $rawStats['yes'] ?? 0,
            'no' => $rawStats['no'] ?? 0,
            'abstain' => $rawStats['abstain'] ?? 0,
        ];
        $totalVotes = array_sum($stats);

        // 3. Check if current user voted
        $userVoteStmt = $db->prepare("SELECT choice FROM proposal_votes WHERE proposal_id = ? AND user_id = ?");
        $userVoteStmt->execute([$id, $_SESSION['user_id']]);
        $userVote = $userVoteStmt->fetchColumn();

        View::render('governance/show', [
            'proposal' => $proposal,
            'stats' => $stats,
            'totalVotes' => $totalVotes,
            'userVote' => $userVote,
            'user_id' => $_SESSION['user_id']
        ]);
    }

    /**
     * Cast a vote
     */
    public function vote()
    {
        if (!isset($_SESSION['user_id'])) die("Login required");
        Csrf::verifyOrDie();

        $proposalId = $_POST['proposal_id'];
        $choice = $_POST['choice']; // yes, no, abstain

        if (!in_array($choice, ['yes', 'no', 'abstain'])) {
            die("Invalid choice");
        }

        $db = Database::getConnection();

        // Check if already voted
        $check = $db->prepare("SELECT id FROM proposal_votes WHERE proposal_id = ? AND user_id = ?");
        $check->execute([$proposalId, $_SESSION['user_id']]);
        if ($check->fetch()) {
            // Already voted
            header("Location: " . \Nexus\Core\TenantContext::getBasePath() . "/proposals/$proposalId?err=already_voted");
            exit;
        }

        // Cast Vote
        try {
            $stmt = $db->prepare("INSERT INTO proposal_votes (proposal_id, user_id, choice) VALUES (?, ?, ?)");
            $stmt->execute([$proposalId, $_SESSION['user_id'], $choice]);

            header("Location: " . \Nexus\Core\TenantContext::getBasePath() . "/proposals/$proposalId?msg=vote_cast");
        } catch (\Exception $e) {
            header("Location: " . \Nexus\Core\TenantContext::getBasePath() . "/proposals/$proposalId?err=failed");
        }
    }

    // Create method could be added here later
}
