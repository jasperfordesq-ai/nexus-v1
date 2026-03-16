<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\CommunityProjectService;

/**
 * CommunityProjectService Tests
 *
 * Tests community project proposals, reviews, support/unsupport,
 * and conversion to volunteering opportunities.
 */
class CommunityProjectServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $testOrgId = null;
    protected static ?int $testProjectId = null;

    /** @var int[] Track all project IDs created during tests for cleanup */
    protected static array $createdProjectIds = [];

    /** @var int[] Track all opportunity IDs created during tests for cleanup */
    protected static array $createdOpportunityIds = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $ts = time();

        // Create test user 1
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$testTenantId, "cptest_user1_{$ts}@test.com", "cptest_user1_{$ts}", 'CP', 'One', 'CP One', 100]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test user 2
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$testTenantId, "cptest_user2_{$ts}@test.com", "cptest_user2_{$ts}", 'CP', 'Two', 'CP Two', 50]
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();

        // Create test organization (needed for convertToOpportunity flow)
        Database::query(
            "INSERT INTO vol_organizations (tenant_id, user_id, name, description, status, created_at)
             VALUES (?, ?, ?, ?, 'approved', NOW())",
            [self::$testTenantId, self::$testUserId, "CP Test Org {$ts}", 'Test organization for community project tests']
        );
        self::$testOrgId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        // Cleanup supporters first (foreign key references)
        if (!empty(self::$createdProjectIds)) {
            foreach (self::$createdProjectIds as $pid) {
                try {
                    Database::query("DELETE FROM vol_community_project_supporters WHERE project_id = ?", [$pid]);
                } catch (\Exception $e) {}
            }
        }

        // Cleanup created opportunities
        if (!empty(self::$createdOpportunityIds)) {
            foreach (self::$createdOpportunityIds as $oid) {
                try {
                    Database::query("DELETE FROM vol_opportunities WHERE id = ?", [$oid]);
                } catch (\Exception $e) {}
            }
        }

        // Cleanup created projects
        if (!empty(self::$createdProjectIds)) {
            foreach (self::$createdProjectIds as $pid) {
                try {
                    Database::query("DELETE FROM vol_community_projects WHERE id = ?", [$pid]);
                } catch (\Exception $e) {}
            }
        }

        // Cleanup org
        if (self::$testOrgId) {
            try {
                Database::query("DELETE FROM vol_organizations WHERE id = ?", [self::$testOrgId]);
            } catch (\Exception $e) {}
        }

        // Cleanup users
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUser2Id) {
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUser2Id]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Class & Method Existence Tests
    // ==========================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(CommunityProjectService::class));
    }

    public function testGetErrorsIsStatic(): void
    {
        $ref = new \ReflectionMethod(CommunityProjectService::class, 'getErrors');
        $this->assertTrue($ref->isStatic());
    }

    public function testProposeIsStatic(): void
    {
        $ref = new \ReflectionMethod(CommunityProjectService::class, 'propose');
        $this->assertTrue($ref->isStatic());
    }

    public function testGetProposalsIsStatic(): void
    {
        $ref = new \ReflectionMethod(CommunityProjectService::class, 'getProposals');
        $this->assertTrue($ref->isStatic());
    }

    public function testGetProposalIsStatic(): void
    {
        $ref = new \ReflectionMethod(CommunityProjectService::class, 'getProposal');
        $this->assertTrue($ref->isStatic());
    }

    public function testUpdateProposalIsStatic(): void
    {
        $ref = new \ReflectionMethod(CommunityProjectService::class, 'updateProposal');
        $this->assertTrue($ref->isStatic());
    }

    public function testReviewIsStatic(): void
    {
        $ref = new \ReflectionMethod(CommunityProjectService::class, 'review');
        $this->assertTrue($ref->isStatic());
    }

    public function testSupportIsStatic(): void
    {
        $ref = new \ReflectionMethod(CommunityProjectService::class, 'support');
        $this->assertTrue($ref->isStatic());
    }

    public function testUnsupportIsStatic(): void
    {
        $ref = new \ReflectionMethod(CommunityProjectService::class, 'unsupport');
        $this->assertTrue($ref->isStatic());
    }

    public function testGetSupportersIsStatic(): void
    {
        $ref = new \ReflectionMethod(CommunityProjectService::class, 'getSupporters');
        $this->assertTrue($ref->isStatic());
    }

    public function testConvertToOpportunityIsStatic(): void
    {
        $ref = new \ReflectionMethod(CommunityProjectService::class, 'convertToOpportunity');
        $this->assertTrue($ref->isStatic());
    }

    // ==========================================
    // Propose Tests
    // ==========================================

    public function testProposeWithValidDataReturnsArray(): void
    {
        $ts = time();
        $result = CommunityProjectService::propose(self::$testUserId, [
            'title' => "Community Garden {$ts}",
            'description' => 'A shared garden for the neighbourhood',
        ]);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('proposed', $result['status']);
        $this->assertStringContainsString("Community Garden {$ts}", $result['title']);

        self::$createdProjectIds[] = $result['id'];
        self::$testProjectId = $result['id'];
    }

    public function testProposeWithAllOptionalFields(): void
    {
        $ts = time();
        $result = CommunityProjectService::propose(self::$testUserId, [
            'title' => "Full Project {$ts}",
            'description' => 'A fully detailed project proposal',
            'category' => 'environment',
            'location' => 'City Park',
            'latitude' => 51.8985,
            'longitude' => -8.4756,
            'target_volunteers' => 15,
            'proposed_date' => '2026-06-15',
        ]);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('id', $result);

        self::$createdProjectIds[] = $result['id'];
    }

    public function testProposeRequiresTitle(): void
    {
        $result = CommunityProjectService::propose(self::$testUserId, [
            'title' => '',
            'description' => 'Some description',
        ]);

        $this->assertEmpty($result);
        $errors = CommunityProjectService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('title', $errors[0]['field']);
    }

    public function testProposeRequiresDescription(): void
    {
        $result = CommunityProjectService::propose(self::$testUserId, [
            'title' => 'Valid Title',
            'description' => '',
        ]);

        $this->assertEmpty($result);
        $errors = CommunityProjectService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('description', $errors[0]['field']);
    }

    // ==========================================
    // Get Proposals Tests
    // ==========================================

    public function testGetProposalsReturnsPaginatedStructure(): void
    {
        $result = CommunityProjectService::getProposals();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsBool($result['has_more']);
    }

    public function testGetProposalsWithStatusFilter(): void
    {
        $result = CommunityProjectService::getProposals(['status' => 'proposed']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        foreach ($result['items'] as $item) {
            $this->assertEquals('proposed', $item['status']);
        }
    }

    // ==========================================
    // Get Proposal by ID Tests
    // ==========================================

    public function testGetProposalByIdReturnsData(): void
    {
        // Create a project to retrieve
        $ts = time();
        $created = CommunityProjectService::propose(self::$testUserId, [
            'title' => "Retrieve Test {$ts}",
            'description' => 'Project to test retrieval',
        ]);
        self::$createdProjectIds[] = $created['id'];

        $result = CommunityProjectService::getProposal($created['id']);

        $this->assertIsArray($result);
        $this->assertEquals($created['id'], $result['id']);
        $this->assertStringContainsString("Retrieve Test {$ts}", $result['title']);
        $this->assertArrayHasKey('supporter_count', $result);
    }

    public function testGetProposalByIdReturnsNullForNonExistent(): void
    {
        $result = CommunityProjectService::getProposal(999999999);

        $this->assertNull($result);
    }

    // ==========================================
    // Update Proposal Tests
    // ==========================================

    public function testUpdateProposalByOwnerSucceeds(): void
    {
        $ts = time();
        $created = CommunityProjectService::propose(self::$testUserId, [
            'title' => "Update Test {$ts}",
            'description' => 'Original description',
        ]);
        self::$createdProjectIds[] = $created['id'];

        $result = CommunityProjectService::updateProposal($created['id'], self::$testUserId, [
            'title' => "Updated Title {$ts}",
            'description' => 'Updated description',
        ]);

        $this->assertTrue($result);

        // Verify the update
        $updated = CommunityProjectService::getProposal($created['id']);
        $this->assertStringContainsString("Updated Title {$ts}", $updated['title']);
        $this->assertEquals('Updated description', $updated['description']);
    }

    public function testUpdateProposalByNonOwnerFails(): void
    {
        $ts = time();
        $created = CommunityProjectService::propose(self::$testUserId, [
            'title' => "Owner Test {$ts}",
            'description' => 'Only owner should edit',
        ]);
        self::$createdProjectIds[] = $created['id'];

        $result = CommunityProjectService::updateProposal($created['id'], self::$testUser2Id, [
            'title' => 'Hijacked Title',
        ]);

        $this->assertFalse($result);
        $errors = CommunityProjectService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('FORBIDDEN', $errors[0]['code']);
    }

    // ==========================================
    // Review Tests
    // ==========================================

    public function testReviewApproveChangesStatus(): void
    {
        $ts = time();
        $created = CommunityProjectService::propose(self::$testUserId, [
            'title' => "Approve Test {$ts}",
            'description' => 'Project to approve',
        ]);
        self::$createdProjectIds[] = $created['id'];

        $result = CommunityProjectService::review($created['id'], self::$testUser2Id, 'approved', 'Looks great');

        $this->assertTrue($result);

        $reviewed = CommunityProjectService::getProposal($created['id']);
        // After approval, convertToOpportunity sets status to 'active'
        $this->assertContains($reviewed['status'], ['approved', 'active']);

        // Track the opportunity created by auto-conversion
        if ($reviewed['opportunity_id']) {
            self::$createdOpportunityIds[] = $reviewed['opportunity_id'];
        }
    }

    public function testReviewRejectChangesStatus(): void
    {
        $ts = time();
        $created = CommunityProjectService::propose(self::$testUserId, [
            'title' => "Reject Test {$ts}",
            'description' => 'Project to reject',
        ]);
        self::$createdProjectIds[] = $created['id'];

        $result = CommunityProjectService::review($created['id'], self::$testUser2Id, 'rejected', 'Not feasible');

        $this->assertTrue($result);

        $reviewed = CommunityProjectService::getProposal($created['id']);
        $this->assertEquals('rejected', $reviewed['status']);
        $this->assertEquals('Not feasible', $reviewed['review_notes']);
    }

    public function testReviewInvalidStatusFails(): void
    {
        $ts = time();
        $created = CommunityProjectService::propose(self::$testUserId, [
            'title' => "Invalid Review {$ts}",
            'description' => 'Project with invalid review status',
        ]);
        self::$createdProjectIds[] = $created['id'];

        $result = CommunityProjectService::review($created['id'], self::$testUser2Id, 'invalid_status');

        $this->assertFalse($result);
        $errors = CommunityProjectService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('VALIDATION_ERROR', $errors[0]['code']);
    }

    // ==========================================
    // Support / Unsupport Tests
    // ==========================================

    public function testSupportIncrementsUpvotes(): void
    {
        $ts = time();
        $created = CommunityProjectService::propose(self::$testUserId, [
            'title' => "Support Test {$ts}",
            'description' => 'Project to support',
        ]);
        self::$createdProjectIds[] = $created['id'];

        $before = CommunityProjectService::getProposal($created['id']);
        $upvotesBefore = $before['upvotes'];

        $result = CommunityProjectService::support($created['id'], self::$testUser2Id, 'Great idea!');
        $this->assertTrue($result);

        $after = CommunityProjectService::getProposal($created['id']);
        $this->assertEquals($upvotesBefore + 1, $after['upvotes']);
    }

    public function testDoubleSupportReturnsFalse(): void
    {
        $ts = time();
        $created = CommunityProjectService::propose(self::$testUserId, [
            'title' => "Double Support Test {$ts}",
            'description' => 'Project for double support test',
        ]);
        self::$createdProjectIds[] = $created['id'];

        // First support should succeed
        $first = CommunityProjectService::support($created['id'], self::$testUser2Id);
        $this->assertTrue($first);

        // Second support by same user should fail
        $second = CommunityProjectService::support($created['id'], self::$testUser2Id);
        $this->assertFalse($second);

        $errors = CommunityProjectService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('DUPLICATE', $errors[0]['code']);
    }

    public function testUnsupportDecrementsUpvotes(): void
    {
        $ts = time();
        $created = CommunityProjectService::propose(self::$testUserId, [
            'title' => "Unsupport Test {$ts}",
            'description' => 'Project for unsupport test',
        ]);
        self::$createdProjectIds[] = $created['id'];

        // Support first
        CommunityProjectService::support($created['id'], self::$testUser2Id);
        $afterSupport = CommunityProjectService::getProposal($created['id']);
        $upvotesAfterSupport = $afterSupport['upvotes'];

        // Unsupport
        $result = CommunityProjectService::unsupport($created['id'], self::$testUser2Id);
        $this->assertTrue($result);

        $afterUnsupport = CommunityProjectService::getProposal($created['id']);
        $this->assertEquals($upvotesAfterSupport - 1, $afterUnsupport['upvotes']);
    }

    public function testUnsupportWithoutSupportReturnsFalse(): void
    {
        $ts = time();
        $created = CommunityProjectService::propose(self::$testUserId, [
            'title' => "Unsupport Fail {$ts}",
            'description' => 'Project never supported',
        ]);
        self::$createdProjectIds[] = $created['id'];

        $result = CommunityProjectService::unsupport($created['id'], self::$testUser2Id);
        $this->assertFalse($result);
    }

    // ==========================================
    // Get Supporters Tests
    // ==========================================

    public function testGetSupportersReturnsArray(): void
    {
        $ts = time();
        $created = CommunityProjectService::propose(self::$testUserId, [
            'title' => "Supporters List {$ts}",
            'description' => 'Project with supporters',
        ]);
        self::$createdProjectIds[] = $created['id'];

        // Add a supporter
        CommunityProjectService::support($created['id'], self::$testUser2Id, 'I support this');

        $supporters = CommunityProjectService::getSupporters($created['id']);

        $this->assertIsArray($supporters);
        $this->assertGreaterThanOrEqual(1, count($supporters));
        $this->assertEquals(self::$testUser2Id, (int)$supporters[0]['user_id']);
    }

    public function testGetSupportersEmptyForNewProject(): void
    {
        $ts = time();
        $created = CommunityProjectService::propose(self::$testUserId, [
            'title' => "No Supporters {$ts}",
            'description' => 'Project with no supporters yet',
        ]);
        self::$createdProjectIds[] = $created['id'];

        $supporters = CommunityProjectService::getSupporters($created['id']);

        $this->assertIsArray($supporters);
        $this->assertCount(0, $supporters);
    }

    // ==========================================
    // Convert to Opportunity Tests
    // ==========================================

    public function testConvertApprovedProjectToOpportunity(): void
    {
        $ts = time();
        $created = CommunityProjectService::propose(self::$testUserId, [
            'title' => "Convert Test {$ts}",
            'description' => 'Project to convert to opportunity',
            'location' => 'Main Street',
            'target_volunteers' => 10,
            'proposed_date' => '2026-07-01',
        ]);
        self::$createdProjectIds[] = $created['id'];

        // First approve it (review auto-calls convertToOpportunity)
        // But let's test convertToOpportunity directly by manually setting status to approved
        Database::query(
            "UPDATE vol_community_projects SET status = 'approved' WHERE id = ? AND tenant_id = ?",
            [$created['id'], self::$testTenantId]
        );

        $opportunityId = CommunityProjectService::convertToOpportunity($created['id']);

        $this->assertIsInt($opportunityId);
        $this->assertGreaterThan(0, $opportunityId);

        self::$createdOpportunityIds[] = $opportunityId;

        // Verify the project is now linked and active
        $updated = CommunityProjectService::getProposal($created['id']);
        $this->assertEquals($opportunityId, $updated['opportunity_id']);
        $this->assertEquals('active', $updated['status']);
    }

    public function testConvertNonApprovedProjectReturnsNull(): void
    {
        $ts = time();
        $created = CommunityProjectService::propose(self::$testUserId, [
            'title' => "Convert Fail {$ts}",
            'description' => 'This project is still pending, cannot convert',
        ]);
        self::$createdProjectIds[] = $created['id'];

        // Project is 'pending' status, should not convert
        $result = CommunityProjectService::convertToOpportunity($created['id']);

        $this->assertNull($result);
        $errors = CommunityProjectService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertEquals('VALIDATION_ERROR', $errors[0]['code']);
    }
}
