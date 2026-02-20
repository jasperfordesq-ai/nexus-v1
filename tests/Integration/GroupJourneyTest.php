<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Integration;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Group Journey Integration Test
 *
 * Tests complete group interaction workflows:
 * - Create group → invite members → accept invitations
 * - Post to group feed → comment on post → like post
 * - Create group event → RSVP to event
 */
class GroupJourneyTest extends DatabaseTestCase
{
    private static int $testTenantId = 2;
    private int $groupCreatorId;
    private int $memberA_Id;
    private int $memberB_Id;
    private array $createdGroupIds = [];
    private array $createdPostIds = [];
    private array $createdEventIds = [];
    private array $createdInvitationIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);

        $timestamp = time();

        // Create group creator
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, password_hash, is_approved, created_at, balance)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), 50)",
            [
                self::$testTenantId,
                "creator_{$timestamp}@example.com",
                "creator_{$timestamp}",
                'Group',
                'Creator',
                'Group Creator',
                password_hash('password', PASSWORD_DEFAULT)
            ]
        );
        $this->groupCreatorId = (int)Database::lastInsertId();

        // Create Member A
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, password_hash, is_approved, created_at, balance)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), 50)",
            [
                self::$testTenantId,
                "memberA_{$timestamp}@example.com",
                "memberA_{$timestamp}",
                'Member',
                'Alpha',
                'Member Alpha',
                password_hash('password', PASSWORD_DEFAULT)
            ]
        );
        $this->memberA_Id = (int)Database::lastInsertId();

        // Create Member B
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, password_hash, is_approved, created_at, balance)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), 50)",
            [
                self::$testTenantId,
                "memberB_{$timestamp}@example.com",
                "memberB_{$timestamp}",
                'Member',
                'Beta',
                'Member Beta',
                password_hash('password', PASSWORD_DEFAULT)
            ]
        );
        $this->memberB_Id = (int)Database::lastInsertId();
    }

    protected function tearDown(): void
    {
        // Clean up in reverse order
        foreach ($this->createdInvitationIds as $invitationId) {
            try {
                Database::query("DELETE FROM group_invitations WHERE id = ?", [$invitationId]);
            } catch (\Exception $e) {
                // Ignore
            }
        }

        foreach ($this->createdEventIds as $eventId) {
            try {
                Database::query("DELETE FROM event_rsvps WHERE event_id = ?", [$eventId]);
                Database::query("DELETE FROM events WHERE id = ?", [$eventId]);
            } catch (\Exception $e) {
                // Ignore
            }
        }

        foreach ($this->createdPostIds as $postId) {
            try {
                Database::query("DELETE FROM feed_post_likes WHERE post_id = ?", [$postId]);
                Database::query("DELETE FROM feed_comments WHERE post_id = ?", [$postId]);
                Database::query("DELETE FROM feed_posts WHERE id = ?", [$postId]);
            } catch (\Exception $e) {
                // Ignore
            }
        }

        foreach ($this->createdGroupIds as $groupId) {
            try {
                Database::query("DELETE FROM group_members WHERE group_id = ?", [$groupId]);
                Database::query("DELETE FROM groups WHERE id = ?", [$groupId]);
            } catch (\Exception $e) {
                // Ignore
            }
        }

        try {
            Database::query(
                "DELETE FROM users WHERE id IN (?, ?, ?)",
                [$this->groupCreatorId, $this->memberA_Id, $this->memberB_Id]
            );
        } catch (\Exception $e) {
            // Ignore
        }

        parent::tearDown();
    }

    /**
     * Test: Complete group creation and membership flow
     */
    public function testCreateGroupAndInviteMembersFlow(): void
    {
        // Step 1: Create a group
        Database::query(
            "INSERT INTO groups (tenant_id, name, description, created_by, visibility, created_at)
             VALUES (?, ?, ?, ?, 'public', NOW())",
            [
                self::$testTenantId,
                'Community Gardeners',
                'A group for people interested in community gardening',
                $this->groupCreatorId
            ]
        );
        $groupId = (int)Database::lastInsertId();
        $this->createdGroupIds[] = $groupId;

        $this->assertGreaterThan(0, $groupId, 'Group should be created');

        // Step 2: Add creator as group admin
        Database::query(
            "INSERT INTO group_members (group_id, user_id, tenant_id, role, status, joined_at, created_at)
             VALUES (?, ?, ?, 'admin', 'active', NOW(), NOW())",
            [$groupId, $this->groupCreatorId, self::$testTenantId]
        );

        // Verify creator membership
        $stmt = Database::query(
            "SELECT * FROM group_members WHERE group_id = ? AND user_id = ?",
            [$groupId, $this->groupCreatorId]
        );
        $membership = $stmt->fetch();

        $this->assertNotFalse($membership);
        $this->assertEquals('admin', $membership['role']);
        $this->assertEquals('active', $membership['status']);

        // Step 3: Invite Member A
        Database::query(
            "INSERT INTO group_invitations (group_id, user_id, invited_by, tenant_id, status, created_at)
             VALUES (?, ?, ?, ?, 'pending', NOW())",
            [$groupId, $this->memberA_Id, $this->groupCreatorId, self::$testTenantId]
        );
        $invitationA_Id = (int)Database::lastInsertId();
        $this->createdInvitationIds[] = $invitationA_Id;

        // Step 4: Member A accepts invitation
        Database::query(
            "UPDATE group_invitations SET status = 'accepted', responded_at = NOW()
             WHERE id = ? AND tenant_id = ?",
            [$invitationA_Id, self::$testTenantId]
        );

        // Add Member A to group
        Database::query(
            "INSERT INTO group_members (group_id, user_id, tenant_id, role, status, joined_at, created_at)
             VALUES (?, ?, ?, 'member', 'active', NOW(), NOW())",
            [$groupId, $this->memberA_Id, self::$testTenantId]
        );

        // Step 5: Verify Member A is in group
        $stmt = Database::query(
            "SELECT COUNT(*) as count FROM group_members WHERE group_id = ? AND status = 'active'",
            [$groupId]
        );
        $this->assertEquals(2, $stmt->fetch()['count'], 'Group should have 2 active members');
    }

    /**
     * Test: Post to group feed and interact
     */
    public function testGroupFeedInteractionFlow(): void
    {
        // Create group with members
        Database::query(
            "INSERT INTO groups (tenant_id, name, description, created_by, visibility, created_at)
             VALUES (?, 'Tech Discussion', 'Technology enthusiasts', ?, 'public', NOW())",
            [self::$testTenantId, $this->groupCreatorId]
        );
        $groupId = (int)Database::lastInsertId();
        $this->createdGroupIds[] = $groupId;

        // Add members
        Database::query(
            "INSERT INTO group_members (group_id, user_id, tenant_id, role, status, joined_at, created_at)
             VALUES (?, ?, ?, 'admin', 'active', NOW(), NOW()), (?, ?, ?, 'member', 'active', NOW(), NOW())",
            [$groupId, $this->groupCreatorId, self::$testTenantId, $groupId, $this->memberA_Id, self::$testTenantId]
        );

        // Step 1: Creator posts to group feed
        Database::query(
            "INSERT INTO feed_posts (tenant_id, user_id, group_id, content, created_at)
             VALUES (?, ?, ?, 'What are your thoughts on the latest tech trends?', NOW())",
            [self::$testTenantId, $this->groupCreatorId, $groupId]
        );
        $postId = (int)Database::lastInsertId();
        $this->createdPostIds[] = $postId;

        // Verify post exists
        $stmt = Database::query("SELECT * FROM feed_posts WHERE id = ?", [$postId]);
        $post = $stmt->fetch();

        $this->assertNotFalse($post);
        $this->assertEquals($groupId, $post['group_id']);
        $this->assertEquals($this->groupCreatorId, $post['user_id']);

        // Step 2: Member A comments on the post
        Database::query(
            "INSERT INTO feed_comments (tenant_id, post_id, user_id, comment, created_at)
             VALUES (?, ?, ?, 'AI and machine learning are definitely the future!', NOW())",
            [self::$testTenantId, $postId, $this->memberA_Id]
        );
        $commentId = (int)Database::lastInsertId();

        // Verify comment exists
        $stmt = Database::query("SELECT * FROM feed_comments WHERE id = ?", [$commentId]);
        $comment = $stmt->fetch();

        $this->assertNotFalse($comment);
        $this->assertEquals($postId, $comment['post_id']);
        $this->assertEquals($this->memberA_Id, $comment['user_id']);

        // Step 3: Member A likes the post
        Database::query(
            "INSERT INTO feed_post_likes (tenant_id, post_id, user_id, created_at)
             VALUES (?, ?, ?, NOW())",
            [self::$testTenantId, $postId, $this->memberA_Id]
        );

        // Step 4: Verify like count
        $stmt = Database::query(
            "SELECT COUNT(*) as count FROM feed_post_likes WHERE post_id = ? AND tenant_id = ?",
            [$postId, self::$testTenantId]
        );
        $this->assertEquals(1, $stmt->fetch()['count'], 'Post should have 1 like');

        // Step 5: Update post like count
        Database::query(
            "UPDATE feed_posts SET likes_count = (SELECT COUNT(*) FROM feed_post_likes WHERE post_id = ?)
             WHERE id = ?",
            [$postId, $postId]
        );

        $stmt = Database::query("SELECT likes_count FROM feed_posts WHERE id = ?", [$postId]);
        $updatedPost = $stmt->fetch();
        $this->assertEquals(1, $updatedPost['likes_count']);
    }

    /**
     * Test: Create group event and RSVP
     */
    public function testGroupEventCreationAndRsvpFlow(): void
    {
        // Create group with members
        Database::query(
            "INSERT INTO groups (tenant_id, name, description, created_by, visibility, created_at)
             VALUES (?, 'Hiking Club', 'Weekend hiking adventures', ?, 'public', NOW())",
            [self::$testTenantId, $this->groupCreatorId]
        );
        $groupId = (int)Database::lastInsertId();
        $this->createdGroupIds[] = $groupId;

        // Add members
        Database::query(
            "INSERT INTO group_members (group_id, user_id, tenant_id, role, status, joined_at, created_at)
             VALUES (?, ?, ?, 'admin', 'active', NOW(), NOW()),
                    (?, ?, ?, 'member', 'active', NOW(), NOW()),
                    (?, ?, ?, 'member', 'active', NOW(), NOW())",
            [
                $groupId,
                $this->groupCreatorId,
                self::$testTenantId,
                $groupId,
                $this->memberA_Id,
                self::$testTenantId,
                $groupId,
                $this->memberB_Id,
                self::$testTenantId
            ]
        );

        // Step 1: Create group event
        Database::query(
            "INSERT INTO events (tenant_id, group_id, user_id, title, description, start_time, end_time, location, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                self::$testTenantId,
                $groupId,
                $this->groupCreatorId,
                'Weekend Mountain Hike',
                'Join us for a scenic mountain hike this Saturday!',
                date('Y-m-d H:i:s', strtotime('+1 week Saturday 9:00')),
                date('Y-m-d H:i:s', strtotime('+1 week Saturday 17:00')),
                'Mountain Trail Head'
            ]
        );
        $eventId = (int)Database::lastInsertId();
        $this->createdEventIds[] = $eventId;

        $this->assertGreaterThan(0, $eventId, 'Event should be created');

        // Step 2: Member A RSVPs as "going"
        Database::query(
            "INSERT INTO event_rsvps (event_id, user_id, tenant_id, status, created_at)
             VALUES (?, ?, ?, 'going', NOW())",
            [$eventId, $this->memberA_Id, self::$testTenantId]
        );

        // Step 3: Member B RSVPs as "interested"
        Database::query(
            "INSERT INTO event_rsvps (event_id, user_id, tenant_id, status, created_at)
             VALUES (?, ?, ?, 'interested', NOW())",
            [$eventId, $this->memberB_Id, self::$testTenantId]
        );

        // Step 4: Verify RSVP counts
        $stmt = Database::query(
            "SELECT status, COUNT(*) as count FROM event_rsvps
             WHERE event_id = ? AND tenant_id = ?
             GROUP BY status",
            [$eventId, self::$testTenantId]
        );
        $rsvps = $stmt->fetchAll();

        $goingCount = 0;
        $interestedCount = 0;

        foreach ($rsvps as $rsvp) {
            if ($rsvp['status'] === 'going') {
                $goingCount = (int)$rsvp['count'];
            } elseif ($rsvp['status'] === 'interested') {
                $interestedCount = (int)$rsvp['count'];
            }
        }

        $this->assertEquals(1, $goingCount, 'Should have 1 "going" RSVP');
        $this->assertEquals(1, $interestedCount, 'Should have 1 "interested" RSVP');

        // Step 5: Member A changes RSVP to "not_going"
        Database::query(
            "UPDATE event_rsvps SET status = 'not_going', updated_at = NOW()
             WHERE event_id = ? AND user_id = ? AND tenant_id = ?",
            [$eventId, $this->memberA_Id, self::$testTenantId]
        );

        // Verify updated RSVP
        $stmt = Database::query(
            "SELECT status FROM event_rsvps WHERE event_id = ? AND user_id = ?",
            [$eventId, $this->memberA_Id]
        );
        $updatedRsvp = $stmt->fetch();

        $this->assertEquals('not_going', $updatedRsvp['status']);
    }

    /**
     * Test: Leave group
     */
    public function testLeaveGroupFlow(): void
    {
        // Create group with member
        Database::query(
            "INSERT INTO groups (tenant_id, name, description, created_by, visibility, created_at)
             VALUES (?, 'Book Club', 'Monthly book discussions', ?, 'public', NOW())",
            [self::$testTenantId, $this->groupCreatorId]
        );
        $groupId = (int)Database::lastInsertId();
        $this->createdGroupIds[] = $groupId;

        // Add creator and member
        Database::query(
            "INSERT INTO group_members (group_id, user_id, tenant_id, role, status, joined_at, created_at)
             VALUES (?, ?, ?, 'admin', 'active', NOW(), NOW()), (?, ?, ?, 'member', 'active', NOW(), NOW())",
            [$groupId, $this->groupCreatorId, self::$testTenantId, $groupId, $this->memberA_Id, self::$testTenantId]
        );

        // Verify initial member count
        $stmt = Database::query(
            "SELECT COUNT(*) as count FROM group_members WHERE group_id = ? AND status = 'active'",
            [$groupId]
        );
        $this->assertEquals(2, $stmt->fetch()['count']);

        // Step 1: Member A leaves the group
        Database::query(
            "UPDATE group_members SET status = 'left', left_at = NOW()
             WHERE group_id = ? AND user_id = ? AND tenant_id = ?",
            [$groupId, $this->memberA_Id, self::$testTenantId]
        );

        // Step 2: Verify member is marked as left
        $stmt = Database::query(
            "SELECT status, left_at FROM group_members WHERE group_id = ? AND user_id = ?",
            [$groupId, $this->memberA_Id]
        );
        $membership = $stmt->fetch();

        $this->assertEquals('left', $membership['status']);
        $this->assertNotNull($membership['left_at']);

        // Step 3: Verify active member count decreased
        $stmt = Database::query(
            "SELECT COUNT(*) as count FROM group_members WHERE group_id = ? AND status = 'active'",
            [$groupId]
        );
        $this->assertEquals(1, $stmt->fetch()['count'], 'Only admin should remain as active member');
    }
}
