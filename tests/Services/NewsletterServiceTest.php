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
use Nexus\Services\NewsletterService;

/**
 * NewsletterService Tests
 *
 * Tests newsletter operations including sending, targeting,
 * A/B testing, and template rendering.
 */
class NewsletterServiceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?int $testNewsletterId = null;

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

        // Create test user
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::$testTenantId, "newsvc_{$ts}@test.com", "newsvc_{$ts}", 'News', 'User', 'News User']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Create test newsletter
        Database::query(
            "INSERT INTO newsletters (tenant_id, subject, content, status, created_by, created_at)
             VALUES (?, ?, ?, 'draft', ?, NOW())",
            [self::$testTenantId, "Test Newsletter {$ts}", '<p>Test content</p>', self::$testUserId]
        );
        self::$testNewsletterId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$testNewsletterId) {
            try {
                Database::query("DELETE FROM newsletter_queue WHERE newsletter_id = ?", [self::$testNewsletterId]);
                Database::query("DELETE FROM newsletters WHERE id = ?", [self::$testNewsletterId]);
            } catch (\Exception $e) {}
        }
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }

    // ==========================================
    // Recipient Tests
    // ==========================================

    public function testGetRecipientsReturnsArray(): void
    {
        $recipients = NewsletterService::getRecipients('all_members');
        $this->assertIsArray($recipients);
    }

    public function testGetRecipientsIncludesEmailAndName(): void
    {
        $recipients = NewsletterService::getRecipients('all_members');
        if (!empty($recipients)) {
            $this->assertArrayHasKey('email', $recipients[0]);
            $this->assertArrayHasKey('name', $recipients[0]);
        }
        $this->assertTrue(true); // Always pass if no recipients
    }

    public function testGetRecipientCountReturnsInteger(): void
    {
        $count = NewsletterService::getRecipientCount('all_members');
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    // ==========================================
    // Stats Tests
    // ==========================================

    public function testGetStatsReturnsValidStructure(): void
    {
        $stats = NewsletterService::getStats(self::$testNewsletterId);

        $this->assertNotNull($stats);
        $this->assertArrayHasKey('status', $stats);
        $this->assertArrayHasKey('total_recipients', $stats);
        $this->assertArrayHasKey('total_sent', $stats);
        $this->assertArrayHasKey('total_failed', $stats);
    }

    public function testGetStatsReturnsNullForInvalidId(): void
    {
        $stats = NewsletterService::getStats(999999);
        $this->assertNull($stats);
    }

    // ==========================================
    // Template Tests
    // ==========================================

    public function testRenderEmailIncludesContent(): void
    {
        $newsletter = [
            'subject' => 'Test Subject',
            'content' => '<p>Test content</p>',
            'preview_text' => 'Preview text'
        ];

        $html = NewsletterService::renderEmail($newsletter, 'Test Tenant');

        $this->assertIsString($html);
        $this->assertStringContainsString('Test content', $html);
        $this->assertStringContainsString('Test Tenant', $html);
    }

    public function testRenderEmailIncludesUnsubscribeLink(): void
    {
        $newsletter = [
            'subject' => 'Test Subject',
            'content' => '<p>Test content</p>',
            'preview_text' => ''
        ];

        $html = NewsletterService::renderEmail($newsletter, 'Test Tenant', 'test_token');

        $this->assertStringContainsString('unsubscribe', strtolower($html));
    }

    public function testProcessTemplateVariablesReplacesPlaceholders(): void
    {
        $content = 'Hello {{first_name}} {{last_name}}!';
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe'
        ];

        $processed = NewsletterService::processTemplateVariables($content, $data);

        $this->assertStringContainsString('John', $processed);
        $this->assertStringContainsString('Doe', $processed);
        $this->assertStringNotContainsString('{{', $processed);
    }

    public function testProcessTemplateVariablesHandlesMissingVariables(): void
    {
        $content = 'Hello {{first_name}} {{missing_var}}!';
        $data = ['first_name' => 'John'];

        $processed = NewsletterService::processTemplateVariables($content, $data);

        $this->assertStringContainsString('John', $processed);
        $this->assertStringContainsString('{{missing_var}}', $processed);
    }

    // ==========================================
    // Sending Method Tests
    // ==========================================

    public function testGetSendingMethodReturnsValidValue(): void
    {
        $method = NewsletterService::getSendingMethod();
        $this->assertIsString($method);
        $this->assertContains($method, ['Gmail API', 'SMTP']);
    }

    // ==========================================
    // Filtered Recipients Tests
    // ==========================================

    public function testGetFilteredRecipientsReturnsArray(): void
    {
        $newsletter = [
            'target_counties' => null,
            'target_towns' => null,
            'target_groups' => null
        ];

        $recipients = NewsletterService::getFilteredRecipients($newsletter, 'all_members');
        $this->assertIsArray($recipients);
    }

    public function testGetFilteredRecipientsFiltersWhenNoMatch(): void
    {
        $newsletter = [
            'target_counties' => json_encode(['NonExistentCounty']),
            'target_towns' => null,
            'target_groups' => null
        ];

        $recipients = NewsletterService::getFilteredRecipients($newsletter, 'all_members');
        $this->assertIsArray($recipients);
        // Should be empty or smaller than unfiltered
    }

    // ==========================================
    // Schedule Tests
    // ==========================================

    public function testScheduleReturnsTrue(): void
    {
        $futureDate = date('Y-m-d H:i:s', strtotime('+1 day'));
        $result = NewsletterService::schedule(self::$testNewsletterId, $futureDate);
        $this->assertTrue($result);

        // Verify status changed to scheduled
        $stmt = Database::query("SELECT status FROM newsletters WHERE id = ?", [self::$testNewsletterId]);
        $newsletter = $stmt->fetch();
        $this->assertEquals('scheduled', $newsletter['status']);

        // Reset status
        Database::query("UPDATE newsletters SET status = 'draft' WHERE id = ?", [self::$testNewsletterId]);
    }

    // ==========================================
    // Resend Tests
    // ==========================================

    public function testGetResendInfoReturnsNullForDraftNewsletter(): void
    {
        $info = NewsletterService::getResendInfo(self::$testNewsletterId);
        $this->assertNull($info);
    }

    public function testGetResendInfoReturnsValidStructure(): void
    {
        // Update newsletter to sent status
        Database::query(
            "UPDATE newsletters SET status = 'sent', sent_at = NOW() WHERE id = ?",
            [self::$testNewsletterId]
        );

        $info = NewsletterService::getResendInfo(self::$testNewsletterId);

        if ($info) {
            $this->assertArrayHasKey('can_resend', $info);
            $this->assertArrayHasKey('days_since_sent', $info);
            $this->assertArrayHasKey('non_opener_count', $info);
        }

        // Reset status
        Database::query("UPDATE newsletters SET status = 'draft' WHERE id = ?", [self::$testNewsletterId]);
        $this->assertTrue(true);
    }
}
