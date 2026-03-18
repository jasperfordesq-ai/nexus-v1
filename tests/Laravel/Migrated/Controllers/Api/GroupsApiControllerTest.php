<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Migrated\Controllers\Api;

use Tests\Laravel\LegacyBridgeTestCase;
use App\Http\Controllers\Api\GroupsApiController;

/**
 * Tests for GroupsApiController (Laravel migration)
 *
 * Migrated from: Nexus\Tests\Controllers\Api\GroupsApiControllerTest
 * Original base: PHPUnit\Framework\TestCase -> now LegacyBridgeTestCase
 */
class GroupsApiControllerTest extends LegacyBridgeTestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(GroupsApiController::class));
    }

    public function testHasRequiredCrudMethods(): void
    {
        $reflection = new \ReflectionClass(GroupsApiController::class);
        $methods = ['index', 'show', 'store', 'update', 'destroy'];

        foreach ($methods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Should have {$methodName}");
            $this->assertTrue($reflection->getMethod($methodName)->isPublic(), "{$methodName} should be public");
        }
    }

    public function testHasMembershipMethods(): void
    {
        $reflection = new \ReflectionClass(GroupsApiController::class);
        $methods = ['join', 'leave', 'members', 'updateMember', 'removeMember'];

        foreach ($methods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Should have {$methodName}");
            $this->assertTrue($reflection->getMethod($methodName)->isPublic(), "{$methodName} should be public");
        }
    }

    public function testHasPendingRequestsMethods(): void
    {
        $reflection = new \ReflectionClass(GroupsApiController::class);
        $this->assertTrue($reflection->hasMethod('pendingRequests'));
        $this->assertTrue($reflection->hasMethod('handleRequest'));
    }

    public function testHasDiscussionMethods(): void
    {
        $reflection = new \ReflectionClass(GroupsApiController::class);
        $methods = ['discussions', 'createDiscussion', 'discussionMessages', 'postToDiscussion'];

        foreach ($methods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Should have {$methodName}");
            $this->assertTrue($reflection->getMethod($methodName)->isPublic(), "{$methodName} should be public");
        }
    }

    public function testHasUploadImageMethod(): void
    {
        $reflection = new \ReflectionClass(GroupsApiController::class);
        $this->assertTrue($reflection->hasMethod('uploadImage'));
    }
}
