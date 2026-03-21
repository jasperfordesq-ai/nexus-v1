<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\GroupChatroomService;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * GroupChatroomServiceTest — tests for group chatroom CRUD and messaging.
 */
class GroupChatroomServiceTest extends TestCase
{
    private GroupChatroomService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GroupChatroomService();
        TenantContext::setById(1);
    }

    // =========================================================================
    // getChatrooms
    // =========================================================================

    public function testGetChatroomsReturnsFormattedArray(): void
    {
        $rows = collect([
            (object) [
                'id' => 1,
                'group_id' => 10,
                'name' => 'General',
                'description' => 'Default chatroom',
                'is_default' => 1,
                'created_by' => 5,
                'created_at' => '2026-01-01 00:00:00',
            ],
        ]);

        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('orderByDesc')->once()->andReturnSelf();
        DB::shouldReceive('orderBy')->once()->andReturnSelf();
        DB::shouldReceive('get')->once()->andReturn($rows);

        $result = $this->service->getChatrooms(10);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('General', $result[0]['name']);
        $this->assertTrue($result[0]['is_default']);
        $this->assertEquals(1, $result[0]['id']);
    }

    // =========================================================================
    // getById
    // =========================================================================

    public function testGetByIdReturnsNullForNonExistent(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('first')->once()->andReturn(null);

        $result = $this->service->getById(999);
        $this->assertNull($result);
    }

    public function testGetByIdReturnsFormattedArray(): void
    {
        $chatroom = (object) [
            'id' => 1,
            'group_id' => 10,
            'name' => 'General',
            'description' => 'Default chatroom',
            'is_default' => 1,
            'created_by' => 5,
            'created_at' => '2026-01-01 00:00:00',
        ];

        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('first')->once()->andReturn($chatroom);

        $result = $this->service->getById(1);

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('General', $result['name']);
        $this->assertTrue($result['is_default']);
    }

    // =========================================================================
    // create — validation
    // =========================================================================

    public function testCreateRejectsEmptyName(): void
    {
        $result = $this->service->create(10, 1, ['name' => '']);
        $this->assertNull($result);

        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('VALIDATION_ERROR', $errors[0]['code']);
        $this->assertEquals('name', $errors[0]['field']);
    }

    public function testCreateRejectsNameOver100Chars(): void
    {
        $result = $this->service->create(10, 1, ['name' => str_repeat('a', 101)]);
        $this->assertNull($result);

        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('100 characters', $errors[0]['message']);
    }

    public function testCreateRejectsNonMember(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->times(3)->andReturnSelf();
        DB::shouldReceive('exists')->once()->andReturn(false);

        $result = $this->service->create(10, 99, ['name' => 'My Room']);
        $this->assertNull($result);

        $errors = $this->service->getErrors();
        $this->assertEquals('FORBIDDEN', $errors[0]['code']);
    }

    public function testCreateReturnsIdOnSuccess(): void
    {
        // isMember check
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->times(3)->andReturnSelf();
        DB::shouldReceive('exists')->once()->andReturn(true);

        // insert
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('insertGetId')->once()->andReturn(42);

        $result = $this->service->create(10, 1, ['name' => 'Dev Room', 'description' => 'For devs']);
        $this->assertEquals(42, $result);
    }

    // =========================================================================
    // delete
    // =========================================================================

    public function testDeleteRejectsNonExistentChatroom(): void
    {
        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('first')->once()->andReturn(null);

        $result = $this->service->delete(999, 1);
        $this->assertFalse($result);
        $this->assertEquals('NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function testDeleteRejectsDefaultChatroom(): void
    {
        $chatroom = (object) [
            'id' => 1,
            'group_id' => 10,
            'is_default' => 1,
            'created_by' => 5,
        ];

        DB::shouldReceive('table')->once()->andReturnSelf();
        DB::shouldReceive('where')->twice()->andReturnSelf();
        DB::shouldReceive('first')->once()->andReturn($chatroom);

        $result = $this->service->delete(1, 5);
        $this->assertFalse($result);
        $this->assertEquals('FORBIDDEN', $this->service->getErrors()[0]['code']);
    }

    // =========================================================================
    // postMessage — validation
    // =========================================================================

    public function testPostMessageRejectsEmptyBody(): void
    {
        $result = $this->service->postMessage(1, 1, '');
        $this->assertNull($result);

        $errors = $this->service->getErrors();
        $this->assertEquals('VALIDATION_ERROR', $errors[0]['code']);
        $this->assertEquals('body', $errors[0]['field']);
    }

    public function testPostMessageRejectsWhitespaceBody(): void
    {
        $result = $this->service->postMessage(1, 1, '   ');
        $this->assertNull($result);
    }

    // =========================================================================
    // getErrors
    // =========================================================================

    public function testGetErrorsStartsEmpty(): void
    {
        $this->assertEmpty($this->service->getErrors());
    }

    public function testGetErrorsResetsBetweenOperations(): void
    {
        // First operation sets errors
        $this->service->create(10, 1, ['name' => '']);
        $this->assertNotEmpty($this->service->getErrors());

        // Second operation resets errors
        $this->service->postMessage(1, 1, '');
        $errors = $this->service->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals('body', $errors[0]['field']);
    }
}
