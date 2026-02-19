<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\GroupPolicyRepository;

class GroupPolicyRepositoryTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(GroupPolicyRepository::class));
    }

    public function testCategoryConstants(): void
    {
        $this->assertEquals('creation', GroupPolicyRepository::CATEGORY_CREATION);
        $this->assertEquals('membership', GroupPolicyRepository::CATEGORY_MEMBERSHIP);
        $this->assertEquals('content', GroupPolicyRepository::CATEGORY_CONTENT);
        $this->assertEquals('moderation', GroupPolicyRepository::CATEGORY_MODERATION);
        $this->assertEquals('notifications', GroupPolicyRepository::CATEGORY_NOTIFICATIONS);
        $this->assertEquals('features', GroupPolicyRepository::CATEGORY_FEATURES);
    }

    public function testTypeConstants(): void
    {
        $this->assertEquals('boolean', GroupPolicyRepository::TYPE_BOOLEAN);
        $this->assertEquals('number', GroupPolicyRepository::TYPE_NUMBER);
        $this->assertEquals('string', GroupPolicyRepository::TYPE_STRING);
        $this->assertEquals('json', GroupPolicyRepository::TYPE_JSON);
        $this->assertEquals('list', GroupPolicyRepository::TYPE_LIST);
    }

    public function testSetPolicyMethodExists(): void
    {
        $this->assertTrue(method_exists(GroupPolicyRepository::class, 'setPolicy'));
        $ref = new \ReflectionMethod(GroupPolicyRepository::class, 'setPolicy');
        $this->assertTrue($ref->isStatic());
    }
}
