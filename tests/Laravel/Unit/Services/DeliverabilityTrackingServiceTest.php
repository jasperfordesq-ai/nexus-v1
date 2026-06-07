<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\DeliverabilityTrackingService;
use App\Models\Deliverable;
use App\Models\DeliverableMilestone;
use App\Core\TenantContext;
use App\Core\Database;

/**
 * DeliverabilityTrackingServiceTest
 *
 * Uses Database class directly (not DatabaseTestCase) to avoid cross-connection
 * deadlocks between self::$pdo and the Database singleton.
 */
class DeliverabilityTrackingServiceTest extends \Tests\Laravel\TestCase
{
    private $testUserId;
    protected int $delivTenantId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->delivTenantId = 1;
        TenantContext::setById($this->delivTenantId);

        $this->testUserId = $this->createTestUser();
    }

    public function testCreateDeliverableWithService()
    {
        $result = DeliverabilityTrackingService::createDeliverable(
            $this->testUserId,
            'Service Test Deliverable',
            'Test description',
            ['priority' => 'high']
        );

        $this->assertIsArray($result);
        $this->assertEquals('Service Test Deliverable', $result['title']);
        $this->assertEquals($this->testUserId, $result['owner_id']);
        $this->assertEquals('draft', $result['status']);
    }

    public function testUpdateDeliverableStatus()
    {
        $result = DeliverabilityTrackingService::createDeliverable(
            $this->testUserId,
            'Status Update Test'
        );

        $this->assertIsArray($result);

        $updated = DeliverabilityTrackingService::updateDeliverableStatus(
            $result['id'],
            'in_progress',
            $this->testUserId
        );

        $this->assertTrue($updated);

        $deliverable = Database::query(
            "SELECT status FROM deliverables WHERE id = ?",
            [$result['id']]
        )->fetch(\PDO::FETCH_ASSOC);

        $this->assertEquals('in_progress', $deliverable['status']);
    }

    public function testUpdateStatusAutoProgressMapping()
    {
        $result = DeliverabilityTrackingService::createDeliverable(
            $this->testUserId,
            'Progress Mapping Test'
        );

        $this->assertIsArray($result);

        // Update to 'review' should set progress to 75
        DeliverabilityTrackingService::updateDeliverableStatus(
            $result['id'],
            'review',
            $this->testUserId
        );

        $deliverable = Database::query(
            "SELECT progress_percentage FROM deliverables WHERE id = ?",
            [$result['id']]
        )->fetch(\PDO::FETCH_ASSOC);

        $this->assertEquals(75, (float) $deliverable['progress_percentage']);
    }

    public function testCompleteDeliverable()
    {
        $result = DeliverabilityTrackingService::createDeliverable(
            $this->testUserId,
            'Completion Test'
        );

        $this->assertIsArray($result);

        $completed = DeliverabilityTrackingService::completeDeliverable(
            $result['id'],
            $this->testUserId,
            ['actual_hours' => 5.0]
        );

        $this->assertTrue($completed);

        $deliverable = Database::query(
            "SELECT status, progress_percentage, completed_at FROM deliverables WHERE id = ?",
            [$result['id']]
        )->fetch(\PDO::FETCH_ASSOC);

        $this->assertEquals('completed', $deliverable['status']);
        $this->assertEquals(100, (float) $deliverable['progress_percentage']);
        $this->assertNotNull($deliverable['completed_at']);
    }

    public function testRecalculateProgressFromMilestones()
    {
        $deliverable = DeliverabilityTrackingService::createDeliverable($this->testUserId, 'Milestone Progress Test');
        $tenantId = TenantContext::getId();

        // Create 4 milestones (Eloquent — the legacy active-record API is gone)
        $milestones = [];
        for ($i = 1; $i <= 4; $i++) {
            $milestones[] = DeliverableMilestone::create([
                'tenant_id' => $tenantId,
                'deliverable_id' => $deliverable['id'],
                'title' => "Milestone {$i}",
                'order_position' => $i,
            ]);
        }

        // Complete 2 out of 4 milestones (50%)
        $milestones[0]->update(['completed_at' => now(), 'completed_by' => $this->testUserId]);
        $milestones[3]->update(['completed_at' => now(), 'completed_by' => $this->testUserId]);

        // Recalculate progress
        $progress = DeliverabilityTrackingService::recalculateProgress(
            $deliverable['id'],
            $this->testUserId
        );

        $this->assertEquals(50, $progress);

        $updated = Deliverable::query()->find($deliverable['id']);
        $this->assertEquals(50, (float) $updated->progress_percentage);
    }

    public function testGetAnalytics()
    {
        // Create diverse test data
        DeliverabilityTrackingService::createDeliverable($this->testUserId, 'D1', null, ['status' => 'draft', 'priority' => 'low']);
        DeliverabilityTrackingService::createDeliverable($this->testUserId, 'D2', null, ['status' => 'in_progress', 'priority' => 'high']);
        DeliverabilityTrackingService::createDeliverable($this->testUserId, 'D3', null, ['status' => 'completed', 'priority' => 'medium']);
        DeliverabilityTrackingService::createDeliverable($this->testUserId, 'D4', null, ['status' => 'blocked', 'priority' => 'urgent']);

        $analytics = DeliverabilityTrackingService::getAnalytics();

        $this->assertArrayHasKey('overview', $analytics);
        $this->assertArrayHasKey('completion_rate', $analytics);
        $this->assertArrayHasKey('risk_distribution', $analytics);
        $this->assertArrayHasKey('priority_breakdown', $analytics);
        $this->assertArrayHasKey('category_distribution', $analytics);

        $this->assertIsArray($analytics['overview']);
        $this->assertIsNumeric($analytics['completion_rate']);
    }

    public function testGetUserDashboard()
    {
        // Create deliverables for user
        DeliverabilityTrackingService::createDeliverable($this->testUserId, 'Owned 1', null, ['owner_id' => $this->testUserId]);
        DeliverabilityTrackingService::createDeliverable($this->testUserId, 'Assigned 1', null, ['assigned_to' => $this->testUserId]);

        $dashboard = DeliverabilityTrackingService::getUserDashboard($this->testUserId);

        $this->assertArrayHasKey('my_deliverables', $dashboard);
        $this->assertArrayHasKey('owned_deliverables', $dashboard);
        $this->assertArrayHasKey('stats', $dashboard);
        $this->assertArrayHasKey('overdue', $dashboard);
        $this->assertArrayHasKey('urgent', $dashboard);
        $this->assertArrayHasKey('upcoming_deadlines', $dashboard);

        $this->assertIsArray($dashboard['my_deliverables']);
        $this->assertIsArray($dashboard['stats']);
    }

    public function testGenerateReport()
    {
        // Create test deliverables
        DeliverabilityTrackingService::createDeliverable($this->testUserId, 'Report Test 1', null, ['status' => 'completed']);
        DeliverabilityTrackingService::createDeliverable($this->testUserId, 'Report Test 2', null, ['status' => 'in_progress']);

        $report = DeliverabilityTrackingService::generateReport();

        $this->assertArrayHasKey('generated_at', $report);
        $this->assertArrayHasKey('analytics', $report);
        $this->assertArrayHasKey('deliverables', $report);
        $this->assertArrayHasKey('summary', $report);

        $this->assertIsArray($report['analytics']);
        $this->assertIsArray($report['deliverables']);
        $this->assertIsArray($report['summary']);
    }

    public function testGenerateReportWithFilters()
    {
        DeliverabilityTrackingService::createDeliverable($this->testUserId, 'Filtered 1', null, ['status' => 'completed']);
        DeliverabilityTrackingService::createDeliverable($this->testUserId, 'Filtered 2', null, ['status' => 'in_progress']);

        $report = DeliverabilityTrackingService::generateReport(['status' => 'completed']);

        $this->assertEquals('completed', $report['filters']['status']);

        // All deliverables in report should be completed
        foreach ($report['deliverables'] as $deliverable) {
            $this->assertEquals('completed', $deliverable['status']);
        }
    }

    /**
     * Helper method to create test user using the Database singleton
     * (same connection the service code uses, avoiding cross-connection issues)
     */
    private function createTestUser()
    {
        $email = 'deliverability_test_' . uniqid() . '_' . mt_rand(1000, 9999) . '@example.com';
        Database::query(
            "INSERT INTO users (tenant_id, email, first_name, last_name, name, password_hash, role, balance, is_approved, created_at)
             VALUES (?, ?, 'Service', 'Test', 'Service Test', ?, 'member', 0, 1, NOW())",
            [$this->delivTenantId, $email, password_hash('password', PASSWORD_DEFAULT)]
        );
        return (int) Database::lastInsertId();
    }

    protected function tearDown(): void
    {
        // Clean up test data using same Database connection the service uses
        if ($this->testUserId) {
            try {
                Database::query(
                    "DELETE FROM deliverable_history WHERE deliverable_id IN (SELECT id FROM deliverables WHERE owner_id = ?)",
                    [$this->testUserId]
                );
            } catch (\Exception $e) {}
            try {
                Database::query(
                    "DELETE FROM deliverable_milestones WHERE deliverable_id IN (SELECT id FROM deliverables WHERE owner_id = ?)",
                    [$this->testUserId]
                );
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM deliverables WHERE owner_id = ?", [$this->testUserId]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM users WHERE id = ?", [$this->testUserId]);
            } catch (\Exception $e) {}
        }

        parent::tearDown();
    }
}
