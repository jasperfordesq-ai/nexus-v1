<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\GroupConfigurationService;

class GroupConfigurationServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(GroupConfigurationService::class));
    }

    public function testConfigurationKeyConstants(): void
    {
        $this->assertEquals('allow_user_group_creation', GroupConfigurationService::CONFIG_ALLOW_USER_GROUP_CREATION);
        $this->assertEquals('require_group_approval', GroupConfigurationService::CONFIG_REQUIRE_GROUP_APPROVAL);
        $this->assertEquals('max_groups_per_user', GroupConfigurationService::CONFIG_MAX_GROUPS_PER_USER);
        $this->assertEquals('max_members_per_group', GroupConfigurationService::CONFIG_MAX_MEMBERS_PER_GROUP);
        $this->assertEquals('allow_private_groups', GroupConfigurationService::CONFIG_ALLOW_PRIVATE_GROUPS);
        $this->assertEquals('enable_discussions', GroupConfigurationService::CONFIG_ENABLE_DISCUSSIONS);
        $this->assertEquals('enable_feedback', GroupConfigurationService::CONFIG_ENABLE_FEEDBACK);
        $this->assertEquals('enable_achievements', GroupConfigurationService::CONFIG_ENABLE_ACHIEVEMENTS);
        $this->assertEquals('default_visibility', GroupConfigurationService::CONFIG_DEFAULT_VISIBILITY);
        $this->assertEquals('moderation_enabled', GroupConfigurationService::CONFIG_MODERATION_ENABLED);
    }

    public function testContentFilterConstants(): void
    {
        $this->assertEquals('content_filter_enabled', GroupConfigurationService::CONFIG_CONTENT_FILTER_ENABLED);
        $this->assertEquals('profanity_filter_enabled', GroupConfigurationService::CONFIG_PROFANITY_FILTER_ENABLED);
        $this->assertEquals('min_description_length', GroupConfigurationService::CONFIG_MIN_DESCRIPTION_LENGTH);
        $this->assertEquals('max_description_length', GroupConfigurationService::CONFIG_MAX_DESCRIPTION_LENGTH);
    }
}
