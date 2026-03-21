<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GroupPolicyRepository;

class GroupPolicyRepositoryTest extends TestCase
{
    private GroupPolicyRepository $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GroupPolicyRepository();
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

    public function test_setPolicy_requires_integration_test(): void
    {
        $this->markTestIncomplete('setPolicy uses private encodeValue and complex upsert — requires integration test');
    }
}
