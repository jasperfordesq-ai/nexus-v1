<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\DatabaseTestCase;
use App\Core\Database;
use App\Core\TenantContext;
use App\Services\TeamTaskService;

/**
 * TeamTaskService Tests
 *
 * Tests task CRUD and statistics.
 */
class TeamTaskServiceTest extends DatabaseTestCase
{
    private const TENANT_ID = 2;
    private TeamTaskService $service;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        $this->service = new TeamTaskService();
    }

    // ==========================================
    // create
    // ==========================================

    public function testCreateReturnsNullForEmptyTitle(): void
    {
        $this->requireTables(['team_tasks']);

        $userId = $this->createUser('task-notitle');

        $result = $this->service->create(1, $userId, ['title' => '']);

        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame('VALIDATION_ERROR', $errors[0]['code']);
    }

    public function testCreateRejectsInvalidStatus(): void
    {
        $this->requireTables(['team_tasks']);

        $userId = $this->createUser('task-badstatus');

        $result = $this->service->create(1, $userId, [
            'title' => 'Valid Title',
            'status' => 'not_a_status',
        ]);

        $this->assertNull($result);
        $errors = $this->service->getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testCreateRejectsInvalidPriority(): void
    {
        $this->requireTables(['team_tasks']);

        $userId = $this->createUser('task-badpri');

        $result = $this->service->create(1, $userId, [
            'title' => 'Valid Title',
            'priority' => 'not_a_priority',
        ]);

        $this->assertNull($result);
    }

    public function testCreateSucceedsWithValidData(): void
    {
        $this->requireTables(['team_tasks']);

        $groupId = 99993;
        $userId = $this->createUser('task-create');

        $taskId = $this->service->create($groupId, $userId, [
            'title' => 'Build landing page',
            'description' => 'Create the initial landing page design',
            'status' => 'todo',
            'priority' => 'high',
            'due_date' => '2026-04-01',
        ]);

        $this->assertNotNull($taskId);
        $this->assertIsInt($taskId);
        $this->assertGreaterThan(0, $taskId);
    }

    public function testCreateSetsCompletedAtWhenStatusIsDone(): void
    {
        $this->requireTables(['team_tasks']);

        $groupId = 99994;
        $userId = $this->createUser('task-done');

        $taskId = $this->service->create($groupId, $userId, [
            'title' => 'Already completed',
            'status' => 'done',
        ]);

        $this->assertNotNull($taskId);

        $task = $this->service->getById($taskId);
        $this->assertNotNull($task);
        $this->assertNotNull($task['completed_at']);
    }

    // ==========================================
    // getById
    // ==========================================

    public function testGetByIdReturnsNullForNonexistent(): void
    {
        $this->requireTables(['team_tasks']);

        $result = $this->service->getById(999999);
        $this->assertNull($result);
    }

    public function testGetByIdReturnsTask(): void
    {
        $this->requireTables(['team_tasks']);

        $groupId = 99995;
        $userId = $this->createUser('task-getbyid');

        $taskId = $this->service->create($groupId, $userId, [
            'title' => 'Fetch me',
            'priority' => 'medium',
        ]);
        $this->assertNotNull($taskId);

        $task = $this->service->getById($taskId);

        $this->assertNotNull($task);
        $this->assertSame('Fetch me', $task['title']);
        $this->assertSame('medium', $task['priority']);
    }

    // ==========================================
    // getTasks
    // ==========================================

    public function testGetTasksReturnsPaginatedResult(): void
    {
        $this->requireTables(['team_tasks']);

        $groupId = 99996;
        $userId = $this->createUser('task-list');

        $this->service->create($groupId, $userId, ['title' => 'Task A']);
        $this->service->create($groupId, $userId, ['title' => 'Task B']);

        $result = $this->service->getTasks($groupId);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertGreaterThanOrEqual(2, count($result['items']));
    }

    public function testGetTasksFiltersbyStatus(): void
    {
        $this->requireTables(['team_tasks']);

        $groupId = 99997;
        $userId = $this->createUser('task-filter');

        $this->service->create($groupId, $userId, ['title' => 'Todo Task', 'status' => 'todo']);
        $this->service->create($groupId, $userId, ['title' => 'Done Task', 'status' => 'done']);

        $result = $this->service->getTasks($groupId, ['status' => 'done']);

        foreach ($result['items'] as $item) {
            $this->assertSame('done', $item['status']);
        }
    }

    // ==========================================
    // update
    // ==========================================

    public function testUpdateReturnsFalseForNonexistentTask(): void
    {
        $this->requireTables(['team_tasks']);

        $userId = $this->createUser('task-updbad');
        $result = $this->service->update(999999, $userId, ['title' => 'New']);

        $this->assertFalse($result);
    }

    public function testUpdateChangesFields(): void
    {
        $this->requireTables(['team_tasks']);

        $groupId = 99998;
        $userId = $this->createUser('task-update');

        $taskId = $this->service->create($groupId, $userId, ['title' => 'Old Title']);
        $this->assertNotNull($taskId);

        $result = $this->service->update($taskId, $userId, [
            'title' => 'New Title',
            'status' => 'in_progress',
            'priority' => 'urgent',
        ]);

        $this->assertTrue($result);

        $task = $this->service->getById($taskId);
        $this->assertSame('New Title', $task['title']);
        $this->assertSame('in_progress', $task['status']);
        $this->assertSame('urgent', $task['priority']);
    }

    public function testUpdateSetsCompletedAtWhenTransitioningToDone(): void
    {
        $this->requireTables(['team_tasks']);

        $groupId = 99999;
        $userId = $this->createUser('task-complete');

        $taskId = $this->service->create($groupId, $userId, ['title' => 'To Complete', 'status' => 'todo']);
        $this->assertNotNull($taskId);

        $this->service->update($taskId, $userId, ['status' => 'done']);

        $task = $this->service->getById($taskId);
        $this->assertNotNull($task['completed_at']);
    }

    public function testUpdateClearsCompletedAtWhenMovingAwayFromDone(): void
    {
        $this->requireTables(['team_tasks']);

        $groupId = 88888;
        $userId = $this->createUser('task-undone');

        $taskId = $this->service->create($groupId, $userId, ['title' => 'Undo Done', 'status' => 'done']);
        $this->assertNotNull($taskId);

        $this->service->update($taskId, $userId, ['status' => 'in_progress']);

        $task = $this->service->getById($taskId);
        $this->assertNull($task['completed_at']);
    }

    public function testUpdateRejectsEmptyTitle(): void
    {
        $this->requireTables(['team_tasks']);

        $groupId = 88889;
        $userId = $this->createUser('task-emptytitle');

        $taskId = $this->service->create($groupId, $userId, ['title' => 'Original']);
        $this->assertNotNull($taskId);

        $result = $this->service->update($taskId, $userId, ['title' => '']);

        $this->assertFalse($result);
    }

    // ==========================================
    // delete
    // ==========================================

    public function testDeleteReturnsFalseForNonexistentTask(): void
    {
        $this->requireTables(['team_tasks']);

        $userId = $this->createUser('task-delbad');
        $result = $this->service->delete(999999, $userId);

        $this->assertFalse($result);
    }

    public function testDeleteRemovesTask(): void
    {
        $this->requireTables(['team_tasks']);

        $groupId = 88890;
        $userId = $this->createUser('task-delete');

        $taskId = $this->service->create($groupId, $userId, ['title' => 'Delete Me']);
        $this->assertNotNull($taskId);

        $result = $this->service->delete($taskId, $userId);

        $this->assertTrue($result);
        $this->assertNull($this->service->getById($taskId));
    }

    // ==========================================
    // getStats
    // ==========================================

    public function testGetStatsReturnsAllCounts(): void
    {
        $this->requireTables(['team_tasks']);

        $groupId = 88891;
        $userId = $this->createUser('task-stats');

        $this->service->create($groupId, $userId, ['title' => 'S1', 'status' => 'todo']);
        $this->service->create($groupId, $userId, ['title' => 'S2', 'status' => 'in_progress']);
        $this->service->create($groupId, $userId, ['title' => 'S3', 'status' => 'done']);

        $stats = $this->service->getStats($groupId);

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('todo', $stats);
        $this->assertArrayHasKey('in_progress', $stats);
        $this->assertArrayHasKey('done', $stats);
        $this->assertArrayHasKey('overdue', $stats);
        $this->assertGreaterThanOrEqual(3, $stats['total']);
        $this->assertGreaterThanOrEqual(1, $stats['todo']);
        $this->assertGreaterThanOrEqual(1, $stats['in_progress']);
        $this->assertGreaterThanOrEqual(1, $stats['done']);
    }

    public function testGetStatsReturnsZerosForEmptyGroup(): void
    {
        $this->requireTables(['team_tasks']);

        $stats = $this->service->getStats(777777);

        $this->assertSame(0, $stats['total']);
        $this->assertSame(0, $stats['todo']);
        $this->assertSame(0, $stats['in_progress']);
        $this->assertSame(0, $stats['done']);
        $this->assertSame(0, $stats['overdue']);
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function createUser(string $prefix): int
    {
        $uniq = $prefix . '-' . str_replace('.', '', (string) microtime(true)) . '-' . random_int(1000, 9999);
        Database::query(
            'INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())',
            [self::TENANT_ID, $uniq . '@example.test', $uniq, 'Test', 'User', 'Test User', 0]
        );
        return (int) Database::getInstance()->lastInsertId();
    }

    /** @param string[] $tables */
    private function requireTables(array $tables): void
    {
        foreach ($tables as $table) {
            $exists = (int) Database::query(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            )->fetchColumn();
            if ($exists === 0) {
                $this->markTestSkipped('Required table not present in test DB: ' . $table);
            }
        }
    }
}
