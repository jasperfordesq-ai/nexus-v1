<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\GroupConfigurationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Laravel\TestCase;

class GroupConfigurationServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Cache::forget('group_config:' . $this->testTenantId);
        parent::tearDown();
    }

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

    public function testAllKnownTabsAreMappedAndUnknownTabsFailClosed(): void
    {
        $tabs = [
            'feed' => GroupConfigurationService::CONFIG_TAB_FEED,
            'discussion' => GroupConfigurationService::CONFIG_TAB_DISCUSSION,
            'members' => GroupConfigurationService::CONFIG_TAB_MEMBERS,
            'events' => GroupConfigurationService::CONFIG_TAB_EVENTS,
            'files' => GroupConfigurationService::CONFIG_TAB_FILES,
            'announcements' => GroupConfigurationService::CONFIG_TAB_ANNOUNCEMENTS,
            'qa' => GroupConfigurationService::CONFIG_TAB_QA,
            'wiki' => GroupConfigurationService::CONFIG_TAB_WIKI,
            'media' => GroupConfigurationService::CONFIG_TAB_MEDIA,
            'chatrooms' => GroupConfigurationService::CONFIG_TAB_CHATROOMS,
            'tasks' => GroupConfigurationService::CONFIG_TAB_TASKS,
            'challenges' => GroupConfigurationService::CONFIG_TAB_CHALLENGES,
            'analytics' => GroupConfigurationService::CONFIG_TAB_ANALYTICS,
            'subgroups' => GroupConfigurationService::CONFIG_TAB_SUBGROUPS,
        ];

        foreach ($tabs as $tab => $configKey) {
            GroupConfigurationService::set($configKey, true);
            $this->assertTrue(GroupConfigurationService::isTabEnabled($tab), $tab . ' should be enabled.');

            GroupConfigurationService::set($configKey, false);
            $this->assertFalse(GroupConfigurationService::isTabEnabled($tab), $tab . ' should be disabled.');
        }

        $this->assertFalse(GroupConfigurationService::isTabEnabled('not-a-real-tab'));
    }

    public function testDiscussionTabAlsoHonoursTheMasterDiscussionPolicy(): void
    {
        GroupConfigurationService::set(GroupConfigurationService::CONFIG_TAB_DISCUSSION, true);
        GroupConfigurationService::set(GroupConfigurationService::CONFIG_ENABLE_DISCUSSIONS, false);

        $this->assertFalse(GroupConfigurationService::isTabEnabled('discussion'));
    }

    public function testNormalizeEnforcesTypesRangesAndChoices(): void
    {
        $this->assertSame(25, GroupConfigurationService::normalize('max_groups_per_user', '25'));
        $this->assertFalse(GroupConfigurationService::normalize('allow_private_groups', 'false'));
        $this->assertSame('private', GroupConfigurationService::normalize('default_visibility', 'private'));

        foreach ([
            ['max_groups_per_user', 0],
            ['max_members_per_group', 10001],
            ['min_description_length', -1],
            ['max_description_length', 50001],
            ['default_visibility', 'secret'],
            ['allow_private_groups', 'not-a-boolean'],
        ] as [$key, $value]) {
            try {
                GroupConfigurationService::normalize($key, $value);
                $this->fail("{$key} accepted an invalid value");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
