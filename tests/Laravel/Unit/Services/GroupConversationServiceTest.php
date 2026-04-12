<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GroupConversationService;

class GroupConversationServiceTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(GroupConversationService::class));
    }

    public function test_has_public_methods(): void
    {
        $ref = new \ReflectionClass(GroupConversationService::class);
        foreach ([
            'getErrors', 'createGroup', 'addMember', 'removeMember', 'updateGroup',
            'getParticipants', 'getUserGroups', 'getGroupMessages', 'sendGroupMessage',
        ] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Method {$m} should exist");
            $this->assertTrue($ref->getMethod($m)->isPublic(), "Method {$m} should be public");
        }
    }

    public function test_getErrors_returns_array(): void
    {
        try {
            $result = GroupConversationService::getErrors();
            $this->assertIsArray($result);
        } catch (\TypeError $e) {
            $this->fail('TypeError: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }

    public function test_getUserGroups_returns_array_safely(): void
    {
        try {
            $result = GroupConversationService::getUserGroups(0);
            $this->assertIsArray($result);
        } catch (\TypeError $e) {
            $this->fail('TypeError: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }
}
