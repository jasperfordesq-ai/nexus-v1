<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services\Federation;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\FederationUserService;

/**
 * FederationUserService Tests
 *
 * Tests individual user federation settings and preferences.
 */
class FederationUserServiceTest extends DatabaseTestCase
{
    protected static ?int $testUserId = null;
    protected static ?int $testUser2Id = null;
    protected static ?int $tenantId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$tenantId = 2;
        TenantContext::setById(self::$tenantId);

        $timestamp = time();

        // Create test users
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'active', NOW())",
            [self::$tenantId, "fed_user_test_{$timestamp}@test.com", "fed_user_test_{$timestamp}", 'FedUser', 'Test', 'FedUser Test', 100]
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'active', NOW())",
            [self::$tenantId, "fed_user_test2_{$timestamp}@test.com", "fed_user_test2_{$timestamp}", 'FedUser2', 'Test', 'FedUser2 Test', 100]
        );
        self::$testUser2Id = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM federation_user_settings WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM federation_audit_log WHERE actor_user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUser2Id) {
            try {
                Database::query("DELETE FROM federation_user_settings WHERE user_id = ?", [self::$testUser2Id]);
                Database::query("DELETE FROM federation_audit_log WHERE actor_user_id = ?", [self::$testUser2Id]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUser2Id]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // getUserSettings Tests
    // ==========================================

    public function testGetUserSettingsReturnsDefaultsForNewUser(): void
    {
        $result = FederationUserService::getUserSettings(self::$testUserId);

        $this->assertIsArray($result);
        $this->assertEquals(self::$testUserId, $result['user_id']);
        $this->assertFalse($result['federation_optin']);
        $this->assertFalse($result['profile_visible_federated']);
        $this->assertFalse($result['messaging_enabled_federated']);
        $this->assertFalse($result['transactions_enabled_federated']);
        $this->assertFalse($result['appear_in_federated_search']);
        $this->assertFalse($result['show_skills_federated']);
        $this->assertFalse($result['show_location_federated']);
        $this->assertEquals('local_only', $result['service_reach']);
    }

    public function testGetUserSettingsForNonExistentUserReturnsDefaults(): void
    {
        $result = FederationUserService::getUserSettings(999999);

        $this->assertIsArray($result);
        $this->assertEquals(999999, $result['user_id']);
        $this->assertFalse($result['federation_optin']);
    }

    // ==========================================
    // updateSettings Tests
    // ==========================================

    public function testUpdateSettingsCreatesNewRecord(): void
    {
        $result = FederationUserService::updateSettings(self::$testUserId, [
            'federation_optin' => true,
            'profile_visible_federated' => true,
            'messaging_enabled_federated' => true,
            'transactions_enabled_federated' => false,
            'appear_in_federated_search' => true,
            'show_skills_federated' => true,
            'show_location_federated' => false,
            'service_reach' => 'remote_ok',
        ]);

        $this->assertTrue($result);

        // Verify settings were saved
        $settings = FederationUserService::getUserSettings(self::$testUserId);
        $this->assertTrue($settings['federation_optin']);
        $this->assertTrue($settings['profile_visible_federated']);
        $this->assertTrue($settings['messaging_enabled_federated']);
        $this->assertFalse($settings['transactions_enabled_federated']);
        $this->assertTrue($settings['appear_in_federated_search']);
        $this->assertEquals('remote_ok', $settings['service_reach']);
    }

    public function testUpdateSettingsUpdatesExistingRecord(): void
    {
        // First create
        FederationUserService::updateSettings(self::$testUser2Id, [
            'federation_optin' => true,
            'service_reach' => 'local_only',
        ]);

        // Then update
        $result = FederationUserService::updateSettings(self::$testUser2Id, [
            'federation_optin' => true,
            'service_reach' => 'travel_ok',
            'travel_radius_km' => 50,
        ]);

        $this->assertTrue($result);

        $settings = FederationUserService::getUserSettings(self::$testUser2Id);
        $this->assertEquals('travel_ok', $settings['service_reach']);
    }

    public function testUpdateSettingsValidatesServiceReach(): void
    {
        $result = FederationUserService::updateSettings(self::$testUserId, [
            'service_reach' => 'invalid_reach_value',
        ]);

        $this->assertTrue($result);

        // Should default to local_only for invalid value
        $settings = FederationUserService::getUserSettings(self::$testUserId);
        $this->assertEquals('local_only', $settings['service_reach']);
    }

    // ==========================================
    // hasOptedIn Tests
    // ==========================================

    public function testHasOptedInReturnsFalseForNewUser(): void
    {
        // Use user2 before any settings are saved
        $result = FederationUserService::hasOptedIn(999999);

        $this->assertFalse($result);
    }

    public function testHasOptedInReturnsTrueAfterOptIn(): void
    {
        FederationUserService::updateSettings(self::$testUserId, [
            'federation_optin' => true,
        ]);

        $result = FederationUserService::hasOptedIn(self::$testUserId);

        $this->assertTrue($result);
    }

    // ==========================================
    // optOut Tests
    // ==========================================

    public function testOptOutDisablesAllFederation(): void
    {
        // First opt in
        FederationUserService::updateSettings(self::$testUserId, [
            'federation_optin' => true,
            'profile_visible_federated' => true,
            'messaging_enabled_federated' => true,
            'transactions_enabled_federated' => true,
            'appear_in_federated_search' => true,
            'show_skills_federated' => true,
            'show_location_federated' => true,
        ]);

        // Then opt out
        $result = FederationUserService::optOut(self::$testUserId);
        $this->assertTrue($result);

        // Verify all are disabled
        $settings = FederationUserService::getUserSettings(self::$testUserId);
        $this->assertFalse($settings['federation_optin']);
        $this->assertFalse($settings['profile_visible_federated']);
        $this->assertFalse($settings['messaging_enabled_federated']);
        $this->assertFalse($settings['transactions_enabled_federated']);
        $this->assertFalse($settings['appear_in_federated_search']);
        $this->assertFalse($settings['show_skills_federated']);
        $this->assertFalse($settings['show_location_federated']);
    }

    // ==========================================
    // getFederatedUsers Tests
    // ==========================================

    public function testGetFederatedUsersReturnsArray(): void
    {
        $result = FederationUserService::getFederatedUsers(self::$tenantId);

        $this->assertIsArray($result);
    }

    public function testGetFederatedUsersWithServiceReachFilter(): void
    {
        $result = FederationUserService::getFederatedUsers(self::$tenantId, ['service_reach' => 'remote_ok']);

        $this->assertIsArray($result);
    }

    public function testGetFederatedUsersForNonExistentTenantReturnsEmpty(): void
    {
        $result = FederationUserService::getFederatedUsers(999999);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ==========================================
    // isFederationAvailableForUser Tests
    // ==========================================

    public function testIsFederationAvailableForUserReturnsBool(): void
    {
        $result = FederationUserService::isFederationAvailableForUser(self::$testUserId);

        $this->assertIsBool($result);
    }

    public function testIsFederationAvailableForNonExistentUser(): void
    {
        $result = FederationUserService::isFederationAvailableForUser(999999);

        $this->assertFalse($result);
    }

    // ==========================================
    // getTrustScore Tests
    // ==========================================

    public function testGetTrustScoreReturnsExpectedStructure(): void
    {
        $result = FederationUserService::getTrustScore(self::$testUserId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('level', $result);
        $this->assertArrayHasKey('components', $result);
        $this->assertArrayHasKey('details', $result);

        $this->assertIsNumeric($result['score']);
        $this->assertIsString($result['level']);
        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
    }

    public function testGetTrustScoreComponentsStructure(): void
    {
        $result = FederationUserService::getTrustScore(self::$testUserId);

        $this->assertArrayHasKey('reviews', $result['components']);
        $this->assertArrayHasKey('transactions', $result['components']);
        $this->assertArrayHasKey('federation', $result['components']);
    }

    public function testGetTrustScoreDetailsStructure(): void
    {
        $result = FederationUserService::getTrustScore(self::$testUserId);

        $this->assertArrayHasKey('review_count', $result['details']);
        $this->assertArrayHasKey('avg_rating', $result['details']);
        $this->assertArrayHasKey('transaction_count', $result['details']);
        $this->assertArrayHasKey('completion_rate', $result['details']);
        $this->assertArrayHasKey('cross_tenant_activity', $result['details']);
    }

    public function testGetTrustScoreForNewUserReturnsLowScore(): void
    {
        $result = FederationUserService::getTrustScore(self::$testUserId);

        // New user with no activity should have low/zero score
        $this->assertLessThanOrEqual(20, $result['score']);
    }

    public function testGetTrustScoreForNonExistentUser(): void
    {
        $result = FederationUserService::getTrustScore(999999);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['score']);
    }

    public function testGetTrustScoreLevelValues(): void
    {
        $result = FederationUserService::getTrustScore(self::$testUserId);

        $validLevels = ['new', 'growing', 'established', 'trusted', 'excellent', 'unknown'];
        $this->assertContains($result['level'], $validLevels);
    }

    // ==========================================
    // getFederatedReviews Tests
    // ==========================================

    public function testGetFederatedReviewsReturnsExpectedStructure(): void
    {
        $result = FederationUserService::getFederatedReviews(self::$testUserId, 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
    }

    public function testGetFederatedReviewsForNonExistentUser(): void
    {
        $result = FederationUserService::getFederatedReviews(999999, 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('reviews', $result);
        $this->assertEmpty($result['reviews']);
    }
}
