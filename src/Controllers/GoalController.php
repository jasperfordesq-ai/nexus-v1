<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;
use Nexus\Models\Goal;
use Nexus\Models\ActivityLog;

class GoalController
{
    private function checkAccess()
    {
        if (!TenantContext::hasFeature('goals')) {
            header('HTTP/1.0 404 Not Found');
            echo "Goals module disabled.";
            exit;
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }
    }

    public function index()
    {
        $this->checkAccess();
        \Nexus\Core\SEO::setTitle('Goals & Buddies');

        $view = $_GET['view'] ?? 'my-goals';

        if ($view === 'finder') {
            $goals = Goal::allPublic(TenantContext::getId());
        } else {
            $goals = Goal::myGoals($_SESSION['user_id'], TenantContext::getId());
        }

        View::render('goals/index', [
            'goals' => $goals,
            'view' => $view
        ]);
    }

    public function create()
    {
        $this->checkAccess();
        \Nexus\Core\SEO::setTitle('Set a Goal');

        View::render('goals/create');
    }

    public function store()
    {
        $this->checkAccess();
        Csrf::verifyOrDie();

        $title = $_POST['title'];
        $description = $_POST['description'];
        $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
        $isPublic = isset($_POST['is_public']) ? 1 : 0;

        if (empty($title)) die("Title required");

        $id = Goal::create(TenantContext::getId(), $_SESSION['user_id'], $title, $description, $deadline, $isPublic);

        // Log Activity if Public
        if ($isPublic) {
            ActivityLog::log(
                TenantContext::getId(),
                $_SESSION['user_id'],
                'created_goal',
                $id,
                'listing', // abusing 'listing' type or should add 'goal' to enum? Using generic for now or we can update ENUM later.
                "set a new goal: $title"
            );
        }

        header('Location: ' . TenantContext::getBasePath() . '/goals');
    }

    public function show($id)
    {
        $this->checkAccess();
        $goal = Goal::find($id, TenantContext::getId());

        if (!$goal) die("Goal not found");

        // Ensure user belongs to this tenant... (implicit via TenantContext usually, but Model doesn't enforce it strictly in find())
        // For strictness, could check tenant_id matches.

        // Fetch social engagement data for Master Social Module
        $userId = $_SESSION['user_id'] ?? 0;
        $likesCount = 0;
        $commentsCount = 0;
        $isLiked = false;

        try {
            $likesResult = \Nexus\Core\Database::query(
                "SELECT COUNT(*) as cnt FROM likes WHERE target_type = 'goal' AND target_id = ?",
                [$id]
            )->fetch(\PDO::FETCH_ASSOC);
            $likesCount = (int)($likesResult['cnt'] ?? 0);

            $commentsResult = \Nexus\Core\Database::query(
                "SELECT COUNT(*) as cnt FROM comments WHERE target_type = 'goal' AND target_id = ?",
                [$id]
            )->fetch(\PDO::FETCH_ASSOC);
            $commentsCount = (int)($commentsResult['cnt'] ?? 0);

            if ($userId) {
                $likedResult = \Nexus\Core\Database::query(
                    "SELECT id FROM likes WHERE target_type = 'goal' AND target_id = ? AND user_id = ?",
                    [$id, $userId]
                )->fetch(\PDO::FETCH_ASSOC);
                $isLiked = !empty($likedResult);
            }
        } catch (\Exception $e) {
            // Silent fail - social features are optional
        }

        \Nexus\Core\SEO::setTitle($goal['title']);
        View::render('goals/show', [
            'goal' => $goal,
            'likesCount' => $likesCount,
            'commentsCount' => $commentsCount,
            'isLiked' => $isLiked,
            'isLoggedIn' => !empty($_SESSION['user_id'])
        ]);
    }

    public function becomeBuddy()
    {
        $this->checkAccess();
        Csrf::verifyOrDie();

        $goalId = $_POST['goal_id'];
        // Find goal without tenant filter - goal ID is unique and we verify ownership below
        $goal = Goal::find($goalId);

        if (!$goal) {
            die("Goal not found");
        }

        // Can't be a buddy for your own goal
        if ($goal['user_id'] == $_SESSION['user_id']) {
            die("You cannot be a buddy for your own goal");
        }

        // Only public goals can have buddies
        if (empty($goal['is_public'])) {
            die("This goal is private");
        }

        // Check if already has a buddy
        if (!empty($goal['mentor_id'])) {
            die("This goal already has a buddy");
        }

        Goal::setMentor($goalId, $_SESSION['user_id']);

        // Notify or Log could go here

        header('Location: ' . TenantContext::getBasePath() . '/goals/' . $goalId . '?msg=buddy_accepted');
    }

    public function edit($id)
    {
        $this->checkAccess();
        $goal = Goal::find($id, TenantContext::getId());

        if (!$goal) die("Goal not found");
        if ($goal['user_id'] != $_SESSION['user_id']) die("Unauthorized");

        \Nexus\Core\SEO::setTitle('Edit Goal');
        View::render('goals/edit', ['goal' => $goal]);
    }

    public function update($id)
    {
        $this->checkAccess();
        Csrf::verifyOrDie();

        $goal = Goal::find($id, TenantContext::getId());
        if (!$goal) die("Goal not found");
        if ($goal['user_id'] != $_SESSION['user_id']) die("Unauthorized");

        $title = $_POST['title'];
        $desc = $_POST['description'];
        $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
        $isPublic = isset($_POST['is_public']) ? 1 : 0;

        Goal::update($id, $title, $desc, $deadline, $isPublic);

        header('Location: ' . TenantContext::getBasePath() . '/goals/' . $id . '?msg=updated');
    }

    public function complete($id)
    {
        $this->checkAccess();
        Csrf::verifyOrDie();

        $goal = Goal::find($id, TenantContext::getId());
        if (!$goal) die("Goal not found");
        if ($goal['user_id'] != $_SESSION['user_id']) die("Unauthorized");

        Goal::setStatus($id, 'completed');

        // Log Achievement
        ActivityLog::log(TenantContext::getId(), $_SESSION['user_id'], 'completed_goal', $id, 'listing', "achieved: " . $goal['title']);

        header('Location: ' . TenantContext::getBasePath() . '/goals/' . $id . '?msg=completed');
    }

    public function destroy($id)
    {
        $this->checkAccess();
        Csrf::verifyOrDie();

        $goal = Goal::find($id, TenantContext::getId());
        if (!$goal) die("Goal not found");
        if ($goal['user_id'] != $_SESSION['user_id']) die("Unauthorized");

        Goal::delete($id);
        header('Location: ' . TenantContext::getBasePath() . '/goals?msg=deleted');
    }
    public function confirmDelete($id)
    {
        $this->checkAccess();
        $goal = Goal::find($id, TenantContext::getId());

        if (!$goal) die("Goal not found");
        if ($goal['user_id'] != $_SESSION['user_id']) die("Unauthorized");

        \Nexus\Core\SEO::setTitle('Delete Goal');
        View::render('goals/delete', ['goal' => $goal]);
    }
}
