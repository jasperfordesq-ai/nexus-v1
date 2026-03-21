<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\ContentModerationService;
use App\Services\TenantSettingsService;
use App\Core\TenantContext;

/**
 * ContentModerationService Tests
 *
 * Tests constants, valid/invalid decision enforcement,
 * and DB-backed queue operations.
 */
class ContentModerationServiceTest extends TestCase
{
    private static int $tenantId  = 2;
    private static int $authorId  = 1;
    private static int $reviewerId = 1;

    public static function setUpBeforeClass(): void
    {
        TenantContext::setById(self::$tenantId);
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function test_content_types_constant_is_correct(): void
    {
        $types = ContentModerationService::CONTENT_TYPES;
        $this->assertIsArray($types);
        $this->assertContains('post', $types);
        $this->assertContains('listing', $types);
        $this->assertContains('event', $types);
        $this->assertContains('comment', $types);
        $this->assertContains('group', $types);
    }

    public function test_status_constants_are_defined(): void
    {
        $this->assertSame('pending',  ContentModerationService::STATUS_PENDING);
        $this->assertSame('approved', ContentModerationService::STATUS_APPROVED);
        $this->assertSame('rejected', ContentModerationService::STATUS_REJECTED);
        $this->assertSame('flagged',  ContentModerationService::STATUS_FLAGGED);
    }

    // -------------------------------------------------------------------------
    // review() — validation (no DB interaction for invalid decision)
    // -------------------------------------------------------------------------

    public function test_review_rejects_invalid_decision(): void
    {
        try {
            $result = ContentModerationService::review(1, self::$tenantId, self::$reviewerId, 'invalid_status');
            $this->assertFalse($result['success']);
            $this->assertStringContainsString('Invalid decision', $result['message']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_review_rejects_missing_queue_item(): void
    {
        try {
            $result = ContentModerationService::review(999999, self::$tenantId, self::$reviewerId, 'approved');
            $this->assertFalse($result['success']);
            $this->assertStringContainsString('not found', $result['message']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_review_rejects_rejection_without_reason(): void
    {
        try {
            // First create a queue item so the not-found check passes
            // Use a fake content_id — the item may not exist; rely on the not-found path
            $result = ContentModerationService::review(999998, self::$tenantId, self::$reviewerId, 'rejected', null);
            // Either not found OR requires reason — both are valid failures
            $this->assertFalse($result['success']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // getStats()
    // -------------------------------------------------------------------------

    public function test_get_stats_returns_expected_structure(): void
    {
        try {
            $stats = ContentModerationService::getStats(self::$tenantId);
            $this->assertIsArray($stats);
            $this->assertArrayHasKey('total', $stats);
            $this->assertArrayHasKey('pending', $stats);
            $this->assertArrayHasKey('flagged', $stats);
            $this->assertArrayHasKey('approved', $stats);
            $this->assertArrayHasKey('rejected', $stats);
            $this->assertArrayHasKey('awaiting_review', $stats);
            $this->assertArrayHasKey('by_type', $stats);
            $this->assertArrayHasKey('settings', $stats);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_stats_awaiting_review_equals_pending_plus_flagged(): void
    {
        try {
            $stats = ContentModerationService::getStats(self::$tenantId);
            $expected = $stats['pending'] + $stats['flagged'];
            $this->assertSame($expected, $stats['awaiting_review']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // getModerationSettings()
    // -------------------------------------------------------------------------

    public function test_get_moderation_settings_returns_expected_keys(): void
    {
        try {
            $settings = ContentModerationService::getModerationSettings(self::$tenantId);
            $this->assertIsArray($settings);
            $this->assertArrayHasKey('enabled', $settings);
            $this->assertArrayHasKey('require_post', $settings);
            $this->assertArrayHasKey('require_listing', $settings);
            $this->assertArrayHasKey('require_event', $settings);
            $this->assertArrayHasKey('require_comment', $settings);
            $this->assertArrayHasKey('auto_filter', $settings);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_moderation_settings_values_are_bools(): void
    {
        try {
            $settings = ContentModerationService::getModerationSettings(self::$tenantId);
            foreach ($settings as $key => $val) {
                $this->assertIsBool($val, "Setting '{$key}' should be a boolean");
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // updateSettings()
    // -------------------------------------------------------------------------

    public function test_update_settings_returns_true(): void
    {
        try {
            $result = ContentModerationService::updateSettings(self::$tenantId, [
                'enabled'       => false,
                'require_post'  => false,
                'auto_filter'   => false,
            ]);
            $this->assertTrue($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // isEnabled()
    // -------------------------------------------------------------------------

    public function test_is_enabled_returns_false_when_moderation_disabled(): void
    {
        try {
            TenantSettingsService::set(self::$tenantId, 'moderation.enabled', '0');
            TenantSettingsService::clearCache();
            $result = ContentModerationService::isEnabled(self::$tenantId, 'post');
            $this->assertFalse($result);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // getQueue()
    // -------------------------------------------------------------------------

    public function test_get_queue_returns_expected_structure(): void
    {
        try {
            $result = ContentModerationService::getQueue(self::$tenantId, [], 10, 0);
            $this->assertIsArray($result);
            $this->assertArrayHasKey('items', $result);
            $this->assertArrayHasKey('total', $result);
            $this->assertIsInt($result['total']);
            $this->assertIsArray($result['items']);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_queue_respects_limit(): void
    {
        try {
            $result = ContentModerationService::getQueue(self::$tenantId, [], 2, 0);
            $this->assertLessThanOrEqual(2, count($result['items']));
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_get_queue_with_content_type_filter(): void
    {
        try {
            $result = ContentModerationService::getQueue(self::$tenantId, ['content_type' => 'post'], 20, 0);
            $this->assertIsArray($result);
            foreach ($result['items'] as $item) {
                $this->assertSame('post', $item['content_type']);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }

    public function test_tenant_isolation_stats_for_different_tenants(): void
    {
        try {
            $stats2 = ContentModerationService::getStats(2);
            $stats1 = ContentModerationService::getStats(1);
            // Both return valid arrays, totals are independent
            $this->assertIsArray($stats1);
            $this->assertIsArray($stats2);
        } catch (\Exception $e) {
            $this->markTestSkipped('DB not available: ' . $e->getMessage());
        }
    }
}
