<?php

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\DeliverabilityTrackingService;
use Nexus\Models\Deliverable;
use Nexus\Models\DeliverableMilestone;
use Nexus\Core\TenantContext;
use Tests\DatabaseTestCase;

class DeliverabilityTrackingServiceTest extends DatabaseTestCase
{
    private $testUserId;
    private $testTenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testTenantId = 1;
        TenantContext::setById($this->testTenantId);

        $this->testUserId = $this->createTestUser();
    }

    public function testCreateDeliverableWithService()
    {
        $deliverable = DeliverabilityTrackingService::createDeliverable(
            $this->testUserId,
            'Service Created Deliverable',
            'Test description',
            ['priority' => 'high']
        );

        $this->assertNotFalse($deliverable);
        $this->assertEquals('Service Created Deliverable', $deliverable['title']);
        $this->assertEquals('high', $deliverable['priority']);
    }

    public function testUpdateDeliverableStatus()
    {
        $deliverable = Deliverable::create($this->testUserId, 'Status Update Test');

        $result = DeliverabilityTrackingService::updateDeliverableStatus(
            $deliverable['id'],
            'in_progress',
            $this->testUserId
        );

        $this->assertTrue($result);

        $updated = Deliverable::findById($deliverable['id']);
        $this->assertEquals('in_progress', $updated['status']);
        $this->assertEquals(50, $updated['progress_percentage']); // Auto-updated
    }

    public function testUpdateStatusAutoProgressMapping()
    {
        $deliverable = Deliverable::create($this->testUserId, 'Auto Progress Test');

        // Test different status -> progress mappings
        DeliverabilityTrackingService::updateDeliverableStatus($deliverable['id'], 'ready', $this->testUserId);
        $updated = Deliverable::findById($deliverable['id']);
        $this->assertEquals(10, $updated['progress_percentage']);

        DeliverabilityTrackingService::updateDeliverableStatus($deliverable['id'], 'review', $this->testUserId);
        $updated = Deliverable::findById($deliverable['id']);
        $this->assertEquals(90, $updated['progress_percentage']);

        DeliverabilityTrackingService::updateDeliverableStatus($deliverable['id'], 'completed', $this->testUserId);
        $updated = Deliverable::findById($deliverable['id']);
        $this->assertEquals(100, $updated['progress_percentage']);
    }

    public function testCompleteDeliverable()
    {
        $deliverable = Deliverable::create($this->testUserId, 'Completion Test');

        $result = DeliverabilityTrackingService::completeDeliverable(
            $deliverable['id'],
            $this->testUserId,
            ['actual_hours' => 15.5]
        );

        $this->assertTrue($result);

        $updated = Deliverable::findById($deliverable['id']);
        $this->assertEquals('completed', $updated['status']);
        $this->assertEquals(100, $updated['progress_percentage']);
        $this->assertEquals(15.5, $updated['actual_hours']);
        $this->assertNotNull($updated['completed_at']);
    }

    public function testRecalculateProgressFromMilestones()
    {
        $deliverable = Deliverable::create($this->testUserId, 'Milestone Progress Test');

        // Create 4 milestones
        DeliverableMilestone::create($deliverable['id'], 'Milestone 1');
        DeliverableMilestone::create($deliverable['id'], 'Milestone 2');
        DeliverableMilestone::create($deliverable['id'], 'Milestone 3');
        $milestone4 = DeliverableMilestone::create($deliverable['id'], 'Milestone 4');

        // Complete 2 out of 4 milestones (50%)
        DeliverableMilestone::complete($milestone4['id'], $this->testUserId);

        $milestones = DeliverableMilestone::getByDeliverable($deliverable['id']);
        $completedMilestone = $milestones[0];
        DeliverableMilestone::complete($completedMilestone['id'], $this->testUserId);

        // Recalculate progress
        $progress = DeliverabilityTrackingService::recalculateProgress(
            $deliverable['id'],
            $this->testUserId
        );

        $this->assertEquals(50, $progress);

        $updated = Deliverable::findById($deliverable['id']);
        $this->assertEquals(50, $updated['progress_percentage']);
    }

    public function testGetAnalytics()
    {
        // Create diverse test data
        Deliverable::create($this->testUserId, 'D1', null, ['status' => 'draft', 'priority' => 'low']);
        Deliverable::create($this->testUserId, 'D2', null, ['status' => 'in_progress', 'priority' => 'high']);
        Deliverable::create($this->testUserId, 'D3', null, ['status' => 'completed', 'priority' => 'medium']);
        Deliverable::create($this->testUserId, 'D4', null, ['status' => 'blocked', 'priority' => 'urgent']);

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
        Deliverable::create($this->testUserId, 'Owned 1', null, ['owner_id' => $this->testUserId]);
        Deliverable::create($this->testUserId, 'Assigned 1', null, ['assigned_to' => $this->testUserId]);

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
        Deliverable::create($this->testUserId, 'Report Test 1', null, ['status' => 'completed']);
        Deliverable::create($this->testUserId, 'Report Test 2', null, ['status' => 'in_progress']);

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
        Deliverable::create($this->testUserId, 'Filtered 1', null, ['status' => 'completed']);
        Deliverable::create($this->testUserId, 'Filtered 2', null, ['status' => 'in_progress']);

        $report = DeliverabilityTrackingService::generateReport(['status' => 'completed']);

        $this->assertEquals('completed', $report['filters']['status']);

        // All deliverables in report should be completed
        foreach ($report['deliverables'] as $deliverable) {
            $this->assertEquals('completed', $deliverable['status']);
        }
    }

    /**
     * Helper method to create test user
     */
    private function createTestUser($email = 'service_test@example.com')
    {
        $this->insertTestData('users', [
            'tenant_id' => $this->testTenantId,
            'email' => $email,
            'first_name' => 'Service',
            'last_name' => 'Test',
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'role' => 'member',
            'balance' => 0
        ]);

        $user = $this->db->query("SELECT * FROM users WHERE email = '{$email}' AND tenant_id = {$this->testTenantId}")
            ->fetch(\PDO::FETCH_ASSOC);

        return $user['id'];
    }

    protected function tearDown(): void
    {
        $this->truncateTable('deliverables');
        $this->truncateTable('deliverable_milestones');
        $this->truncateTable('deliverable_history');

        parent::tearDown();
    }
}
