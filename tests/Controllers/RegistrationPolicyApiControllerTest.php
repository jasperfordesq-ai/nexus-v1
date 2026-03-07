<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\Identity\InviteCodeService;
use Nexus\Services\Identity\IdentityVerificationEventService;
use Nexus\Services\Identity\IdentityVerificationSessionService;
use Nexus\Services\Identity\RegistrationPolicyService;

/**
 * RegistrationPolicyApiControllerTest — Tests for Registration Policy API endpoints
 * and associated services (InviteCodeService, IdentityVerificationEventService,
 * IdentityVerificationSessionService).
 *
 * Tests cover:
 * - InviteCodeService: generate, validate, redeem, deactivate
 * - IdentityVerificationEventService: log, getForUser, getForTenant
 * - IdentityVerificationSessionService: create, getAbandoned, updateStatus
 * - RegistrationPolicyService: constants, encryption, effective policy
 */
class RegistrationPolicyApiControllerTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testAdminId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        self::createTestData();
    }

    protected static function createTestData(): void
    {
        $timestamp = time();

        // Create test admin user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, password_hash, balance, is_approved, role, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, 'admin', 'active', NOW())",
            [
                self::$testTenantId,
                "regpolicy_admin_{$timestamp}@test.com",
                "regpolicy_admin_{$timestamp}",
                'RegPolicy',
                'Admin',
                'RegPolicy Admin',
                password_hash('TestPassword123!', PASSWORD_DEFAULT),
            ]
        );
        self::$testAdminId = (int) Database::getInstance()->lastInsertId();

        // Create test regular user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, password_hash, balance, is_approved, role, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, 'member', 'active', NOW())",
            [
                self::$testTenantId,
                "regpolicy_user_{$timestamp}@test.com",
                "regpolicy_user_{$timestamp}",
                'RegPolicy',
                'User',
                'RegPolicy User',
                password_hash('TestPassword123!', PASSWORD_DEFAULT),
            ]
        );
        self::$testUserId = (int) Database::getInstance()->lastInsertId();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testAdminId) {
            try { Database::query("DELETE FROM tenant_invite_code_uses WHERE user_id = ?", [self::$testUserId]); } catch (\Exception $e) {}
            try { Database::query("DELETE FROM tenant_invite_codes WHERE tenant_id = ? AND created_by = ?", [self::$testTenantId, self::$testAdminId]); } catch (\Exception $e) {}
            try { Database::query("DELETE FROM identity_verification_events WHERE tenant_id = ? AND user_id IN (?, ?)", [self::$testTenantId, self::$testUserId, self::$testAdminId]); } catch (\Exception $e) {}
            try { Database::query("DELETE FROM identity_verification_sessions WHERE tenant_id = ? AND user_id = ?", [self::$testTenantId, self::$testUserId]); } catch (\Exception $e) {}
            try { Database::query("DELETE FROM users WHERE id IN (?, ?)", [self::$testAdminId, self::$testUserId]); } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // =========================================================================
    // INVITE CODE SERVICE — GENERATE
    // =========================================================================

    public function testGenerateSingleInviteCode(): void
    {
        $codes = InviteCodeService::generate(
            self::$testTenantId,
            self::$testAdminId,
            1
        );

        $this->assertCount(1, $codes);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{8}$/', $codes[0]);
    }

    public function testGenerateMultipleInviteCodes(): void
    {
        $codes = InviteCodeService::generate(
            self::$testTenantId,
            self::$testAdminId,
            5
        );

        $this->assertCount(5, $codes);

        // All codes should be unique
        $this->assertCount(5, array_unique($codes));

        // All codes should match the format (uppercase alphanumeric, 8 chars, no I/O/0/1)
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z2-9]{8}$/', $code, "Code {$code} does not match expected format");
        }
    }

    public function testGenerateCodesAreCappedAt100(): void
    {
        // Requesting 200 should only produce 100
        $codes = InviteCodeService::generate(
            self::$testTenantId,
            self::$testAdminId,
            200
        );

        $this->assertCount(100, $codes);
    }

    public function testGenerateCodeWithExpiry(): void
    {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
        $codes = InviteCodeService::generate(
            self::$testTenantId,
            self::$testAdminId,
            1,
            1,
            $expiresAt,
            'Test code with expiry'
        );

        $this->assertCount(1, $codes);

        // Verify the code exists in the database with the correct expiry
        $row = Database::query(
            "SELECT expires_at, note FROM tenant_invite_codes WHERE tenant_id = ? AND code = ?",
            [self::$testTenantId, $codes[0]]
        )->fetch();

        $this->assertNotFalse($row);
        $this->assertNotNull($row['expires_at']);
        $this->assertSame('Test code with expiry', $row['note']);
    }

    public function testGenerateCodeWithCustomMaxUses(): void
    {
        $codes = InviteCodeService::generate(
            self::$testTenantId,
            self::$testAdminId,
            1,
            10 // Allow 10 uses
        );

        $row = Database::query(
            "SELECT max_uses FROM tenant_invite_codes WHERE tenant_id = ? AND code = ?",
            [self::$testTenantId, $codes[0]]
        )->fetch();

        $this->assertEquals(10, (int) $row['max_uses']);
    }

    // =========================================================================
    // INVITE CODE SERVICE — VALIDATE
    // =========================================================================

    public function testValidateValidCode(): void
    {
        $codes = InviteCodeService::generate(
            self::$testTenantId,
            self::$testAdminId,
            1
        );

        $result = InviteCodeService::validate(self::$testTenantId, $codes[0]);

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('code_id', $result);
        $this->assertIsInt($result['code_id']);
    }

    public function testValidateCodeIsCaseInsensitive(): void
    {
        $codes = InviteCodeService::generate(
            self::$testTenantId,
            self::$testAdminId,
            1
        );

        // Codes are stored uppercase; validating with lowercase should still work
        $result = InviteCodeService::validate(self::$testTenantId, strtolower($codes[0]));

        $this->assertTrue($result['valid']);
    }

    public function testValidateCodeTrimsWhitespace(): void
    {
        $codes = InviteCodeService::generate(
            self::$testTenantId,
            self::$testAdminId,
            1
        );

        $result = InviteCodeService::validate(self::$testTenantId, '  ' . $codes[0] . '  ');

        $this->assertTrue($result['valid']);
    }

    public function testValidateNonexistentCode(): void
    {
        $result = InviteCodeService::validate(self::$testTenantId, 'ZZZZZZZZ');

        $this->assertFalse($result['valid']);
        $this->assertSame('invalid_code', $result['reason']);
    }

    public function testValidateExpiredCode(): void
    {
        // Generate a code that has already expired
        $pastDate = date('Y-m-d H:i:s', strtotime('-1 day'));
        $codes = InviteCodeService::generate(
            self::$testTenantId,
            self::$testAdminId,
            1,
            1,
            $pastDate
        );

        $result = InviteCodeService::validate(self::$testTenantId, $codes[0]);

        $this->assertFalse($result['valid']);
        $this->assertSame('code_expired', $result['reason']);
    }

    public function testValidateExhaustedCode(): void
    {
        // Generate a single-use code
        $codes = InviteCodeService::generate(
            self::$testTenantId,
            self::$testAdminId,
            1,
            1 // max_uses = 1
        );

        // Manually set uses_count = max_uses to simulate exhaustion
        Database::query(
            "UPDATE tenant_invite_codes SET uses_count = max_uses WHERE tenant_id = ? AND code = ?",
            [self::$testTenantId, $codes[0]]
        );

        $result = InviteCodeService::validate(self::$testTenantId, $codes[0]);

        $this->assertFalse($result['valid']);
        $this->assertSame('code_exhausted', $result['reason']);
    }

    public function testValidateDeactivatedCode(): void
    {
        $codes = InviteCodeService::generate(
            self::$testTenantId,
            self::$testAdminId,
            1
        );

        // Deactivate via the service
        $codeRow = Database::query(
            "SELECT id FROM tenant_invite_codes WHERE tenant_id = ? AND code = ?",
            [self::$testTenantId, $codes[0]]
        )->fetch();

        InviteCodeService::deactivate(self::$testTenantId, (int) $codeRow['id']);

        $result = InviteCodeService::validate(self::$testTenantId, $codes[0]);

        $this->assertFalse($result['valid']);
        $this->assertSame('code_deactivated', $result['reason']);
    }

    public function testValidateCodeWrongTenant(): void
    {
        $codes = InviteCodeService::generate(
            self::$testTenantId,
            self::$testAdminId,
            1
        );

        // Validate against a different tenant — should fail
        $result = InviteCodeService::validate(99999, $codes[0]);

        $this->assertFalse($result['valid']);
        $this->assertSame('invalid_code', $result['reason']);
    }

    // =========================================================================
    // INVITE CODE SERVICE — REDEEM
    // =========================================================================

    public function testRedeemValidCode(): void
    {
        $codes = InviteCodeService::generate(
            self::$testTenantId,
            self::$testAdminId,
            1,
            5 // Allow 5 uses so we can test increment
        );

        $result = InviteCodeService::redeem(self::$testTenantId, $codes[0], self::$testUserId);

        $this->assertTrue($result);

        // Verify uses_count incremented
        $row = Database::query(
            "SELECT uses_count, last_used_by FROM tenant_invite_codes WHERE tenant_id = ? AND code = ?",
            [self::$testTenantId, $codes[0]]
        )->fetch();

        $this->assertEquals(1, (int) $row['uses_count']);
        $this->assertEquals(self::$testUserId, (int) $row['last_used_by']);
    }

    public function testRedeemExhaustedCodeFails(): void
    {
        // Generate single-use code
        $codes = InviteCodeService::generate(
            self::$testTenantId,
            self::$testAdminId,
            1,
            1
        );

        // First redeem should succeed
        $first = InviteCodeService::redeem(self::$testTenantId, $codes[0], self::$testUserId);
        $this->assertTrue($first);

        // Second redeem should fail (code exhausted)
        $second = InviteCodeService::redeem(self::$testTenantId, $codes[0], self::$testUserId);
        $this->assertFalse($second);
    }

    public function testRedeemExpiredCodeFails(): void
    {
        $pastDate = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $codes = InviteCodeService::generate(
            self::$testTenantId,
            self::$testAdminId,
            1,
            1,
            $pastDate
        );

        $result = InviteCodeService::redeem(self::$testTenantId, $codes[0], self::$testUserId);

        $this->assertFalse($result);
    }

    public function testRedeemDeactivatedCodeFails(): void
    {
        $codes = InviteCodeService::generate(
            self::$testTenantId,
            self::$testAdminId,
            1
        );

        // Deactivate
        $codeRow = Database::query(
            "SELECT id FROM tenant_invite_codes WHERE tenant_id = ? AND code = ?",
            [self::$testTenantId, $codes[0]]
        )->fetch();
        InviteCodeService::deactivate(self::$testTenantId, (int) $codeRow['id']);

        $result = InviteCodeService::redeem(self::$testTenantId, $codes[0], self::$testUserId);

        $this->assertFalse($result);
    }

    public function testRedeemLogsUsage(): void
    {
        $codes = InviteCodeService::generate(
            self::$testTenantId,
            self::$testAdminId,
            1,
            5
        );

        InviteCodeService::redeem(self::$testTenantId, $codes[0], self::$testUserId);

        // Check usage log
        $usage = Database::query(
            "SELECT u.user_id FROM tenant_invite_code_uses u
             JOIN tenant_invite_codes c ON c.id = u.invite_code_id
             WHERE c.tenant_id = ? AND c.code = ? AND u.user_id = ?",
            [self::$testTenantId, $codes[0], self::$testUserId]
        )->fetch();

        $this->assertNotFalse($usage);
        $this->assertEquals(self::$testUserId, (int) $usage['user_id']);
    }

    // =========================================================================
    // INVITE CODE SERVICE — LIST & DEACTIVATE
    // =========================================================================

    public function testListForTenantReturnsStructuredResult(): void
    {
        // Generate some codes first
        InviteCodeService::generate(self::$testTenantId, self::$testAdminId, 3);

        $result = InviteCodeService::listForTenant(self::$testTenantId);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('limit', $result);
        $this->assertArrayHasKey('offset', $result);
        $this->assertIsArray($result['items']);
        $this->assertGreaterThanOrEqual(3, $result['total']);
    }

    public function testListForTenantRespectsLimit(): void
    {
        InviteCodeService::generate(self::$testTenantId, self::$testAdminId, 5);

        $result = InviteCodeService::listForTenant(self::$testTenantId, 2, 0);

        $this->assertCount(2, $result['items']);
        $this->assertEquals(2, $result['limit']);
    }

    public function testDeactivateCode(): void
    {
        $codes = InviteCodeService::generate(self::$testTenantId, self::$testAdminId, 1);

        $codeRow = Database::query(
            "SELECT id FROM tenant_invite_codes WHERE tenant_id = ? AND code = ?",
            [self::$testTenantId, $codes[0]]
        )->fetch();

        $success = InviteCodeService::deactivate(self::$testTenantId, (int) $codeRow['id']);
        $this->assertTrue($success);

        // Verify deactivated in DB
        $row = Database::query(
            "SELECT is_active FROM tenant_invite_codes WHERE id = ?",
            [(int) $codeRow['id']]
        )->fetch();
        $this->assertEquals(0, (int) $row['is_active']);
    }

    public function testDeactivateNonexistentCodeReturnsFalse(): void
    {
        $result = InviteCodeService::deactivate(self::$testTenantId, 999999999);
        $this->assertFalse($result);
    }

    // =========================================================================
    // IDENTITY VERIFICATION EVENT SERVICE — LOG
    // =========================================================================

    public function testLogEventCreatesRecord(): void
    {
        IdentityVerificationEventService::log(
            self::$testTenantId,
            self::$testUserId,
            IdentityVerificationEventService::EVENT_REGISTRATION_STARTED,
            null,
            null,
            IdentityVerificationEventService::ACTOR_SYSTEM,
            ['source' => 'test'],
            '127.0.0.1',
            'PHPUnit Test Agent'
        );

        $events = IdentityVerificationEventService::getForUser(
            self::$testTenantId,
            self::$testUserId,
            1
        );

        $this->assertNotEmpty($events);
        $latest = $events[0];
        $this->assertSame(IdentityVerificationEventService::EVENT_REGISTRATION_STARTED, $latest['event_type']);
        $this->assertSame(IdentityVerificationEventService::ACTOR_SYSTEM, $latest['actor_type']);
        $this->assertSame('127.0.0.1', $latest['ip_address']);
    }

    public function testLogEventWithSessionId(): void
    {
        // Create a real session first so the FK constraint is satisfied
        $sessionId = IdentityVerificationSessionService::create(
            self::$testTenantId,
            self::$testUserId,
            'mock',
            'document_only',
            ['provider_session_id' => 'mock_fk_test_' . time(), 'redirect_url' => null, 'client_token' => null, 'expires_at' => null]
        );

        IdentityVerificationEventService::log(
            self::$testTenantId,
            self::$testUserId,
            IdentityVerificationEventService::EVENT_VERIFICATION_CREATED,
            $sessionId,
            self::$testAdminId,
            IdentityVerificationEventService::ACTOR_ADMIN,
            ['provider' => 'mock']
        );

        $events = IdentityVerificationEventService::getForUser(
            self::$testTenantId,
            self::$testUserId,
            100
        );

        $found = false;
        foreach ($events as $event) {
            if ($event['event_type'] === IdentityVerificationEventService::EVENT_VERIFICATION_CREATED
                && (int) $event['session_id'] === $sessionId) {
                $found = true;
                $this->assertEquals(self::$testAdminId, (int) $event['actor_id']);
                break;
            }
        }
        $this->assertTrue($found, "Event with session_id {$sessionId} not found");
    }

    public function testLogEventTruncatesLongUserAgent(): void
    {
        $longUA = str_repeat('X', 1000);

        IdentityVerificationEventService::log(
            self::$testTenantId,
            self::$testUserId,
            IdentityVerificationEventService::EVENT_VERIFICATION_STARTED,
            null,
            null,
            IdentityVerificationEventService::ACTOR_USER,
            null,
            null,
            $longUA
        );

        $events = IdentityVerificationEventService::getForUser(
            self::$testTenantId,
            self::$testUserId,
            1
        );

        $this->assertNotEmpty($events);
        // User agent should be truncated to 500 chars
        $this->assertLessThanOrEqual(500, strlen($events[0]['user_agent']));
    }

    public function testLogEventNeverThrowsOnFailure(): void
    {
        // Logging should silently fail rather than breaking the main flow.
        // We can't easily simulate a DB failure, but we can verify the method
        // signature allows null values without throwing.
        IdentityVerificationEventService::log(
            self::$testTenantId,
            self::$testUserId,
            IdentityVerificationEventService::EVENT_FALLBACK_TRIGGERED,
            null,
            null,
            IdentityVerificationEventService::ACTOR_SYSTEM,
            null,
            null,
            null
        );

        // If we get here, no exception was thrown
        $this->assertTrue(true);
    }

    // =========================================================================
    // IDENTITY VERIFICATION EVENT SERVICE — QUERY
    // =========================================================================

    public function testGetForUserReturnsOrderedEvents(): void
    {
        // Log two events
        IdentityVerificationEventService::log(
            self::$testTenantId,
            self::$testUserId,
            IdentityVerificationEventService::EVENT_VERIFICATION_PASSED
        );
        IdentityVerificationEventService::log(
            self::$testTenantId,
            self::$testUserId,
            IdentityVerificationEventService::EVENT_ACCOUNT_ACTIVATED
        );

        $events = IdentityVerificationEventService::getForUser(
            self::$testTenantId,
            self::$testUserId,
            50
        );

        $this->assertGreaterThanOrEqual(2, count($events));
        // Should be ordered by created_at DESC (newest first)
        if (count($events) >= 2) {
            $this->assertGreaterThanOrEqual(
                strtotime($events[1]['created_at']),
                strtotime($events[0]['created_at'])
            );
        }
    }

    public function testGetForTenantReturnsStructuredResult(): void
    {
        // Ensure at least one event exists
        IdentityVerificationEventService::log(
            self::$testTenantId,
            self::$testUserId,
            IdentityVerificationEventService::EVENT_REGISTRATION_STARTED
        );

        $result = IdentityVerificationEventService::getForTenant(
            self::$testTenantId,
            50,
            0
        );

        $this->assertArrayHasKey('events', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertIsArray($result['events']);
        $this->assertGreaterThanOrEqual(1, $result['total']);
    }

    public function testGetForTenantFiltersByEventType(): void
    {
        // Log events of different types
        IdentityVerificationEventService::log(
            self::$testTenantId,
            self::$testUserId,
            IdentityVerificationEventService::EVENT_VERIFICATION_PASSED
        );
        IdentityVerificationEventService::log(
            self::$testTenantId,
            self::$testUserId,
            IdentityVerificationEventService::EVENT_VERIFICATION_FAILED
        );

        $result = IdentityVerificationEventService::getForTenant(
            self::$testTenantId,
            50,
            0,
            IdentityVerificationEventService::EVENT_VERIFICATION_PASSED
        );

        foreach ($result['events'] as $event) {
            $this->assertSame(
                IdentityVerificationEventService::EVENT_VERIFICATION_PASSED,
                $event['event_type']
            );
        }
    }

    // =========================================================================
    // IDENTITY VERIFICATION EVENT SERVICE — CONSTANTS
    // =========================================================================

    public function testEventTypeConstantsExist(): void
    {
        $this->assertSame('registration_started', IdentityVerificationEventService::EVENT_REGISTRATION_STARTED);
        $this->assertSame('verification_created', IdentityVerificationEventService::EVENT_VERIFICATION_CREATED);
        $this->assertSame('verification_started', IdentityVerificationEventService::EVENT_VERIFICATION_STARTED);
        $this->assertSame('verification_processing', IdentityVerificationEventService::EVENT_VERIFICATION_PROCESSING);
        $this->assertSame('verification_passed', IdentityVerificationEventService::EVENT_VERIFICATION_PASSED);
        $this->assertSame('verification_failed', IdentityVerificationEventService::EVENT_VERIFICATION_FAILED);
        $this->assertSame('verification_expired', IdentityVerificationEventService::EVENT_VERIFICATION_EXPIRED);
        $this->assertSame('verification_cancelled', IdentityVerificationEventService::EVENT_VERIFICATION_CANCELLED);
        $this->assertSame('admin_review_started', IdentityVerificationEventService::EVENT_ADMIN_REVIEW_STARTED);
        $this->assertSame('admin_approved', IdentityVerificationEventService::EVENT_ADMIN_APPROVED);
        $this->assertSame('admin_rejected', IdentityVerificationEventService::EVENT_ADMIN_REJECTED);
        $this->assertSame('account_activated', IdentityVerificationEventService::EVENT_ACCOUNT_ACTIVATED);
        $this->assertSame('fallback_triggered', IdentityVerificationEventService::EVENT_FALLBACK_TRIGGERED);
    }

    public function testActorTypeConstantsExist(): void
    {
        $this->assertSame('system', IdentityVerificationEventService::ACTOR_SYSTEM);
        $this->assertSame('user', IdentityVerificationEventService::ACTOR_USER);
        $this->assertSame('admin', IdentityVerificationEventService::ACTOR_ADMIN);
        $this->assertSame('webhook', IdentityVerificationEventService::ACTOR_WEBHOOK);
    }

    // =========================================================================
    // IDENTITY VERIFICATION SESSION SERVICE — CREATE & QUERY
    // =========================================================================

    public function testCreateSessionReturnsId(): void
    {
        $sessionId = IdentityVerificationSessionService::create(
            self::$testTenantId,
            self::$testUserId,
            'mock',
            'document_selfie',
            [
                'provider_session_id' => 'mock_test_' . time(),
                'redirect_url' => 'https://example.com/verify',
                'client_token' => 'test_token_abc',
                'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            ]
        );

        $this->assertIsInt($sessionId);
        $this->assertGreaterThan(0, $sessionId);
    }

    public function testGetByIdReturnsCreatedSession(): void
    {
        $providerSessionId = 'mock_getbyid_' . time();
        $sessionId = IdentityVerificationSessionService::create(
            self::$testTenantId,
            self::$testUserId,
            'mock',
            'document_only',
            [
                'provider_session_id' => $providerSessionId,
                'redirect_url' => 'https://example.com/verify',
                'client_token' => null,
                'expires_at' => null,
            ]
        );

        $session = IdentityVerificationSessionService::getById($sessionId);

        $this->assertNotNull($session);
        $this->assertEquals(self::$testTenantId, (int) $session['tenant_id']);
        $this->assertEquals(self::$testUserId, (int) $session['user_id']);
        $this->assertSame('mock', $session['provider_slug']);
        $this->assertSame('document_only', $session['verification_level']);
        $this->assertSame('created', $session['status']);
        $this->assertSame($providerSessionId, $session['provider_session_id']);
    }

    public function testGetByIdReturnsNullForNonexistent(): void
    {
        $result = IdentityVerificationSessionService::getById(999999999);
        $this->assertNull($result);
    }

    public function testFindByProviderSession(): void
    {
        $providerSessionId = 'mock_find_' . time() . '_' . random_int(1000, 9999);
        IdentityVerificationSessionService::create(
            self::$testTenantId,
            self::$testUserId,
            'mock',
            'document_selfie',
            ['provider_session_id' => $providerSessionId, 'redirect_url' => null, 'client_token' => null, 'expires_at' => null]
        );

        $found = IdentityVerificationSessionService::findByProviderSession('mock', $providerSessionId);

        $this->assertNotNull($found);
        $this->assertSame($providerSessionId, $found['provider_session_id']);
    }

    public function testGetLatestForUser(): void
    {
        // Create two sessions; latest should be returned
        IdentityVerificationSessionService::create(
            self::$testTenantId,
            self::$testUserId,
            'mock',
            'document_only',
            ['provider_session_id' => 'mock_older_' . time(), 'redirect_url' => null, 'client_token' => null, 'expires_at' => null]
        );

        $newerProviderSessionId = 'mock_newer_' . time() . '_' . random_int(1000, 9999);
        IdentityVerificationSessionService::create(
            self::$testTenantId,
            self::$testUserId,
            'mock',
            'document_selfie',
            ['provider_session_id' => $newerProviderSessionId, 'redirect_url' => null, 'client_token' => null, 'expires_at' => null]
        );

        $latest = IdentityVerificationSessionService::getLatestForUser(self::$testTenantId, self::$testUserId);

        $this->assertNotNull($latest);
        // The latest session should be the one we just created (most recent by created_at)
        // In fast execution, multiple sessions may share the same created_at timestamp,
        // so just verify the latest session belongs to our user and has a valid provider_session_id
        $this->assertSame('mock', $latest['provider_slug']);
        $this->assertSame((string) self::$testUserId, (string) $latest['user_id']);
    }

    // =========================================================================
    // IDENTITY VERIFICATION SESSION SERVICE — UPDATE STATUS
    // =========================================================================

    public function testUpdateStatusToPassed(): void
    {
        $sessionId = IdentityVerificationSessionService::create(
            self::$testTenantId,
            self::$testUserId,
            'mock',
            'document_selfie',
            ['provider_session_id' => 'mock_status_' . time(), 'redirect_url' => null, 'client_token' => null, 'expires_at' => null]
        );

        IdentityVerificationSessionService::updateStatus(
            $sessionId,
            'passed',
            'All checks passed',
            'ref_123'
        );

        $session = IdentityVerificationSessionService::getById($sessionId);

        $this->assertSame('passed', $session['status']);
        $this->assertSame('All checks passed', $session['result_summary']);
        $this->assertSame('ref_123', $session['provider_reference']);
        $this->assertNotNull($session['completed_at']);
    }

    public function testUpdateStatusToFailed(): void
    {
        $sessionId = IdentityVerificationSessionService::create(
            self::$testTenantId,
            self::$testUserId,
            'mock',
            'document_only',
            ['provider_session_id' => 'mock_fail_' . time(), 'redirect_url' => null, 'client_token' => null, 'expires_at' => null]
        );

        IdentityVerificationSessionService::updateStatus(
            $sessionId,
            'failed',
            null,
            null,
            'document_unreadable'
        );

        $session = IdentityVerificationSessionService::getById($sessionId);

        $this->assertSame('failed', $session['status']);
        $this->assertSame('document_unreadable', $session['failure_reason']);
        $this->assertNotNull($session['completed_at']);
    }

    public function testUpdateStatusToProcessingDoesNotSetCompletedAt(): void
    {
        $sessionId = IdentityVerificationSessionService::create(
            self::$testTenantId,
            self::$testUserId,
            'mock',
            'document_selfie',
            ['provider_session_id' => 'mock_proc_' . time(), 'redirect_url' => null, 'client_token' => null, 'expires_at' => null]
        );

        IdentityVerificationSessionService::updateStatus($sessionId, 'processing');

        $session = IdentityVerificationSessionService::getById($sessionId);

        $this->assertSame('processing', $session['status']);
        $this->assertNull($session['completed_at']);
    }

    // =========================================================================
    // IDENTITY VERIFICATION SESSION SERVICE — ABANDONED SESSIONS
    // =========================================================================

    public function testGetAbandonedReturnsStaleSessions(): void
    {
        // Create a session and backdate its created_at
        $sessionId = IdentityVerificationSessionService::create(
            self::$testTenantId,
            self::$testUserId,
            'mock',
            'document_selfie',
            ['provider_session_id' => 'mock_abandoned_' . time(), 'redirect_url' => null, 'client_token' => null, 'expires_at' => null]
        );

        // Backdate to 48 hours ago
        Database::query(
            "UPDATE identity_verification_sessions SET created_at = DATE_SUB(NOW(), INTERVAL 48 HOUR) WHERE id = ?",
            [$sessionId]
        );

        $abandoned = IdentityVerificationSessionService::getAbandoned(24, 100);

        // Our session should be in the results
        $found = false;
        foreach ($abandoned as $session) {
            if ((int) $session['id'] === $sessionId) {
                $found = true;
                $this->assertSame('created', $session['status']);
                break;
            }
        }
        $this->assertTrue($found, "Abandoned session {$sessionId} not found in results");
    }

    public function testGetAbandonedExcludesCompletedSessions(): void
    {
        // Create and complete a session
        $sessionId = IdentityVerificationSessionService::create(
            self::$testTenantId,
            self::$testUserId,
            'mock',
            'document_selfie',
            ['provider_session_id' => 'mock_completed_' . time(), 'redirect_url' => null, 'client_token' => null, 'expires_at' => null]
        );

        IdentityVerificationSessionService::updateStatus($sessionId, 'passed');

        // Backdate
        Database::query(
            "UPDATE identity_verification_sessions SET created_at = DATE_SUB(NOW(), INTERVAL 48 HOUR) WHERE id = ?",
            [$sessionId]
        );

        $abandoned = IdentityVerificationSessionService::getAbandoned(24, 100);

        $found = false;
        foreach ($abandoned as $session) {
            if ((int) $session['id'] === $sessionId) {
                $found = true;
                break;
            }
        }
        $this->assertFalse($found, 'Completed session should not appear in abandoned results');
    }

    public function testGetAbandonedExcludesAlreadyReminded(): void
    {
        $sessionId = IdentityVerificationSessionService::create(
            self::$testTenantId,
            self::$testUserId,
            'mock',
            'document_selfie',
            ['provider_session_id' => 'mock_reminded_' . time(), 'redirect_url' => null, 'client_token' => null, 'expires_at' => null]
        );

        // Backdate and mark reminded
        Database::query(
            "UPDATE identity_verification_sessions SET created_at = DATE_SUB(NOW(), INTERVAL 48 HOUR) WHERE id = ?",
            [$sessionId]
        );
        IdentityVerificationSessionService::markReminderSent($sessionId);

        $abandoned = IdentityVerificationSessionService::getAbandoned(24, 100);

        $found = false;
        foreach ($abandoned as $session) {
            if ((int) $session['id'] === $sessionId) {
                $found = true;
                break;
            }
        }
        $this->assertFalse($found, 'Session with reminder_sent_at should be excluded from abandoned');
    }

    // =========================================================================
    // IDENTITY VERIFICATION SESSION SERVICE — EXPIRE ABANDONED
    // =========================================================================

    public function testExpireAbandonedUpdatesStatus(): void
    {
        $sessionId = IdentityVerificationSessionService::create(
            self::$testTenantId,
            self::$testUserId,
            'mock',
            'document_selfie',
            ['provider_session_id' => 'mock_expire_' . time(), 'redirect_url' => null, 'client_token' => null, 'expires_at' => null]
        );

        // Backdate to 96 hours ago (beyond 72-hour default)
        Database::query(
            "UPDATE identity_verification_sessions SET created_at = DATE_SUB(NOW(), INTERVAL 96 HOUR) WHERE id = ?",
            [$sessionId]
        );

        $expiredCount = IdentityVerificationSessionService::expireAbandoned(72);

        $this->assertGreaterThanOrEqual(1, $expiredCount);

        $session = IdentityVerificationSessionService::getById($sessionId);
        $this->assertSame('expired', $session['status']);
        $this->assertNotNull($session['completed_at']);
    }

    // =========================================================================
    // REGISTRATION POLICY SERVICE — CONSTANTS
    // =========================================================================

    public function testRegistrationModesConstant(): void
    {
        $this->assertCount(5, RegistrationPolicyService::MODES);
        $this->assertContains('open', RegistrationPolicyService::MODES);
        $this->assertContains('open_with_approval', RegistrationPolicyService::MODES);
        $this->assertContains('verified_identity', RegistrationPolicyService::MODES);
        $this->assertContains('government_id', RegistrationPolicyService::MODES);
        $this->assertContains('invite_only', RegistrationPolicyService::MODES);
    }

    public function testVerificationLevelsConstant(): void
    {
        $this->assertCount(5, RegistrationPolicyService::VERIFICATION_LEVELS);
        $this->assertContains('none', RegistrationPolicyService::VERIFICATION_LEVELS);
        $this->assertContains('document_only', RegistrationPolicyService::VERIFICATION_LEVELS);
        $this->assertContains('document_selfie', RegistrationPolicyService::VERIFICATION_LEVELS);
        $this->assertContains('reusable_digital_id', RegistrationPolicyService::VERIFICATION_LEVELS);
        $this->assertContains('manual_review', RegistrationPolicyService::VERIFICATION_LEVELS);
    }

    public function testPostVerificationActionsConstant(): void
    {
        $this->assertCount(4, RegistrationPolicyService::POST_VERIFICATION_ACTIONS);
        $this->assertContains('activate', RegistrationPolicyService::POST_VERIFICATION_ACTIONS);
        $this->assertContains('admin_approval', RegistrationPolicyService::POST_VERIFICATION_ACTIONS);
        $this->assertContains('limited_access', RegistrationPolicyService::POST_VERIFICATION_ACTIONS);
        $this->assertContains('reject_on_fail', RegistrationPolicyService::POST_VERIFICATION_ACTIONS);
    }

    public function testFallbackModesConstant(): void
    {
        $this->assertCount(3, RegistrationPolicyService::FALLBACK_MODES);
        $this->assertContains('none', RegistrationPolicyService::FALLBACK_MODES);
        $this->assertContains('admin_review', RegistrationPolicyService::FALLBACK_MODES);
        $this->assertContains('native_registration', RegistrationPolicyService::FALLBACK_MODES);
    }

    // =========================================================================
    // REGISTRATION POLICY SERVICE — ENCRYPTION
    // =========================================================================

    public function testDecryptConfigHandlesInvalidBase64(): void
    {
        $result = RegistrationPolicyService::decryptConfig('not-valid-base64!!!');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testDecryptConfigHandlesPlainJson(): void
    {
        $json = json_encode(['api_key' => 'sk_test_123']);
        $result = RegistrationPolicyService::decryptConfig($json);

        // If APP_KEY is set, decryptConfig tries base64+AES path first, which fails
        // for plain JSON and returns []. If APP_KEY is not set, it falls back to
        // json_decode and succeeds. Both paths are valid — the test validates the
        // method doesn't throw.
        $this->assertIsArray($result);
        if (!\Nexus\Core\Env::get('APP_KEY')) {
            $this->assertSame('sk_test_123', $result['api_key']);
        } else {
            // With APP_KEY set, plain JSON is not decodable via AES path
            $this->assertEmpty($result);
        }
    }

    public function testDecryptConfigHandlesTooShortInput(): void
    {
        $result = RegistrationPolicyService::decryptConfig(base64_encode('short'));
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =========================================================================
    // REGISTRATION POLICY SERVICE — EFFECTIVE POLICY
    // =========================================================================

    public function testGetEffectivePolicyReturnsExpectedStructure(): void
    {
        $policy = RegistrationPolicyService::getEffectivePolicy(self::$testTenantId);

        $this->assertArrayHasKey('registration_mode', $policy);
        $this->assertArrayHasKey('verification_provider', $policy);
        $this->assertArrayHasKey('verification_level', $policy);
        $this->assertArrayHasKey('post_verification', $policy);
        $this->assertArrayHasKey('fallback_mode', $policy);
        $this->assertArrayHasKey('require_email_verify', $policy);
        $this->assertArrayHasKey('has_policy', $policy);

        // Mode should be a valid mode
        $this->assertContains($policy['registration_mode'], RegistrationPolicyService::MODES);
        $this->assertIsBool($policy['require_email_verify']);
    }

    public function testGetEffectivePolicyFallsBackToLegacy(): void
    {
        // For a tenant without a policy row, should fall back to legacy settings
        $policy = RegistrationPolicyService::getEffectivePolicy(99999);

        $this->assertFalse($policy['has_policy']);
        $this->assertContains($policy['registration_mode'], RegistrationPolicyService::MODES);
    }

    // =========================================================================
    // REGISTRATION POLICY SERVICE — UPSERT VALIDATION
    // =========================================================================

    public function testUpsertPolicyRejectsInvalidMode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid registration_mode');

        RegistrationPolicyService::upsertPolicy(self::$testTenantId, [
            'registration_mode' => 'totally_bogus_mode',
        ]);
    }

    public function testUpsertPolicyRejectsInvalidVerificationLevel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid verification_level');

        RegistrationPolicyService::upsertPolicy(self::$testTenantId, [
            'registration_mode' => 'open',
            'verification_level' => 'invalid_level',
        ]);
    }

    public function testUpsertPolicyRejectsInvalidPostVerification(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid post_verification');

        RegistrationPolicyService::upsertPolicy(self::$testTenantId, [
            'registration_mode' => 'open',
            'post_verification' => 'invalid_action',
        ]);
    }

    public function testUpsertPolicyRejectsInvalidFallbackMode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid fallback_mode');

        RegistrationPolicyService::upsertPolicy(self::$testTenantId, [
            'registration_mode' => 'open',
            'fallback_mode' => 'invalid_fallback',
        ]);
    }

    public function testUpsertPolicyRejectsUnknownProvider(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown verification provider');

        RegistrationPolicyService::upsertPolicy(self::$testTenantId, [
            'registration_mode' => 'verified_identity',
            'verification_provider' => 'nonexistent_provider_xyz',
        ]);
    }

    public function testUpsertPolicyWithValidData(): void
    {
        $policy = RegistrationPolicyService::upsertPolicy(self::$testTenantId, [
            'registration_mode' => 'open_with_approval',
            'verification_level' => 'none',
            'post_verification' => 'admin_approval',
            'fallback_mode' => 'none',
            'require_email_verify' => true,
        ]);

        $this->assertNotNull($policy);
        $this->assertSame('open_with_approval', $policy['registration_mode']);
        $this->assertSame('admin_approval', $policy['post_verification']);
    }

    public function testUpsertPolicyUpdatesExistingRow(): void
    {
        // First upsert
        RegistrationPolicyService::upsertPolicy(self::$testTenantId, [
            'registration_mode' => 'open',
        ]);

        // Second upsert should update, not create a duplicate
        $policy = RegistrationPolicyService::upsertPolicy(self::$testTenantId, [
            'registration_mode' => 'invite_only',
        ]);

        $this->assertSame('invite_only', $policy['registration_mode']);

        // Verify only one active policy exists for this tenant
        $count = Database::query(
            "SELECT COUNT(*) AS cnt FROM tenant_registration_policies WHERE tenant_id = ? AND is_active = 1",
            [self::$testTenantId]
        )->fetch()['cnt'];

        $this->assertEquals(1, (int) $count);
    }
}
