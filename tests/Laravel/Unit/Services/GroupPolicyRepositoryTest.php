<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\GroupConfigurationService;
use App\Services\GroupPolicyRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Laravel\TestCase;

class GroupPolicyRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private GroupPolicyRepository $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GroupPolicyRepository();
    }

    protected function tearDown(): void
    {
        Cache::forget("group_policies:{$this->testTenantId}");
        Cache::forget("group_config:{$this->testTenantId}");

        parent::tearDown();
    }

    public function test_category_constants_are_defined(): void
    {
        $this->assertEquals('creation', GroupPolicyRepository::CATEGORY_CREATION);
        $this->assertEquals('membership', GroupPolicyRepository::CATEGORY_MEMBERSHIP);
        $this->assertEquals('content', GroupPolicyRepository::CATEGORY_CONTENT);
        $this->assertEquals('moderation', GroupPolicyRepository::CATEGORY_MODERATION);
        $this->assertEquals('notifications', GroupPolicyRepository::CATEGORY_NOTIFICATIONS);
        $this->assertEquals('features', GroupPolicyRepository::CATEGORY_FEATURES);
    }

    public function test_type_constants_are_defined(): void
    {
        $this->assertEquals('boolean', GroupPolicyRepository::TYPE_BOOLEAN);
        $this->assertEquals('number', GroupPolicyRepository::TYPE_NUMBER);
        $this->assertEquals('string', GroupPolicyRepository::TYPE_STRING);
        $this->assertEquals('json', GroupPolicyRepository::TYPE_JSON);
        $this->assertEquals('list', GroupPolicyRepository::TYPE_LIST);
    }

    public function test_set_policy_invalidates_runtime_configuration_cache(): void
    {
        $key = GroupConfigurationService::CONFIG_ALLOW_PRIVATE_GROUPS;
        GroupConfigurationService::set($key, false);

        self::assertFalse(GroupConfigurationService::get($key));
        self::assertTrue(Cache::has("group_config:{$this->testTenantId}"));

        $this->service->setPolicy(
            $key,
            true,
            GroupPolicyRepository::CATEGORY_MEMBERSHIP,
            GroupPolicyRepository::TYPE_BOOLEAN,
            null,
            $this->testTenantId,
        );

        self::assertFalse(Cache::has("group_config:{$this->testTenantId}"));
        self::assertTrue(GroupConfigurationService::get($key));
    }

    public function test_delete_policy_invalidates_runtime_configuration_cache(): void
    {
        $key = GroupConfigurationService::CONFIG_DEFAULT_VISIBILITY;
        GroupConfigurationService::set($key, 'private');

        self::assertSame('private', GroupConfigurationService::get($key));
        self::assertTrue(Cache::has("group_config:{$this->testTenantId}"));

        self::assertTrue($this->service->deletePolicy($key, $this->testTenantId));

        self::assertFalse(Cache::has("group_config:{$this->testTenantId}"));
        self::assertSame('public', GroupConfigurationService::get($key));
    }
}
