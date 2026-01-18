<?php

namespace Nexus\Controllers;

use Nexus\Models\Group;
use Nexus\Models\GroupDiscussion;
use Nexus\Models\GroupPost;
use Nexus\Core\View;
use Nexus\Core\Csrf;

class GroupDiscussionController
{
    public function create($groupId)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        $group = Group::findById($groupId);
        if (!$group) {
            echo "Group not found";
            return;
        }

        // Enforce Membership
        if (!Group::isMember($groupId, $_SESSION['user_id'])) {
            // Redirect or Error
            echo "You must be a member of this group to start a discussion.";
            return;
        }

        View::render('groups/discussions/create', [
            'group' => $group,
            'pageTitle' => 'Start Discussion'
        ]);
    }

    public function store($groupId)
    {
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        if (!Group::isMember($groupId, $_SESSION['user_id'])) {
            die("Unauthorized: You must join the group first.");
        }

        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';

        if ($title && $content) {
            $discussionId = GroupDiscussion::create($groupId, $_SESSION['user_id'], $title);
            GroupPost::create($discussionId, $_SESSION['user_id'], $content);

            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/groups/' . $groupId . '/discussions/' . $discussionId);
            exit;
        }

        // Fallback if error
        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/groups/' . $groupId);
    }

    public function show($groupId, $discussionId)
    {
        $group = Group::findById($groupId);
        $discussion = GroupDiscussion::findById($discussionId);

        if (!$group || !$discussion) {
            echo "Discussion not found";
            return;
        }

        // Privacy Check (Redundant if relying on GroupController gating, but safer)
        $userId = $_SESSION['user_id'] ?? 0;
        $isMember = false;
        if ($userId) {
            $isMember = Group::isMember($groupId, $userId);
        }

        if ($group['visibility'] === 'private' && !$isMember && $group['owner_id'] != $userId) {
            echo "This is a private group discussion.";
            return;
        }

        $posts = GroupPost::getForDiscussion($discussionId);

        View::render('groups/discussions/show', [
            'group' => $group,
            'discussion' => $discussion,
            'posts' => $posts,
            'isMember' => $isMember,
            'pageTitle' => $discussion['title']
        ]);
    }

    public function reply($groupId, $discussionId)
    {
        Csrf::verifyOrDie();
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/login');
            exit;
        }

        if (!Group::isMember($groupId, $_SESSION['user_id'])) {
            die("Unauthorized: You must join the group first.");
        }

        $content = $_POST['content'] ?? '';

        if ($content) {
            GroupPost::create($discussionId, $_SESSION['user_id'], $content);
        }

        header('Location: ' . \Nexus\Core\TenantContext::getBasePath() . '/groups/' . $groupId . '/discussions/' . $discussionId);
    }
}
