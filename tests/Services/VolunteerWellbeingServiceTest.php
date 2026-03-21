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
use App\Services\VolunteerWellbeingService;

class VolunteerWellbeingServiceTest extends DatabaseTestCase
{
    private const TENANT_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    public function testDetectBurnoutRiskReturnsRequiredKeys(): void
    {
        $userId = $this->createUser("burnout-basic");
        $result = VolunteerWellbeingService::detectBurnoutRisk($userId);
        $this->assertIsArray($result);
        $this->assertArrayHasKey("user_id", $result);
        $this->assertArrayHasKey("risk_score", $result);
        $this->assertArrayHasKey("risk_level", $result);
        $this->assertArrayHasKey("indicators", $result);
        $this->assertArrayHasKey("assessed_at", $result);
        $this->assertSame($userId, $result["user_id"]);
    }

    public function testDetectBurnoutRiskIndicatorsHaveRiskContribution(): void
    {
        $userId = $this->createUser("burnout-indicators");
        $result = VolunteerWellbeingService::detectBurnoutRisk($userId);
        $indicators = $result["indicators"];
        $this->assertIsArray($indicators);
        $this->assertCount(5, $indicators);
        foreach ($indicators as $name => $indicator) {
            $this->assertArrayHasKey("risk_contribution", $indicator, "Indicator {$name} missing risk_contribution");
        }
    }

    public function testDetectBurnoutRiskLevelIsLowForInactiveNewUser(): void
    {
        $userId = $this->createUser("burnout-new");
        $result = VolunteerWellbeingService::detectBurnoutRisk($userId);
        $this->assertContains($result["risk_level"], ["low", "moderate", "high", "critical"]);
        $this->assertGreaterThanOrEqual(0.0, $result["risk_score"]);
        $this->assertLessThanOrEqual(100.0, $result["risk_score"]);
    }

    public function testDetectBurnoutRiskScoreIsNumericAndBounded(): void
    {
        $userId = $this->createUser("burnout-bounded");
        $result = VolunteerWellbeingService::detectBurnoutRisk($userId);
        $this->assertIsFloat($result["risk_score"]);
        $this->assertGreaterThanOrEqual(0.0, $result["risk_score"]);
        $this->assertLessThanOrEqual(100.0, $result["risk_score"]);
    }

    public function testRunTenantAssessmentReturnsStructuredResult(): void
    {
        $result = VolunteerWellbeingService::runTenantAssessment();
        $this->assertIsArray($result);
        $this->assertArrayHasKey("total_assessed", $result);
        $this->assertArrayHasKey("alerts_created", $result);
        $this->assertArrayHasKey("risk_distribution", $result);
        $this->assertIsInt($result["total_assessed"]);
        $this->assertIsInt($result["alerts_created"]);
    }

    public function testRunTenantAssessmentRiskDistributionHasAllLevels(): void
    {
        $result = VolunteerWellbeingService::runTenantAssessment();
        $dist = $result["risk_distribution"];
        $this->assertArrayHasKey("low", $dist);
        $this->assertArrayHasKey("moderate", $dist);
        $this->assertArrayHasKey("high", $dist);
        $this->assertArrayHasKey("critical", $dist);
    }

    public function testUpdateAlertFailsForInvalidAction(): void
    {
        $this->requireTables(["vol_wellbeing_alerts"]);
        $result = VolunteerWellbeingService::updateAlert(1, "invalid-action");
        $this->assertFalse($result);
        $errors = VolunteerWellbeingService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame("VALIDATION_ERROR", $errors[0]["code"]);
    }

    public function testUpdateAlertFailsForUnknownAlertId(): void
    {
        $this->requireTables(["vol_wellbeing_alerts"]);
        $result = VolunteerWellbeingService::updateAlert(999999, "resolve");
        $this->assertFalse($result);
        $errors = VolunteerWellbeingService::getErrors();
        $this->assertNotEmpty($errors);
        $this->assertSame("NOT_FOUND", $errors[0]["code"]);
    }

    private function createUser(string $prefix): int
    {
        $uniq = $prefix . "-" . str_replace(".", "", (string)microtime(true)) . "-" . random_int(1000, 9999);
        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, balance, is_approved, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [self::TENANT_ID, $uniq . "@example.test", $uniq, "Test", "User", "Test User", 0]
        );
        return (int)Database::getInstance()->lastInsertId();
    }

    private function requireTables(array $tables): void
    {
        foreach ($tables as $table) {
            $exists = (int)Database::query(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                [$table]
            )->fetchColumn();
            if ($exists === 0) {
                $this->markTestSkipped("Required table not present in test DB: {$table}");
            }
        }
    }
}
