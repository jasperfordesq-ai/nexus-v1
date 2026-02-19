<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Models;

use PHPUnit\Framework\TestCase;
use Nexus\Models\Deliverable;
use Nexus\Core\TenantContext;
use Nexus\Tests\DatabaseTestCase;

class DeliverableTest extends DatabaseTestCase
{
    private $testUserId;
    private $testTenantId;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test tenant
        $this->testTenantId = 1;
        TenantContext::setById($this->testTenantId);

        // Create test user
        $this->testUserId = $this->createTestUser();
    }

    public function testCreateDeliverable()
    {
        $deliverable = Deliverable::create(
            $this->testUserId,
            'Test Deliverable',
            'Test description',
            [
                'priority' => 'high',
                'category' => 'development',
                'status' => 'draft'
            ]
        );

        $this->assertNotFalse($deliverable);
        $this->assertEquals('Test Deliverable', $deliverable['title']);
        $this->assertEquals('Test description', $deliverable['description']);
        $this->assertEquals('high', $deliverable['priority']);
        $this->assertEquals('development', $deliverable['category']);
        $this->assertEquals('draft', $deliverable['status']);
        $this->assertEquals($this->testUserId, $deliverable['owner_id']);
    }

    public function testFindById()
    {
        $created = Deliverable::create($this->testUserId, 'Find Test', 'Description');
        $this->assertNotFalse($created);

        $found = Deliverable::findById($created['id']);
        $this->assertNotFalse($found);
        $this->assertEquals($created['id'], $found['id']);
        $this->assertEquals('Find Test', $found['title']);
    }

    public function testUpdateDeliverable()
    {
        $deliverable = Deliverable::create($this->testUserId, 'Original Title', 'Original Description');
        $this->assertNotFalse($deliverable);

        $result = Deliverable::update($deliverable['id'], [
            'title' => 'Updated Title',
            'priority' => 'urgent',
            'progress_percentage' => 50
        ], $this->testUserId);

        $this->assertTrue($result);

        $updated = Deliverable::findById($deliverable['id']);
        $this->assertEquals('Updated Title', $updated['title']);
        $this->assertEquals('urgent', $updated['priority']);
        $this->assertEquals(50, $updated['progress_percentage']);
    }

    public function testUpdateStatus()
    {
        $deliverable = Deliverable::create($this->testUserId, 'Status Test');
        $this->assertNotFalse($deliverable);

        $result = Deliverable::updateStatus($deliverable['id'], 'in_progress', $this->testUserId);
        $this->assertTrue($result);

        $updated = Deliverable::findById($deliverable['id']);
        $this->assertEquals('in_progress', $updated['status']);
    }

    public function testUpdateStatusToCompleted()
    {
        $deliverable = Deliverable::create($this->testUserId, 'Completion Test');
        $this->assertNotFalse($deliverable);

        $result = Deliverable::updateStatus($deliverable['id'], 'completed', $this->testUserId);
        $this->assertTrue($result);

        $updated = Deliverable::findById($deliverable['id']);
        $this->assertEquals('completed', $updated['status']);
        $this->assertNotNull($updated['completed_at']);
    }

    public function testUpdateProgress()
    {
        $deliverable = Deliverable::create($this->testUserId, 'Progress Test');
        $this->assertNotFalse($deliverable);

        $result = Deliverable::updateProgress($deliverable['id'], 75.5, $this->testUserId);
        $this->assertTrue($result);

        $updated = Deliverable::findById($deliverable['id']);
        $this->assertEquals(75.5, $updated['progress_percentage']);
    }

    public function testAssignToUser()
    {
        $assigneeId = $this->createTestUser('assignee@test.com');

        $deliverable = Deliverable::create($this->testUserId, 'Assignment Test');
        $this->assertNotFalse($deliverable);

        $result = Deliverable::assign($deliverable['id'], $assigneeId, null, $this->testUserId);
        $this->assertTrue($result);

        $updated = Deliverable::findById($deliverable['id']);
        $this->assertEquals($assigneeId, $updated['assigned_to']);
    }

    public function testGetAll()
    {
        // Create multiple deliverables
        Deliverable::create($this->testUserId, 'Deliverable 1', 'Desc 1', ['priority' => 'high']);
        Deliverable::create($this->testUserId, 'Deliverable 2', 'Desc 2', ['priority' => 'low']);
        Deliverable::create($this->testUserId, 'Deliverable 3', 'Desc 3', ['status' => 'in_progress']);

        $all = Deliverable::getAll();
        $this->assertGreaterThanOrEqual(3, count($all));
    }

    public function testGetAllWithStatusFilter()
    {
        Deliverable::create($this->testUserId, 'Draft 1', null, ['status' => 'draft']);
        Deliverable::create($this->testUserId, 'In Progress 1', null, ['status' => 'in_progress']);
        Deliverable::create($this->testUserId, 'Draft 2', null, ['status' => 'draft']);

        $drafts = Deliverable::getAll(['status' => 'draft']);
        $this->assertGreaterThanOrEqual(2, count($drafts));

        foreach ($drafts as $deliverable) {
            $this->assertEquals('draft', $deliverable['status']);
        }
    }

    public function testGetAllWithPriorityFilter()
    {
        Deliverable::create($this->testUserId, 'Urgent 1', null, ['priority' => 'urgent']);
        Deliverable::create($this->testUserId, 'Low 1', null, ['priority' => 'low']);

        $urgent = Deliverable::getAll(['priority' => 'urgent']);
        $this->assertGreaterThanOrEqual(1, count($urgent));

        foreach ($urgent as $deliverable) {
            $this->assertEquals('urgent', $deliverable['priority']);
        }
    }

    public function testGetCount()
    {
        $initialCount = Deliverable::getCount();

        Deliverable::create($this->testUserId, 'Count Test 1');
        Deliverable::create($this->testUserId, 'Count Test 2');

        $newCount = Deliverable::getCount();
        $this->assertEquals($initialCount + 2, $newCount);
    }

    public function testGetStats()
    {
        // Create deliverables with different statuses
        Deliverable::create($this->testUserId, 'Draft', null, ['status' => 'draft']);
        Deliverable::create($this->testUserId, 'In Progress', null, ['status' => 'in_progress']);
        Deliverable::create($this->testUserId, 'Completed', null, ['status' => 'completed']);
        Deliverable::create($this->testUserId, 'Blocked', null, ['status' => 'blocked']);

        $stats = Deliverable::getStats($this->testUserId);

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('draft', $stats);
        $this->assertArrayHasKey('in_progress', $stats);
        $this->assertArrayHasKey('completed', $stats);
        $this->assertArrayHasKey('blocked', $stats);

        $this->assertGreaterThanOrEqual(4, $stats['total']);
        $this->assertGreaterThanOrEqual(1, $stats['draft']);
        $this->assertGreaterThanOrEqual(1, $stats['in_progress']);
        $this->assertGreaterThanOrEqual(1, $stats['completed']);
    }

    public function testLogHistory()
    {
        $deliverable = Deliverable::create($this->testUserId, 'History Test');
        $this->assertNotFalse($deliverable);

        $result = Deliverable::logHistory(
            $deliverable['id'],
            $this->testUserId,
            'status_changed',
            'draft',
            'in_progress',
            'status',
            'Status changed to in_progress'
        );

        $this->assertTrue($result);

        $history = Deliverable::getHistory($deliverable['id']);
        $this->assertGreaterThanOrEqual(1, count($history)); // At least creation + this change
    }

    public function testGetHistory()
    {
        $deliverable = Deliverable::create($this->testUserId, 'History Retrieval Test');
        $this->assertNotFalse($deliverable);

        // Create some history entries
        Deliverable::updateStatus($deliverable['id'], 'in_progress', $this->testUserId);
        Deliverable::updateProgress($deliverable['id'], 50, $this->testUserId);

        $history = Deliverable::getHistory($deliverable['id']);

        $this->assertIsArray($history);
        $this->assertGreaterThanOrEqual(3, count($history)); // Create + 2 updates
    }

    public function testDeleteDeliverable()
    {
        $deliverable = Deliverable::create($this->testUserId, 'Delete Test');
        $this->assertNotFalse($deliverable);

        $result = Deliverable::delete($deliverable['id'], $this->testUserId);
        $this->assertTrue($result);

        $deleted = Deliverable::findById($deliverable['id']);
        $this->assertFalse($deleted);
    }

    public function testTagsJsonHandling()
    {
        $tags = ['urgent', 'backend', 'api'];

        $deliverable = Deliverable::create(
            $this->testUserId,
            'Tagged Deliverable',
            'Description',
            ['tags' => $tags]
        );

        $this->assertNotFalse($deliverable);
        $this->assertIsArray($deliverable['tags']);
        $this->assertEquals($tags, $deliverable['tags']);
    }

    public function testCustomFieldsJsonHandling()
    {
        $customFields = [
            'external_ticket_id' => 'JIRA-123',
            'customer_name' => 'Acme Corp',
            'budget' => 5000
        ];

        $deliverable = Deliverable::create($this->testUserId, 'Custom Fields Test');
        $this->assertNotFalse($deliverable);

        $result = Deliverable::update($deliverable['id'], [
            'custom_fields' => $customFields
        ], $this->testUserId);

        $this->assertTrue($result);

        $updated = Deliverable::findById($deliverable['id']);
        $this->assertIsArray($updated['custom_fields']);
        $this->assertEquals('JIRA-123', $updated['custom_fields']['external_ticket_id']);
    }

    /**
     * Helper method to create a test user
     */
    private function createTestUser($email = 'test@example.com')
    {
        return $this->insertTestData('users', [
            'tenant_id' => $this->testTenantId,
            'email' => $email,
            'name' => 'Test User',
            'first_name' => 'Test',
            'last_name' => 'User',
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'member',
            'balance' => 0
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->truncateTable('deliverables');
        $this->truncateTable('deliverable_history');

        parent::tearDown();
    }
}
