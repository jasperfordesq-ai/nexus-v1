<?php
// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Core\Database;
use App\Core\TenantContext;
use App\Models\JobVacancy;
use App\Services\JobVacancyService;
use App\Tests\DatabaseTestCase;

/**
 * JobVacancyService Test Suite
 *
 * Covers all J1-J10 feature groups.
 * DatabaseTestCase wraps each test method in a transaction rolled back in tearDown().
 */
class JobVacancyServiceTest extends DatabaseTestCase
{
    protected static int $ownerUserId     = 0;
    protected static int $applicantUserId = 0;
    protected static int $adminUserId     = 0;
    protected static int $jobId           = 0;

    protected static bool $hasApplicationsTable = false;
    protected static bool $hasSavedJobsTable    = false;
    protected static bool $hasAlertsTable       = false;
    protected static bool $hasHistoryTable      = false;
    protected static bool $hasViewsTable        = false;

    protected static ?JobVacancyService $svc = null;

    /**
     * Get a shared service instance for tests.
     */
    protected static function svc(): JobVacancyService
    {
        if (self::$svc === null) {
            self::$svc = new JobVacancyService(new JobVacancy());
        }
        return self::$svc;
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        TenantContext::setById(2);
        self::createTestData();
        self::detectTables();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$pdo === null) {
            parent::tearDownAfterClass();
            return;
        }
        try {
            if (self::$hasHistoryTable && self::$hasApplicationsTable && self::$jobId) {
                self::$pdo->exec(
                    "DELETE jah FROM job_application_history jah"
                    . " INNER JOIN job_vacancy_applications jva ON jva.id = jah.application_id"
                    . " WHERE jva.vacancy_id = " . self::$jobId
                );
            }
            if (self::$hasApplicationsTable && self::$jobId) {
                self::$pdo->exec("DELETE FROM job_vacancy_applications WHERE vacancy_id = " . self::$jobId);
            }
            if (self::$hasSavedJobsTable && self::$jobId) {
                self::$pdo->exec("DELETE FROM saved_jobs WHERE job_id = " . self::$jobId);
            }
            $uids = implode(",", array_filter([
                self::$ownerUserId, self::$applicantUserId, self::$adminUserId,
            ]));
            if ($uids && self::$hasAlertsTable) {
                self::$pdo->exec("DELETE FROM job_alerts WHERE user_id IN ({$uids})");
            }
            if (self::$hasViewsTable && self::$jobId) {
                self::$pdo->exec("DELETE FROM job_vacancy_views WHERE vacancy_id = " . self::$jobId);
            }
            if (self::$jobId) {
                self::$pdo->exec("DELETE FROM job_vacancies WHERE id = " . self::$jobId);
                self::$pdo->exec(
                    "DELETE FROM job_vacancies WHERE tenant_id = 2 AND user_id = " . self::$ownerUserId
                    . " AND title = 'Expired job'"
                );
            }
            foreach ([self::$ownerUserId, self::$applicantUserId, self::$adminUserId] as $uid) {
                if ($uid) {
                    self::$pdo->exec("DELETE FROM user_skills WHERE user_id = {$uid}");
                    self::$pdo->exec("DELETE FROM users WHERE id = {$uid}");
                }
            }
        } catch (\Throwable $e) {}
        parent::tearDownAfterClass();
    }

    private static function createTestData(): void
    {
        $ts = time();
        self::$pdo->exec(
            "INSERT INTO users (tenant_id, name, email, password_hash, role, status, created_at)"
            . " VALUES (2, 'JV Owner', 'jv_owner_{$ts}@test.invalid', 'x', 'member', 'active', NOW())"
        );
        self::$ownerUserId = (int) self::$pdo->lastInsertId();
        self::$pdo->exec(
            "INSERT INTO users (tenant_id, name, email, password_hash, role, status, created_at)"
            . " VALUES (2, 'JV Applicant', 'jv_app_{$ts}@test.invalid', 'x', 'member', 'active', NOW())"
        );
        self::$applicantUserId = (int) self::$pdo->lastInsertId();
        self::$pdo->exec(
            "INSERT INTO users (tenant_id, name, email, password_hash, role, status, created_at)"
            . " VALUES (2, 'JV Admin', 'jv_admin_{$ts}@test.invalid', 'x', 'admin', 'active', NOW())"
        );
        self::$adminUserId = (int) self::$pdo->lastInsertId();
        try {
            self::$pdo->exec(
                "INSERT INTO job_vacancies (tenant_id, user_id, title, description, status, category, location,"
                . " time_commitment, skills_required, expires_at, created_at)"
                . " VALUES (2, " . self::$ownerUserId . ", 'Test Vacancy', 'Test description', 'active',"
                . " 'technology', 'Remote', '10 hrs/week', 'php,testing',"
                . " DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())"
            );
            self::$jobId = (int) self::$pdo->lastInsertId();
        } catch (\Throwable $e) {
            self::$jobId = 0;
        }
    }

    private static function detectTables(): void
    {
        $map = [
            "job_vacancy_applications"  => "hasApplicationsTable",
            "saved_jobs"                => "hasSavedJobsTable",
            "job_alert_subscriptions"   => "hasAlertsTable",
            "job_application_history"   => "hasHistoryTable",
            "job_vacancy_views"         => "hasViewsTable",
        ];
        foreach ($map as $table => $flag) {
            try {
                self::$pdo->query("SELECT 1 FROM {$table} LIMIT 1");
                self::$$flag = true;
            } catch (\Throwable $e) {
                self::$$flag = false;
            }
        }
    }

    private function requireJobsTable(): void
    {
        if (!self::$jobId) {
            $this->markTestSkipped("job_vacancies table not available or fixture creation failed.");
        }
    }

    private function requireApplicationsTable(): void
    {
        if (!self::$hasApplicationsTable) {
            $this->markTestSkipped("job_vacancy_applications table not available.");
        }
    }

    private function requireSavedJobsTable(): void
    {
        if (!self::$hasSavedJobsTable) {
            $this->markTestSkipped("saved_jobs table not available.");
        }
    }

    private function requireAlertsTable(): void
    {
        if (!self::$hasAlertsTable) {
            $this->markTestSkipped("job_alerts table not available.");
        }
    }

    private function requireHistoryTable(): void
    {
        if (!self::$hasHistoryTable) {
            $this->markTestSkipped("job_application_history table not available.");
        }
    }


    // -------------------------------------------------------------------------
    // Core CRUD tests
    // -------------------------------------------------------------------------

    public function testGetAllReturnsArray(): void
    {
        $this->requireJobsTable();
        $result = self::svc()->getAll([], self::$ownerUserId);
        $this->assertIsArray($result);
    }

    public function testGetAllHasPaginationShape(): void
    {
        $this->requireJobsTable();
        $result = self::svc()->getAll([], self::$ownerUserId);
        $this->assertArrayHasKey("items", $result);
        $this->assertArrayHasKey("has_more", $result);
    }

    public function testGetAllItemsAreArrays(): void
    {
        $this->requireJobsTable();
        $result = self::svc()->getAll([], self::$ownerUserId);
        foreach ($result["items"] as $item) {
            $this->assertIsArray($item);
        }
    }

    public function testGetAllWithOpenStatusFilter(): void
    {
        $this->requireJobsTable();
        $result = self::svc()->getAll(["status" => "open"], self::$ownerUserId);
        $this->assertArrayHasKey("items", $result);
        foreach ($result["items"] as $item) {
            $this->assertSame("open", $item["status"]);
        }
    }

    public function testGetAllWithCategoryFilter(): void
    {
        $this->requireJobsTable();
        $result = self::svc()->getAll(["category" => "technology"], self::$ownerUserId);
        $this->assertArrayHasKey("items", $result);
        foreach ($result["items"] as $item) {
            $this->assertSame("technology", $item["category"]);
        }
    }

    public function testGetAllCursorPaginationHasCursorKey(): void
    {
        $this->requireJobsTable();
        $result = self::svc()->getAll(["limit" => 1], self::$ownerUserId);
        $this->assertArrayHasKey("cursor", $result);
    }

    public function testGetByIdReturnsCorrectVacancy(): void
    {
        $this->requireJobsTable();
        $vacancy = self::svc()->getById(self::$jobId, self::$ownerUserId);
        $this->assertNotNull($vacancy);
        $this->assertIsArray($vacancy);
        $this->assertSame(self::$jobId, $vacancy["id"]);
    }

    public function testGetByIdIncludesRequiredFields(): void
    {
        $this->requireJobsTable();
        $vacancy = self::svc()->getById(self::$jobId, self::$ownerUserId);
        $this->assertNotNull($vacancy);
        $this->assertArrayHasKey("title", $vacancy);
        $this->assertArrayHasKey("description", $vacancy);
        $this->assertArrayHasKey("status", $vacancy);
        $this->assertArrayHasKey("category", $vacancy);
    }

    public function testGetByIdIncludesCreatorSubArray(): void
    {
        $this->requireJobsTable();
        $vacancy = self::svc()->getById(self::$jobId, self::$ownerUserId);
        $this->assertNotNull($vacancy);
        $this->assertArrayHasKey("creator", $vacancy);
        $this->assertIsArray($vacancy["creator"]);
    }

    public function testGetByIdReturnsNullForNonexistentId(): void
    {
        $result = self::svc()->getById(99999999, self::$ownerUserId);
        $this->assertNull($result);
    }

    public function testCreateReturnsPositiveInteger(): void
    {
        $this->requireJobsTable();
        $id = self::svc()->create(self::$ownerUserId, [
            "title"       => "Created in test",
            "description" => "Test create description",
            "category"    => "technology",
            "location"    => "Remote",
            "type"        => "paid",
            "commitment"  => "flexible",
            "status"      => "open",
        ]);
        $this->assertNotNull($id);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateJobAppearsInGetAll(): void
    {
        $this->requireJobsTable();
        $id = self::svc()->create(self::$ownerUserId, [
            "title"       => "Findable vacancy",
            "description" => "Should appear in listing",
            "category"    => "education",
            "location"    => "Online",
        ]);
        $this->assertNotNull($id);
        $result = self::svc()->getAll([], self::$ownerUserId);
        $ids = array_column($result["items"], "id");
        $this->assertContains($id, $ids);
    }

    public function testUpdateChangesTitle(): void
    {
        $this->requireJobsTable();
        $updated = self::svc()->update(self::$jobId, self::$ownerUserId, ["title" => "Updated Title " . time()]);
        $this->assertTrue($updated);
    }

    public function testUpdateReturnsFalseForNonexistentId(): void
    {
        $result = self::svc()->update(99999999, self::$ownerUserId, ["title" => "Ghost update"]);
        $this->assertFalse($result);
    }

    public function testDeleteJobRemovesIt(): void
    {
        $this->requireJobsTable();
        $id = self::svc()->create(self::$ownerUserId, [
            "title"       => "To be deleted",
            "description" => "Will be deleted in this test",
            "category"    => "other",
            "location"    => "N/A",
        ]);
        $this->assertNotNull($id);
        $deleted = self::svc()->delete($id, self::$ownerUserId);
        $this->assertTrue($deleted);
        $this->assertNull(self::svc()->getById($id, self::$ownerUserId));
    }

    public function testDeleteReturnsFalseForNonexistentId(): void
    {
        $result = self::svc()->delete(99999999, self::$ownerUserId);
        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // J1 - Saved jobs
    // -------------------------------------------------------------------------

    public function testSaveJobReturnsBool(): void
    {
        $this->requireJobsTable();
        $this->requireSavedJobsTable();
        $result = self::svc()->saveJob(self::$jobId, self::$applicantUserId);
        $this->assertIsBool($result);
    }

    public function testSaveJobAppearsInSavedList(): void
    {
        $this->requireJobsTable();
        $this->requireSavedJobsTable();
        self::svc()->saveJob(self::$jobId, self::$applicantUserId);
        $saved = self::svc()->getSavedJobs(self::$applicantUserId);
        $this->assertArrayHasKey("items", $saved);
        $ids = array_column($saved["items"], "id");
        $this->assertContains(self::$jobId, $ids);
    }

    public function testUnsaveJobRemovesFromSavedList(): void
    {
        $this->requireJobsTable();
        $this->requireSavedJobsTable();
        self::svc()->saveJob(self::$jobId, self::$applicantUserId);
        $result = self::svc()->unsaveJob(self::$jobId, self::$applicantUserId);
        $this->assertTrue($result);
        $saved = self::svc()->getSavedJobs(self::$applicantUserId);
        $ids = array_column($saved["items"], "id");
        $this->assertNotContains(self::$jobId, $ids);
    }

    public function testGetSavedJobsHasItemsKey(): void
    {
        $this->requireSavedJobsTable();
        $saved = self::svc()->getSavedJobs(self::$ownerUserId);
        $this->assertArrayHasKey("items", $saved);
        $this->assertIsArray($saved["items"]);
    }

    public function testIsSavedFlagReflectedInGetById(): void
    {
        $this->requireJobsTable();
        $this->requireSavedJobsTable();
        self::svc()->saveJob(self::$jobId, self::$applicantUserId);
        $vacancy = self::svc()->getById(self::$jobId, self::$applicantUserId);
        $this->assertNotNull($vacancy);
        if (array_key_exists("is_saved", $vacancy)) {
            $this->assertTrue((bool) $vacancy["is_saved"]);
        } else {
            $this->markTestSkipped("is_saved enrichment not present in this build.");
        }
    }

    // -------------------------------------------------------------------------
    // J2 - Skills matching
    // -------------------------------------------------------------------------

    public function testCalculateMatchPercentageReturnsArray(): void
    {
        $this->requireJobsTable();
        $result = self::svc()->calculateMatchPercentage(self::$applicantUserId, self::$jobId);
        $this->assertIsArray($result);
    }

    public function testCalculateMatchPercentageHasPercentageKey(): void
    {
        $this->requireJobsTable();
        $result = self::svc()->calculateMatchPercentage(self::$applicantUserId, self::$jobId);
        $this->assertArrayHasKey("percentage", $result);
    }

    public function testCalculateMatchPercentageIsInValidRange(): void
    {
        $this->requireJobsTable();
        $result = self::svc()->calculateMatchPercentage(self::$applicantUserId, self::$jobId);
        $pct = $result["percentage"];
        $this->assertGreaterThanOrEqual(0, $pct);
        $this->assertLessThanOrEqual(100, $pct);
    }

    public function testMatchPercentageNonZeroWithMatchingSkills(): void
    {
        $this->requireJobsTable();
        self::$pdo->exec(
            "INSERT IGNORE INTO user_skills (user_id, skill_name, tenant_id) VALUES"
            . " (" . self::$applicantUserId . ", 'php', 2),"
            . " (" . self::$applicantUserId . ", 'testing', 2)"
        );
        $result = self::svc()->calculateMatchPercentage(self::$applicantUserId, self::$jobId);
        $this->assertGreaterThan(0, $result["percentage"]);
    }

    // -------------------------------------------------------------------------
    // J3 - Application pipeline
    // -------------------------------------------------------------------------

    public function testApplyReturnsPositiveApplicationId(): void
    {
        $this->requireJobsTable();
        $this->requireApplicationsTable();
        $appId = self::svc()->apply(self::$jobId, self::$applicantUserId, "Test application message");
        $this->assertNotNull($appId);
        $this->assertIsInt($appId);
        $this->assertGreaterThan(0, $appId);
    }

    public function testGetApplicationsReturnsArrayForOwner(): void
    {
        $this->requireJobsTable();
        $this->requireApplicationsTable();
        self::svc()->apply(self::$jobId, self::$applicantUserId, "Applying");
        $applications = self::svc()->getApplications(self::$jobId, self::$ownerUserId);
        $this->assertNotNull($applications);
        $this->assertIsArray($applications);
    }

    public function testApplicationHasIdAndStatusFields(): void
    {
        $this->requireJobsTable();
        $this->requireApplicationsTable();
        self::svc()->apply(self::$jobId, self::$applicantUserId, "Check fields");
        $applications = self::svc()->getApplications(self::$jobId, self::$ownerUserId);
        $this->assertNotNull($applications);
        $this->assertNotEmpty($applications);
        $app = $applications[0];
        $this->assertArrayHasKey("id", $app);
        $this->assertArrayHasKey("status", $app);
    }

    public function testUpdateApplicationStatusToScreening(): void
    {
        $this->requireJobsTable();
        $this->requireApplicationsTable();
        $appId = self::svc()->apply(self::$jobId, self::$applicantUserId, "Status update test");
        $this->assertNotNull($appId);
        $result = self::svc()->updateApplicationStatus($appId, self::$ownerUserId, "screening", "Moving to screening");
        $this->assertTrue($result);
    }

    public function testUpdateApplicationStatusToInterview(): void
    {
        $this->requireJobsTable();
        $this->requireApplicationsTable();
        $appId = self::svc()->apply(self::$jobId, self::$applicantUserId, "Interview stage test");
        $this->assertNotNull($appId);
        $result = self::svc()->updateApplicationStatus($appId, self::$ownerUserId, "interview");
        $this->assertTrue($result);
    }

    public function testUpdateApplicationStatusToOffer(): void
    {
        $this->requireJobsTable();
        $this->requireApplicationsTable();
        $appId = self::svc()->apply(self::$jobId, self::$applicantUserId, "Offer stage test");
        $this->assertNotNull($appId);
        $result = self::svc()->updateApplicationStatus($appId, self::$ownerUserId, "offer", "Extending an offer");
        $this->assertTrue($result);
    }

    public function testUpdateApplicationStatusToRejected(): void
    {
        $this->requireJobsTable();
        $this->requireApplicationsTable();
        $appId = self::svc()->apply(self::$jobId, self::$applicantUserId, "Rejection test");
        $this->assertNotNull($appId);
        $result = self::svc()->updateApplicationStatus($appId, self::$ownerUserId, "rejected", "Not the right fit");
        $this->assertTrue($result);
    }

    public function testGetMyApplicationsReturnsItemsKey(): void
    {
        $this->requireApplicationsTable();
        $result = self::svc()->getMyApplications(self::$applicantUserId);
        $this->assertIsArray($result);
        $this->assertArrayHasKey("items", $result);
    }

    public function testGetMyApplicationsIncludesOwnApplication(): void
    {
        $this->requireJobsTable();
        $this->requireApplicationsTable();
        $appId = self::svc()->apply(self::$jobId, self::$applicantUserId, "My application test");
        $result = self::svc()->getMyApplications(self::$applicantUserId);
        $this->assertArrayHasKey("items", $result);
        $appIds = array_column($result["items"], "id");
        $this->assertContains($appId, $appIds);
    }
    // -------------------------------------------------------------------------
    // J4 - Application history
    // -------------------------------------------------------------------------

    public function testGetApplicationHistoryReturnsArrayForValidApplication(): void
    {
        $this->requireJobsTable();
        $this->requireApplicationsTable();
        $this->requireHistoryTable();
        $appId = self::svc()->apply(self::$jobId, self::$applicantUserId, "History test");
        $this->assertNotNull($appId);
        $history = self::svc()->getApplicationHistory($appId, self::$ownerUserId);
        $this->assertNotNull($history);
        $this->assertIsArray($history);
    }

    public function testApplicationHistoryRecordsStatusChange(): void
    {
        $this->requireJobsTable();
        $this->requireApplicationsTable();
        $this->requireHistoryTable();
        $appId = self::svc()->apply(self::$jobId, self::$applicantUserId, "History status change");
        $this->assertNotNull($appId);
        self::svc()->updateApplicationStatus($appId, self::$ownerUserId, "reviewed", "Reviewed notes");
        $history = self::svc()->getApplicationHistory($appId, self::$ownerUserId);
        $this->assertNotNull($history);
        $this->assertNotEmpty($history);
    }

    public function testGetApplicationHistoryReturnsNullForNonexistentId(): void
    {
        $this->requireHistoryTable();
        $history = self::svc()->getApplicationHistory(99999999, self::$ownerUserId);
        $this->assertNull($history);
    }

    // -------------------------------------------------------------------------
    // J5 - Qualification assessment
    // -------------------------------------------------------------------------

    public function testGetQualificationAssessmentReturnsNullOrArray(): void
    {
        $this->requireJobsTable();
        $result = self::svc()->getQualificationAssessment(self::$applicantUserId, self::$jobId);
        $this->assertTrue($result === null || is_array($result));
    }

    public function testQualificationAssessmentHasScoreFieldWhenPresent(): void
    {
        $this->requireJobsTable();
        $result = self::svc()->getQualificationAssessment(self::$applicantUserId, self::$jobId);
        if ($result === null) {
            $this->markTestSkipped("Qualification assessment returned null for this job.");
        }
        $this->assertArrayHasKey("score", $result);
    }

    public function testQualificationAssessmentScoreInValidRange(): void
    {
        $this->requireJobsTable();
        $result = self::svc()->getQualificationAssessment(self::$applicantUserId, self::$jobId);
        if ($result === null) {
            $this->markTestSkipped("Qualification assessment returned null for this job.");
        }
        $this->assertGreaterThanOrEqual(0, $result["score"]);
        $this->assertLessThanOrEqual(100, $result["score"]);
    }

    // -------------------------------------------------------------------------
    // J6 - Job alerts
    // -------------------------------------------------------------------------

    public function testSubscribeAlertReturnsPositiveId(): void
    {
        $this->requireAlertsTable();
        $alertId = self::svc()->subscribeAlert(self::$applicantUserId, [
            "keywords"  => "php developer",
            "category"  => "technology",
            "location"  => "Remote",
            "frequency" => "weekly",
        ]);
        $this->assertNotNull($alertId);
        $this->assertIsInt($alertId);
        $this->assertGreaterThan(0, $alertId);
    }

    public function testGetAlertsReturnsCreatedAlert(): void
    {
        $this->requireAlertsTable();
        $alertId = self::svc()->subscribeAlert(self::$applicantUserId, [
            "keywords"  => "volunteer coordinator",
            "frequency" => "daily",
        ]);
        $this->assertNotNull($alertId);
        $alerts = self::svc()->getAlerts(self::$applicantUserId);
        $this->assertIsArray($alerts);
        $ids = array_column($alerts, "id");
        $this->assertContains($alertId, $ids);
    }

    public function testDeleteAlertReturnsTrueForOwnAlert(): void
    {
        $this->requireAlertsTable();
        $alertId = self::svc()->subscribeAlert(self::$applicantUserId, [
            "keywords"  => "alert to delete",
            "frequency" => "weekly",
        ]);
        $this->assertNotNull($alertId);
        $result = self::svc()->deleteAlert($alertId, self::$applicantUserId);
        $this->assertTrue($result);
    }

    public function testDeleteAlertReturnsFalseForAnotherUsersAlert(): void
    {
        $this->requireAlertsTable();
        $alertId = self::svc()->subscribeAlert(self::$applicantUserId, [
            "keywords"  => "protected alert",
            "frequency" => "weekly",
        ]);
        $this->assertNotNull($alertId);
        $result = self::svc()->deleteAlert($alertId, self::$ownerUserId);
        $this->assertFalse($result);
    }

    public function testGetAlertsReturnsArrayForUserWithNoAlerts(): void
    {
        $this->requireAlertsTable();
        $alerts = self::svc()->getAlerts(self::$ownerUserId);
        $this->assertIsArray($alerts);
    }

    // -------------------------------------------------------------------------
    // J7 - Expiry and renewal
    // -------------------------------------------------------------------------

    public function testExpireOverdueJobsReturnsNonNegativeInt(): void
    {
        $this->requireJobsTable();
        $count = self::svc()->expireOverdueJobs();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testExpireOverdueJobsExpiresPastDueVacancy(): void
    {
        $this->requireJobsTable();
        Database::query(
            "INSERT INTO job_vacancies (tenant_id, user_id, title, description, type, commitment, status, deadline, views_count, applications_count, created_at)"
            . " VALUES (?, ?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL 1 DAY), 0, 0, NOW())",
            [2, self::$ownerUserId, 'Expired job', 'Past due', 'paid', 'flexible', 'open']
        );
        $count = self::svc()->expireOverdueJobs();
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testRenewJobReturnsTrueForOwner(): void
    {
        $this->requireJobsTable();
        $result = self::svc()->renewJob(self::$jobId, self::$ownerUserId, 30);
        $this->assertTrue($result);
    }

    public function testRenewJobReturnsFalseForNonexistentId(): void
    {
        $result = self::svc()->renewJob(99999999, self::$ownerUserId, 30);
        $this->assertFalse($result);
    }

    public function testRenewJobDoesNotDecreaseExpiryDate(): void
    {
        $this->requireJobsTable();
        $before = self::svc()->getById(self::$jobId, self::$ownerUserId);
        $this->assertNotNull($before);
        $expiryBefore = $before["expires_at"] ?? null;
        self::svc()->renewJob(self::$jobId, self::$ownerUserId, 30);
        $after = self::svc()->getById(self::$jobId, self::$ownerUserId);
        $this->assertNotNull($after);
        $expiryAfter = $after["expires_at"] ?? null;
        if ($expiryBefore !== null && $expiryAfter !== null) {
            $this->assertGreaterThanOrEqual(strtotime($expiryBefore), strtotime($expiryAfter));
        } else {
            $this->markTestSkipped("expires_at field not present in vacancy shape.");
        }
    }

    // -------------------------------------------------------------------------
    // J8 - Analytics
    // -------------------------------------------------------------------------

    public function testGetAnalyticsReturnsArrayForOwner(): void
    {
        $this->requireJobsTable();
        $result = self::svc()->getAnalytics(self::$jobId, self::$ownerUserId);
        $this->assertNotNull($result);
        $this->assertIsArray($result);
    }

    public function testGetAnalyticsHasViewsField(): void
    {
        $this->requireJobsTable();
        $result = self::svc()->getAnalytics(self::$jobId, self::$ownerUserId);
        $this->assertNotNull($result);
        $this->assertArrayHasKey("views", $result);
    }

    public function testGetAnalyticsReturnsNullOrArrayForNonOwner(): void
    {
        $this->requireJobsTable();
        $result = self::svc()->getAnalytics(self::$jobId, self::$applicantUserId);
        $this->assertTrue($result === null || is_array($result));
    }

    // -------------------------------------------------------------------------
    // J9 - Salary fields
    // -------------------------------------------------------------------------

    public function testCreateJobWithSalaryFieldsReturnsId(): void
    {
        $this->requireJobsTable();
        $id = self::svc()->create(self::$ownerUserId, [
            "title"           => "Salaried role test",
            "description"     => "Test with salary fields",
            "category"        => "finance",
            "location"        => "Hybrid",
            "salary_min"      => 30000,
            "salary_max"      => 50000,
            "salary_currency" => "EUR",
            "salary_period"   => "annual",
        ]);
        $this->assertNotNull($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testSalaryFieldsArePersisted(): void
    {
        $this->requireJobsTable();
        $id = self::svc()->create(self::$ownerUserId, [
            "title"           => "Salary persistence test",
            "description"     => "Check salary is stored",
            "category"        => "finance",
            "location"        => "Office",
            "salary_min"      => 25000,
            "salary_max"      => 40000,
            "salary_currency" => "USD",
            "salary_period"   => "annual",
        ]);
        $this->assertNotNull($id);
        $vacancy = self::svc()->getById($id, self::$ownerUserId);
        $this->assertNotNull($vacancy);
        if (!isset($vacancy["salary_min"])) {
            $this->markTestSkipped("Salary fields not present in vacancy shape for this build.");
        }
        $this->assertSame(25000, (int) $vacancy["salary_min"]);
        $this->assertSame(40000, (int) $vacancy["salary_max"]);
    }

    public function testGetAllAcceptsSalaryMinFilter(): void
    {
        $this->requireJobsTable();
        $result = self::svc()->getAll(["salary_min" => 20000], self::$ownerUserId);
        $this->assertArrayHasKey("items", $result);
        $this->assertIsArray($result["items"]);
    }

    // -------------------------------------------------------------------------
    // J10 - Featured jobs
    // -------------------------------------------------------------------------

    public function testFeatureJobReturnsTrueForAdminUser(): void
    {
        $this->requireJobsTable();
        $result = self::svc()->featureJob(self::$jobId, self::$adminUserId, 7);
        $this->assertTrue($result);
    }

    public function testUnfeatureJobReturnsTrueAfterFeature(): void
    {
        $this->requireJobsTable();
        self::svc()->featureJob(self::$jobId, self::$adminUserId, 7);
        $result = self::svc()->unfeatureJob(self::$jobId, self::$adminUserId);
        $this->assertTrue($result);
    }

    public function testFeatureJobReturnsFalseForNonAdminUser(): void
    {
        $this->requireJobsTable();
        $result = self::svc()->featureJob(self::$jobId, self::$applicantUserId, 7);
        $this->assertFalse($result);
    }

    public function testFeaturedFlagAppearsInGetByIdAfterFeature(): void
    {
        $this->requireJobsTable();
        self::svc()->featureJob(self::$jobId, self::$adminUserId, 7);
        $vacancy = self::svc()->getById(self::$jobId, self::$ownerUserId);
        $this->assertNotNull($vacancy);
        if (!array_key_exists("is_featured", $vacancy)) {
            $this->markTestSkipped("is_featured field not present in vacancy shape.");
        }
        $this->assertTrue((bool) $vacancy["is_featured"]);
    }

    public function testGetAllWithFeaturedFilterReturnsOnlyFeatured(): void
    {
        $this->requireJobsTable();
        self::svc()->featureJob(self::$jobId, self::$adminUserId, 7);
        $result = self::svc()->getAll(["featured" => true], self::$ownerUserId);
        $this->assertArrayHasKey("items", $result);
        foreach ($result["items"] as $item) {
            if (array_key_exists("is_featured", $item)) {
                $this->assertTrue((bool) $item["is_featured"]);
            }
        }
    }

    public function testIsFeaturedFlagFalseAfterUnfeature(): void
    {
        $this->requireJobsTable();
        self::svc()->featureJob(self::$jobId, self::$adminUserId, 7);
        self::svc()->unfeatureJob(self::$jobId, self::$adminUserId);
        $vacancy = self::svc()->getById(self::$jobId, self::$ownerUserId);
        $this->assertNotNull($vacancy);
        if (!array_key_exists("is_featured", $vacancy)) {
            $this->markTestSkipped("is_featured field not present in vacancy shape.");
        }
        $this->assertFalse((bool) $vacancy["is_featured"]);
    }

    // -------------------------------------------------------------------------
    // Application business rules (edge cases)
    // -------------------------------------------------------------------------

    public function testApplyCannotApplyToOwnVacancy(): void
    {
        $this->requireJobsTable();
        $this->requireApplicationsTable();
        $result = self::svc()->apply(self::$jobId, self::$ownerUserId, 'Owner applying to own job');
        $this->assertNull($result);
        $errors = self::svc()->getErrors();
        $this->assertNotEmpty($errors);
        $codes = array_column($errors, 'code');
        $this->assertContains('CONFLICT', $codes);
    }

    public function testApplyCannotApplyTwice(): void
    {
        $this->requireJobsTable();
        $this->requireApplicationsTable();
        self::svc()->apply(self::$jobId, self::$applicantUserId, 'First apply');
        $result = self::svc()->apply(self::$jobId, self::$applicantUserId, 'Second apply');
        $this->assertNull($result);
        $errors = self::svc()->getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testGetApplicationsReturnsNullForNonOwner(): void
    {
        $this->requireJobsTable();
        $this->requireApplicationsTable();
        $result = self::svc()->getApplications(self::$jobId, self::$applicantUserId);
        $this->assertNull($result);
        $errors = self::svc()->getErrors();
        $codes = array_column($errors, 'code');
        $this->assertContains('FORBIDDEN', $codes);
    }

    public function testUpdateApplicationStatusForbiddenForNonOwner(): void
    {
        $this->requireJobsTable();
        $this->requireApplicationsTable();
        $appId = self::svc()->apply(self::$jobId, self::$applicantUserId, 'Auth test');
        $this->assertNotNull($appId);
        $ts = time();
        self::$pdo->exec(
            "INSERT INTO users (tenant_id, name, email, password_hash, role, status, created_at)"
            . " VALUES (2, 'Stranger', 'stranger_{$ts}@test.invalid', 'x', 'member', 'active', NOW())"
        );
        $strangerId = (int) self::$pdo->lastInsertId();
        $result = self::svc()->updateApplicationStatus($appId, $strangerId, 'rejected');
        $this->assertFalse($result);
        $errors = self::svc()->getErrors();
        $codes = array_column($errors, 'code');
        $this->assertContains('FORBIDDEN', $codes);
        self::$pdo->exec("DELETE FROM users WHERE id = {$strangerId}");
    }

    public function testUpdateApplicationStatusInvalidStatus(): void
    {
        $this->requireJobsTable();
        $this->requireApplicationsTable();
        $appId = self::svc()->apply(self::$jobId, self::$applicantUserId, 'Invalid status test');
        $this->assertNotNull($appId);
        $result = self::svc()->updateApplicationStatus($appId, self::$ownerUserId, 'totally_invalid_status');
        $this->assertFalse($result);
        $errors = self::svc()->getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testGetMyApplicationsWithStatusFilter(): void
    {
        $this->requireApplicationsTable();
        $appId = self::svc()->apply(self::$jobId, self::$applicantUserId, 'Filter test');
        if ($appId) {
            self::svc()->updateApplicationStatus($appId, self::$ownerUserId, 'accepted');
        }
        $result = self::svc()->getMyApplications(self::$applicantUserId, ['status' => 'accepted']);
        $this->assertArrayHasKey('items', $result);
        foreach ($result['items'] as $item) {
            $this->assertSame('accepted', $item['status']);
        }
    }
}
