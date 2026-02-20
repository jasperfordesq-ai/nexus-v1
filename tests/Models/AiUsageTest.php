<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Models;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\AiUsage;

/**
 * AiUsage Model Tests
 *
 * Tests usage logging, user retrieval, stats, provider/feature
 * aggregation, daily trends, cost calculation, and monthly cost.
 */
class AiUsageTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);

        $timestamp = time();
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 100, 1, NOW())",
            [self::$testTenantId, "ai_usage_test_{$timestamp}@test.com", "ai_usage_test_{$timestamp}", 'AiUsage', 'Tester', 'AiUsage Tester']
        );
        self::$testUserId = (int)Database::getInstance()->lastInsertId();
    }

    public static function tearDownAfterClass(): void
    {
        try {
            if (self::$testUserId) {
                Database::query("DELETE FROM ai_usage WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            }
        } catch (\Exception $e) {
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // Log Tests
    // ==========================================

    public function testLogReturnsId(): void
    {
        $id = AiUsage::log(self::$testUserId, 'openai', 'chat', [
            'tokens_input' => 100,
            'tokens_output' => 200,
            'cost_usd' => 0.005,
        ]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testLogWithMinimalData(): void
    {
        $id = AiUsage::log(self::$testUserId, 'gemini', 'content_gen');

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    // ==========================================
    // GetByUserId Tests
    // ==========================================

    public function testGetByUserIdReturnsArray(): void
    {
        $usage = AiUsage::getByUserId(self::$testUserId);
        $this->assertIsArray($usage);
        $this->assertNotEmpty($usage);
    }

    public function testGetByUserIdReturnsEmptyForNonExistent(): void
    {
        $usage = AiUsage::getByUserId(999999999);
        $this->assertIsArray($usage);
        $this->assertEmpty($usage);
    }

    // ==========================================
    // GetStats Tests
    // ==========================================

    public function testGetStatsReturnsStructure(): void
    {
        $stats = AiUsage::getStats('month');
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_requests', $stats);
        $this->assertArrayHasKey('unique_users', $stats);
        $this->assertArrayHasKey('total_tokens_input', $stats);
        $this->assertArrayHasKey('total_tokens_output', $stats);
        $this->assertArrayHasKey('total_tokens', $stats);
        $this->assertArrayHasKey('total_cost', $stats);
    }

    public function testGetStatsWithDifferentPeriods(): void
    {
        foreach (['day', 'week', 'month', 'year'] as $period) {
            $stats = AiUsage::getStats($period);
            $this->assertIsArray($stats);
            $this->assertArrayHasKey('total_requests', $stats);
        }
    }

    // ==========================================
    // GetByProvider Tests
    // ==========================================

    public function testGetByProviderReturnsArray(): void
    {
        $byProvider = AiUsage::getByProvider();
        $this->assertIsArray($byProvider);
    }

    // ==========================================
    // GetByFeature Tests
    // ==========================================

    public function testGetByFeatureReturnsArray(): void
    {
        $byFeature = AiUsage::getByFeature();
        $this->assertIsArray($byFeature);
    }

    // ==========================================
    // GetDailyTrend Tests
    // ==========================================

    public function testGetDailyTrendReturnsArray(): void
    {
        $trend = AiUsage::getDailyTrend(30);
        $this->assertIsArray($trend);
    }

    // ==========================================
    // GetCurrentMonthCost Tests
    // ==========================================

    public function testGetCurrentMonthCostReturnsFloat(): void
    {
        $cost = AiUsage::getCurrentMonthCost();
        $this->assertIsFloat($cost);
        $this->assertGreaterThanOrEqual(0.0, $cost);
    }

    // ==========================================
    // CalculateCost Tests (Pure Function)
    // ==========================================

    public function testCalculateCostForOpenAi(): void
    {
        $cost = AiUsage::calculateCost('openai', 'gpt-4-turbo', 1000, 1000);
        $this->assertIsFloat($cost);
        // GPT-4 Turbo: $0.01/1K input + $0.03/1K output = $0.04
        $this->assertEqualsWithDelta(0.04, $cost, 0.001);
    }

    public function testCalculateCostForGeminiIsZero(): void
    {
        $cost = AiUsage::calculateCost('gemini', 'gemini-pro', 1000, 1000);
        $this->assertEquals(0.0, $cost);
    }

    public function testCalculateCostForOllamaIsZero(): void
    {
        $cost = AiUsage::calculateCost('ollama', 'llama2', 1000, 1000);
        $this->assertEquals(0.0, $cost);
    }

    public function testCalculateCostForUnknownProviderIsZero(): void
    {
        $cost = AiUsage::calculateCost('unknown', 'model', 1000, 1000);
        $this->assertEquals(0.0, $cost);
    }

    public function testCalculateCostForZeroTokensIsZero(): void
    {
        $cost = AiUsage::calculateCost('openai', 'gpt-4-turbo', 0, 0);
        $this->assertEquals(0.0, $cost);
    }
}
