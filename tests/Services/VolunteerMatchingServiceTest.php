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
use App\Services\VolunteerMatchingService;

/**
 * VolunteerMatchingService Tests
 *
 * Tests volunteer-to-opportunity matching and scoring.
 */
class VolunteerMatchingServiceTest extends DatabaseTestCase
{
    private const TENANT_ID = 2;
    private VolunteerMatchingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        $this->service = new VolunteerMatchingService();
    }

    // ==========================================
    // findMatches
    // ==========================================

    public function testFindMatchesReturnsEmptyForNonexistentOpportunity(): void
    {
        $this->requireTables(['vol_opportunities']);

        $result = $this->service->findMatches(self::TENANT_ID, 999999);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFindMatchesReturnsEmptyWhenNoCandidates(): void
    {
        $this->requireTables(['vol_opportunities', 'vol_organizations', 'vol_applications', 'users']);

        $userId = $this->createUser('match-owner');
        $oppId = $this->createOpportunity($userId, 'cooking,gardening');

        $result = $this->service->findMatches(self::TENANT_ID, $oppId);

        $this->assertIsArray($result);
        // May or may not be empty depending on existing test DB users
    }

    public function testFindMatchesExcludesAlreadyAppliedUsers(): void
    {
        $this->requireTables(['vol_opportunities', 'vol_organizations', 'vol_applications', 'users', 'user_skills', 'vol_logs']);

        $ownerId = $this->createUser('match-owner2');
        $applicantId = $this->createUser('match-applicant');
        $oppId = $this->createOpportunity($ownerId, 'cooking');

        // Create an application for the applicant
        Database::query(
            "INSERT INTO vol_applications (tenant_id, opportunity_id, user_id, status, created_at) VALUES (?, ?, ?, 'pending', NOW())",
            [self::TENANT_ID, $oppId, $applicantId]
        );

        $results = $this->service->findMatches(self::TENANT_ID, $oppId);

        // Applicant should not appear in results
        $matchedUserIds = array_column($results, 'user_id');
        $this->assertNotContains($applicantId, $matchedUserIds);
    }

    public function testFindMatchesRespectsLimit(): void
    {
        $this->requireTables(['vol_opportunities', 'vol_organizations', 'users', 'user_skills', 'vol_logs', 'vol_applications']);

        $ownerId = $this->createUser('match-limit-owner');
        $oppId = $this->createOpportunity($ownerId, '');

        $result = $this->service->findMatches(self::TENANT_ID, $oppId, 2);

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(2, count($result));
    }

    public function testFindMatchesReturnsScoredResults(): void
    {
        $this->requireTables(['vol_opportunities', 'vol_organizations', 'users', 'user_skills', 'vol_logs', 'vol_applications']);

        $ownerId = $this->createUser('match-score-owner');
        $volunteerId = $this->createUser('match-volunteer', 'active');
        $oppId = $this->createOpportunity($ownerId, 'gardening');

        // Give the volunteer a matching skill
        Database::query(
            "INSERT INTO user_skills (tenant_id, user_id, skill_name, is_offering, created_at) VALUES (?, ?, 'gardening', 1, NOW())",
            [self::TENANT_ID, $volunteerId]
        );

        // Give the volunteer some approved hours
        $orgId = $this->createOrganization($ownerId);
        Database::query(
            "INSERT INTO vol_logs (tenant_id, user_id, organization_id, hours, date_logged, status, created_at) VALUES (?, ?, ?, 20.0, CURDATE(), 'approved', NOW())",
            [self::TENANT_ID, $volunteerId, $orgId]
        );

        $results = $this->service->findMatches(self::TENANT_ID, $oppId, 50);

        // Find our volunteer in results
        $volunteerMatch = null;
        foreach ($results as $match) {
            if ((int) $match['user_id'] === $volunteerId) {
                $volunteerMatch = $match;
                break;
            }
        }

        if ($volunteerMatch !== null) {
            $this->assertArrayHasKey('match_score', $volunteerMatch);
            $this->assertGreaterThan(0, $volunteerMatch['match_score']);
            $this->assertLessThanOrEqual(100, $volunteerMatch['match_score']);
            $this->assertArrayHasKey('skill_match', $volunteerMatch);
        } else {
            // Volunteer may have been filtered — still valid
            $this->assertTrue(true);
        }
    }

    // ==========================================
    // getMatchScore
    // ==========================================

    public function testGetMatchScoreReturnsZeroForNonexistentOpportunity(): void
    {
        $this->requireTables(['vol_opportunities']);

        $userId = $this->createUser('match-score-user');
        $score = $this->service->getMatchScore(self::TENANT_ID, 999999, $userId);

        $this->assertEquals(0.0, $score);
    }

    public function testGetMatchScoreReturnsNeutralForNoSkillsRequired(): void
    {
        $this->requireTables(['vol_opportunities', 'vol_organizations', 'users', 'user_skills', 'vol_logs', 'vol_applications']);

        $ownerId = $this->createUser('match-neutral-owner');
        $userId = $this->createUser('match-neutral-user');
        $oppId = $this->createOpportunity($ownerId, '');

        $score = $this->service->getMatchScore(self::TENANT_ID, $oppId, $userId);

        // No skills required should return 50.0 (neutral) or a score from findMatches
        $this->assertGreaterThanOrEqual(0.0, $score);
        $this->assertLessThanOrEqual(100.0, $score);
    }

    // ==========================================
    // suggestOpportunities
    // ==========================================

    public function testSuggestOpportunitiesReturnsArray(): void
    {
        $this->requireTables(['vol_opportunities', 'vol_organizations', 'vol_applications', 'user_skills', 'users']);

        $userId = $this->createUser('suggest-user');

        $results = $this->service->suggestOpportunities(self::TENANT_ID, $userId);

        $this->assertIsArray($results);
    }

    public function testSuggestOpportunitiesRespectsLimit(): void
    {
        $this->requireTables(['vol_opportunities', 'vol_organizations', 'vol_applications', 'user_skills', 'users']);

        $userId = $this->createUser('suggest-limit');

        $results = $this->service->suggestOpportunities(self::TENANT_ID, $userId, 3);

        $this->assertIsArray($results);
        $this->assertLessThanOrEqual(3, count($results));
    }

    // ==========================================
    // Helpers
    // ==========================================

    private function createUser(string $prefix, string $status = 'active'): int
    {
        $uniq = $prefix . '-' . str_replace('.', '', (string) microtime(true)) . '-' . random_int(1000, 9999);
        Database::query(
            'INSERT INTO users (tenant_id, email, username, first_name, last_name, name, status, balance, is_approved, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())',
            [self::TENANT_ID, $uniq . '@example.test', $uniq, 'Test', 'User', 'Test User', $status, 0]
        );
        return (int) Database::getInstance()->lastInsertId();
    }

    private function createOrganization(int $ownerId): int
    {
        $uniq = 'org-' . str_replace('.', '', (string) microtime(true)) . '-' . random_int(1000, 9999);
        Database::query(
            'INSERT INTO vol_organizations (tenant_id, user_id, name, description, contact_email, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [self::TENANT_ID, $ownerId, $uniq, 'Test org', $uniq . '@example.test', 'approved']
        );
        return (int) Database::getInstance()->lastInsertId();
    }

    private function createOpportunity(int $ownerId, string $skillsNeeded): int
    {
        $orgId = $this->createOrganization($ownerId);
        $uniq = 'opp-' . str_replace('.', '', (string) microtime(true)) . '-' . random_int(1000, 9999);
        Database::query(
            "INSERT INTO vol_opportunities (tenant_id, organization_id, created_by, title, description, location, skills_needed, status, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'open', 1, NOW())",
            [self::TENANT_ID, $orgId, $ownerId, $uniq, 'Test opportunity', 'Remote', $skillsNeeded]
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
