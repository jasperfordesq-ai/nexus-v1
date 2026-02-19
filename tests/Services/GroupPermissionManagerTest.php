<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\GroupPermissionManager;

class GroupPermissionManagerTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(GroupPermissionManager::class));
    }

    public function testCorePermissionConstants(): void
    {
        $this->assertEquals('create_group', GroupPermissionManager::PERM_CREATE_GROUP);
        $this->assertEquals('create_hub', GroupPermissionManager::PERM_CREATE_HUB);
        $this->assertEquals('edit_any_group', GroupPermissionManager::PERM_EDIT_ANY_GROUP);
        $this->assertEquals('delete_any_group', GroupPermissionManager::PERM_DELETE_ANY_GROUP);
        $this->assertEquals('moderate_content', GroupPermissionManager::PERM_MODERATE_CONTENT);
        $this->assertEquals('manage_members', GroupPermissionManager::PERM_MANAGE_MEMBERS);
        $this->assertEquals('manage_settings', GroupPermissionManager::PERM_MANAGE_SETTINGS);
        $this->assertEquals('view_analytics', GroupPermissionManager::PERM_VIEW_ANALYTICS);
        $this->assertEquals('approve_groups', GroupPermissionManager::PERM_APPROVE_GROUPS);
        $this->assertEquals('ban_members', GroupPermissionManager::PERM_BAN_MEMBERS);
    }

    public function testGroupLevelPermissionConstants(): void
    {
        $this->assertEquals('group_edit', GroupPermissionManager::PERM_GROUP_EDIT);
        $this->assertEquals('group_delete', GroupPermissionManager::PERM_GROUP_DELETE);
        $this->assertEquals('group_manage_members', GroupPermissionManager::PERM_GROUP_MANAGE_MEMBERS);
        $this->assertEquals('group_post_discussion', GroupPermissionManager::PERM_GROUP_POST_DISCUSSION);
        $this->assertEquals('group_invite_members', GroupPermissionManager::PERM_GROUP_INVITE_MEMBERS);
    }
}
