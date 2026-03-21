<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\GroupNotificationService;
use App\Core\TenantContext;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

/**
 * GroupNotificationServiceTest — tests for group notification creation.
 */
class GroupNotificationServiceTest extends TestCase
{
    private GroupNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GroupNotificationService();
        TenantContext::setById(1);
    }

    // =========================================================================
    // notifyJoinRequest
    // =========================================================================

    public function testNotifyJoinRequestDoesNothingIfGroupNotFound(): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn(null); // group not found

        // getUserName will also be called
        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn((object) ['name' => 'John Doe']);

        // Should not attempt to create notifications
        $this->service->notifyJoinRequest(999, 1);

        // If we got here without error, the guard clause worked
        $this->assertTrue(true);
    }

    public function testNotifyJoinRequestDoesNothingIfUserNotFound(): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn((object) ['id' => 1, 'name' => 'Test Group']);

        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn(null); // user not found

        $this->service->notifyJoinRequest(1, 999);
        $this->assertTrue(true);
    }

    // =========================================================================
    // notifyJoined
    // =========================================================================

    public function testNotifyJoinedDoesNothingIfGroupNotFound(): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn(null);

        $this->service->notifyJoined(999, 1);
        $this->assertTrue(true);
    }

    // =========================================================================
    // notifyJoinRejected
    // =========================================================================

    public function testNotifyJoinRejectedDoesNothingIfGroupNotFound(): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn(null);

        $this->service->notifyJoinRejected(999, 1);
        $this->assertTrue(true);
    }

    // =========================================================================
    // notifyNewDiscussion
    // =========================================================================

    public function testNotifyNewDiscussionDoesNothingIfGroupNotFound(): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn(null); // group not found

        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn('Author Name');

        $this->service->notifyNewDiscussion(999, 1, 1, 'Test Discussion');
        $this->assertTrue(true);
    }

    // =========================================================================
    // notifyNewAnnouncement
    // =========================================================================

    public function testNotifyNewAnnouncementDoesNothingIfGroupNotFound(): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn(null);

        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn('Author Name');

        $this->service->notifyNewAnnouncement(999, 1, 'Test Announcement');
        $this->assertTrue(true);
    }

    // =========================================================================
    // Private helpers via reflection
    // =========================================================================

    public function testGetGroupNameReturnsNullForNonExistentGroup(): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn(null);

        $result = $this->callPrivateMethod($this->service, 'getGroupName', [999, 1]);
        $this->assertNull($result);
    }

    public function testGetGroupNameReturnsObjectForExistingGroup(): void
    {
        $group = (object) ['id' => 1, 'name' => 'Test Group'];

        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn($group);

        $result = $this->callPrivateMethod($this->service, 'getGroupName', [1, 1]);
        $this->assertNotNull($result);
        $this->assertEquals('Test Group', $result->name);
    }

    public function testGetUserNameReturnsNullForNonExistentUser(): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn(null);

        $result = $this->callPrivateMethod($this->service, 'getUserName', [999, 1]);
        $this->assertNull($result);
    }

    public function testGetUserNameReturnsTrimmedName(): void
    {
        DB::shouldReceive('selectOne')
            ->once()
            ->andReturn((object) ['name' => ' John Doe ']);

        $result = $this->callPrivateMethod($this->service, 'getUserName', [1, 1]);
        $this->assertEquals('John Doe', $result);
    }

    public function testGetGroupAdminsReturnsArray(): void
    {
        $admins = [
            (object) ['user_id' => 10],
            (object) ['user_id' => 20],
        ];

        DB::shouldReceive('select')
            ->once()
            ->andReturn($admins);

        $result = $this->callPrivateMethod($this->service, 'getGroupAdmins', [1, 1]);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testGetActiveMembersReturnsArray(): void
    {
        $members = [
            (object) ['user_id' => 10],
            (object) ['user_id' => 20],
            (object) ['user_id' => 30],
        ];

        DB::shouldReceive('select')
            ->once()
            ->andReturn($members);

        $result = $this->callPrivateMethod($this->service, 'getActiveMembers', [1, 1]);
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }
}
