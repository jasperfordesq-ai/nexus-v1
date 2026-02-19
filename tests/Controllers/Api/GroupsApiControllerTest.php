<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\GroupsApiController;

class GroupsApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(GroupsApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(GroupsApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
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

    public function testUpdateMemberAcceptsTwoIntParams(): void
    {
        $reflection = new \ReflectionClass(GroupsApiController::class);
        $method = $reflection->getMethod('updateMember');
        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('id', $params[0]->getName());
        $this->assertEquals('targetUserId', $params[1]->getName());
    }

    public function testDiscussionMessagesAcceptsTwoIntParams(): void
    {
        $reflection = new \ReflectionClass(GroupsApiController::class);
        $method = $reflection->getMethod('discussionMessages');
        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('id', $params[0]->getName());
        $this->assertEquals('discussionId', $params[1]->getName());
    }
}
