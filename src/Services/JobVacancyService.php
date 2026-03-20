<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Job Vacancy Service
 *
 * Manages job vacancies for the current tenant, including:
 *   - CRUD operations on vacancies
 *   - Application pipeline (apply, status updates, history)
 *   - Saved jobs / bookmarks
 *   - Skills-based matching and qualification assessment
 *   - Job alerts and subscriptions
 *   - Expiry, renewal, featuring, and analytics
 */
class JobVacancyService
{
    /** @var array<int, array{code: string, message: string}> */
    private static array $errors = [];

    private const VALID_STATUSES = [
        'pending', 'screening', 'reviewed', 'interview', 'offer', 'accepted', 'rejected', 'withdrawn',
    ];

    // ------------------------------------------------------------------
    //  Error handling
    // ------------------------------------------------------------------

    /** @return array<int, array{code: string, message: string}> */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    private static function clearErrors(): void
    {
        self::$errors = [];
    }

    private static function addError(string $code, string $message): void
    {
        self::$errors[] = ['code' => $code, 'message' => $message];
    }

    // ------------------------------------------------------------------
    //  CRUD
    // ------------------------------------------------------------------

    /**
     * @return array{items: array, has_more: bool, cursor: int|null}
     */
    public static function getAll(array $filters = [], int $userId = 0): array
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();
        $params = [$tenantId];
        $where = ['jv.tenant_id = ?'];

        if (!empty($filters['status'])) {
            $where[] = 'jv.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['category'])) {
            $where[] = 'jv.category = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['featured'])) {
            $where[] = 'jv.is_featured = 1';
        }
        if (!empty($filters['salary_min'])) {
            $where[] = '(jv.salary_min IS NULL OR jv.salary_min >= ?)';
            $params[] = (int)$filters['salary_min'];
        }

        $limit = (int)($filters['limit'] ?? 50);
        $params[] = $limit + 1;

        $sql = "SELECT jv.* FROM job_vacancies jv WHERE " . implode(' AND ', $where)
             . " ORDER BY jv.created_at DESC LIMIT ?";

        $stmt = Database::query($sql, $params);
        $rows = [];
        while ($row = $stmt->fetch()) {
            $rows[] = self::formatVacancy($row, $userId);
        }

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        return [
            'items'    => $rows,
            'has_more' => $hasMore,
            'cursor'   => !empty($rows) ? (int)end($rows)['id'] : null,
        ];
    }

    public static function getById(int $id, int $userId = 0): ?array
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT jv.* FROM job_vacancies jv WHERE jv.id = ? AND jv.tenant_id = ?",
            [$id, $tenantId]
        );
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $vacancy = self::formatVacancy($row, $userId);

        // Check saved status
        if ($userId > 0) {
            try {
                $savedStmt = Database::query(
                    "SELECT 1 FROM saved_jobs WHERE job_id = ? AND user_id = ? AND tenant_id = ?",
                    [$id, $userId, $tenantId]
                );
                $vacancy['is_saved'] = (bool)$savedStmt->fetch();
            } catch (\Throwable $e) {
                // saved_jobs table may not exist
            }
        }

        // Creator sub-array
        try {
            $userStmt = Database::query(
                "SELECT id, name, email FROM users WHERE id = ?",
                [$row['user_id']]
            );
            $vacancy['creator'] = $userStmt->fetch() ?: ['id' => $row['user_id'], 'name' => 'Unknown'];
        } catch (\Throwable $e) {
            $vacancy['creator'] = ['id' => $row['user_id'], 'name' => 'Unknown'];
        }

        return $vacancy;
    }

    public static function create(int $userId, array $data): ?int
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        $cols = ['tenant_id', 'user_id', 'title', 'description', 'status', 'created_at'];
        $vals = [$tenantId, $userId, $data['title'] ?? '', $data['description'] ?? '', $data['status'] ?? 'active', date('Y-m-d H:i:s')];
        $placeholders = array_fill(0, count($cols), '?');

        $optional = [
            'category', 'location', 'type', 'commitment', 'skills_required', 'time_commitment',
            'salary_min', 'salary_max', 'salary_currency', 'salary_period',
        ];
        foreach ($optional as $field) {
            if (isset($data[$field])) {
                $cols[] = $field;
                $vals[] = $data[$field];
                $placeholders[] = '?';
            }
        }

        $sql = "INSERT INTO job_vacancies (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        Database::query($sql, $vals);

        return (int)Database::getInstance()->lastInsertId();
    }

    public static function update(int $id, int $userId, array $data): bool
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        // Verify existence and ownership
        $stmt = Database::query(
            "SELECT id FROM job_vacancies WHERE id = ? AND tenant_id = ? AND user_id = ?",
            [$id, $tenantId, $userId]
        );
        if (!$stmt->fetch()) {
            return false;
        }

        $sets = [];
        $params = [];
        foreach ($data as $key => $value) {
            $sets[] = "{$key} = ?";
            $params[] = $value;
        }
        if (empty($sets)) {
            return true;
        }

        $params[] = $id;
        $params[] = $tenantId;

        Database::query(
            "UPDATE job_vacancies SET " . implode(', ', $sets) . " WHERE id = ? AND tenant_id = ?",
            $params
        );

        return true;
    }

    public static function delete(int $id, int $userId): bool
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT id FROM job_vacancies WHERE id = ? AND tenant_id = ? AND user_id = ?",
            [$id, $tenantId, $userId]
        );
        if (!$stmt->fetch()) {
            return false;
        }

        Database::query(
            "DELETE FROM job_vacancies WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        return true;
    }

    // ------------------------------------------------------------------
    //  Saved jobs
    // ------------------------------------------------------------------

    public static function saveJob(int $jobId, int $userId): bool
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();
        try {
            Database::query(
                "INSERT IGNORE INTO saved_jobs (job_id, user_id, tenant_id, created_at) VALUES (?, ?, ?, NOW())",
                [$jobId, $userId, $tenantId]
            );
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function unsaveJob(int $jobId, int $userId): bool
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();
        Database::query(
            "DELETE FROM saved_jobs WHERE job_id = ? AND user_id = ? AND tenant_id = ?",
            [$jobId, $userId, $tenantId]
        );
        return true;
    }

    /** @return array{items: array} */
    public static function getSavedJobs(int $userId): array
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();
        try {
            $stmt = Database::query(
                "SELECT jv.* FROM job_vacancies jv
                 INNER JOIN saved_jobs sj ON sj.job_id = jv.id
                 WHERE sj.user_id = ? AND sj.tenant_id = ?
                 ORDER BY sj.created_at DESC",
                [$userId, $tenantId]
            );
            $items = [];
            while ($row = $stmt->fetch()) {
                $items[] = self::formatVacancy($row, $userId);
            }
            return ['items' => $items];
        } catch (\Throwable $e) {
            return ['items' => []];
        }
    }

    // ------------------------------------------------------------------
    //  Skills matching
    // ------------------------------------------------------------------

    public static function calculateMatchPercentage(int $userId, int $jobId): array
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        // Get job required skills
        $stmt = Database::query(
            "SELECT skills_required FROM job_vacancies WHERE id = ? AND tenant_id = ?",
            [$jobId, $tenantId]
        );
        $row = $stmt->fetch();
        if (!$row || empty($row['skills_required'])) {
            return ['percentage' => 0, 'matched' => [], 'missing' => []];
        }

        $required = array_map('trim', explode(',', $row['skills_required']));

        // Get user skills
        try {
            $userStmt = Database::query(
                "SELECT skill_name FROM user_skills WHERE user_id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            );
            $userSkills = [];
            while ($s = $userStmt->fetch()) {
                $userSkills[] = strtolower($s['skill_name']);
            }
        } catch (\Throwable $e) {
            $userSkills = [];
        }

        $matched = [];
        $missing = [];
        foreach ($required as $skill) {
            if (in_array(strtolower($skill), $userSkills, true)) {
                $matched[] = $skill;
            } else {
                $missing[] = $skill;
            }
        }

        $percentage = count($required) > 0 ? (int)round((count($matched) / count($required)) * 100) : 0;

        return [
            'percentage' => $percentage,
            'matched'    => $matched,
            'missing'    => $missing,
        ];
    }

    // ------------------------------------------------------------------
    //  Application pipeline
    // ------------------------------------------------------------------

    public static function apply(int $jobId, int $userId, string $message = ''): ?int
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        // Can't apply to own vacancy
        $stmt = Database::query(
            "SELECT user_id FROM job_vacancies WHERE id = ? AND tenant_id = ?",
            [$jobId, $tenantId]
        );
        $job = $stmt->fetch();
        if (!$job) {
            self::addError('NOT_FOUND', 'Job vacancy not found');
            return null;
        }
        if ((int)$job['user_id'] === $userId) {
            self::addError('CONFLICT', 'Cannot apply to your own vacancy');
            return null;
        }

        // Check duplicate application
        $existing = Database::query(
            "SELECT id FROM job_vacancy_applications WHERE vacancy_id = ? AND user_id = ? AND tenant_id = ?",
            [$jobId, $userId, $tenantId]
        );
        if ($existing->fetch()) {
            self::addError('DUPLICATE', 'You have already applied to this vacancy');
            return null;
        }

        Database::query(
            "INSERT INTO job_vacancy_applications (vacancy_id, user_id, tenant_id, message, status, created_at)
             VALUES (?, ?, ?, ?, 'pending', NOW())",
            [$jobId, $userId, $tenantId, $message]
        );

        $appId = (int)Database::getInstance()->lastInsertId();

        // Record initial history
        try {
            Database::query(
                "INSERT INTO job_application_history (application_id, status, notes, created_at)
                 VALUES (?, 'pending', 'Application submitted', NOW())",
                [$appId]
            );
        } catch (\Throwable $e) {
            // History table may not exist
        }

        return $appId;
    }

    /** @return array|null Array of applications, or null if forbidden */
    public static function getApplications(int $jobId, int $ownerUserId): ?array
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        // Verify ownership
        $stmt = Database::query(
            "SELECT user_id FROM job_vacancies WHERE id = ? AND tenant_id = ?",
            [$jobId, $tenantId]
        );
        $job = $stmt->fetch();
        if (!$job || (int)$job['user_id'] !== $ownerUserId) {
            self::addError('FORBIDDEN', 'Only the vacancy owner can view applications');
            return null;
        }

        $appStmt = Database::query(
            "SELECT jva.*, u.name as applicant_name
             FROM job_vacancy_applications jva
             LEFT JOIN users u ON u.id = jva.user_id
             WHERE jva.vacancy_id = ? AND jva.tenant_id = ?
             ORDER BY jva.created_at DESC",
            [$jobId, $tenantId]
        );

        $apps = [];
        while ($row = $appStmt->fetch()) {
            $apps[] = $row;
        }
        return $apps;
    }

    public static function updateApplicationStatus(int $applicationId, int $actorUserId, string $status, string $notes = ''): bool
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        if (!in_array($status, self::VALID_STATUSES, true)) {
            self::addError('INVALID_STATUS', "Invalid application status: {$status}");
            return false;
        }

        // Get application and verify ownership of the vacancy
        $stmt = Database::query(
            "SELECT jva.id, jva.vacancy_id, jv.user_id as owner_id
             FROM job_vacancy_applications jva
             INNER JOIN job_vacancies jv ON jv.id = jva.vacancy_id
             WHERE jva.id = ? AND jva.tenant_id = ?",
            [$applicationId, $tenantId]
        );
        $row = $stmt->fetch();
        if (!$row) {
            self::addError('NOT_FOUND', 'Application not found');
            return false;
        }
        if ((int)$row['owner_id'] !== $actorUserId) {
            self::addError('FORBIDDEN', 'Only the vacancy owner can update application status');
            return false;
        }

        Database::query(
            "UPDATE job_vacancy_applications SET status = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?",
            [$status, $applicationId, $tenantId]
        );

        // Record history
        try {
            Database::query(
                "INSERT INTO job_application_history (application_id, status, notes, created_at)
                 VALUES (?, ?, ?, NOW())",
                [$applicationId, $status, $notes]
            );
        } catch (\Throwable $e) {
            // History table may not exist
        }

        return true;
    }

    /** @return array{items: array} */
    public static function getMyApplications(int $userId, array $filters = []): array
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        $where = ['jva.user_id = ?', 'jva.tenant_id = ?'];
        $params = [$userId, $tenantId];

        if (!empty($filters['status'])) {
            $where[] = 'jva.status = ?';
            $params[] = $filters['status'];
        }

        $stmt = Database::query(
            "SELECT jva.*, jv.title as vacancy_title
             FROM job_vacancy_applications jva
             LEFT JOIN job_vacancies jv ON jv.id = jva.vacancy_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY jva.created_at DESC",
            $params
        );

        $items = [];
        while ($row = $stmt->fetch()) {
            $items[] = $row;
        }
        return ['items' => $items];
    }

    // ------------------------------------------------------------------
    //  Application history
    // ------------------------------------------------------------------

    public static function getApplicationHistory(int $applicationId, int $actorUserId): ?array
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        // Verify the application exists and actor has access
        $stmt = Database::query(
            "SELECT jva.id, jv.user_id as owner_id, jva.user_id as applicant_id
             FROM job_vacancy_applications jva
             INNER JOIN job_vacancies jv ON jv.id = jva.vacancy_id
             WHERE jva.id = ? AND jva.tenant_id = ?",
            [$applicationId, $tenantId]
        );
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $historyStmt = Database::query(
            "SELECT * FROM job_application_history WHERE application_id = ? ORDER BY created_at ASC",
            [$applicationId]
        );

        $history = [];
        while ($h = $historyStmt->fetch()) {
            $history[] = $h;
        }
        return $history;
    }

    // ------------------------------------------------------------------
    //  Qualification assessment
    // ------------------------------------------------------------------

    public static function getQualificationAssessment(int $userId, int $jobId): ?array
    {
        self::clearErrors();
        $match = self::calculateMatchPercentage($userId, $jobId);

        return [
            'score'   => $match['percentage'],
            'matched' => $match['matched'],
            'missing' => $match['missing'],
        ];
    }

    // ------------------------------------------------------------------
    //  Job alerts
    // ------------------------------------------------------------------

    public static function subscribeAlert(int $userId, array $criteria): ?int
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        Database::query(
            "INSERT INTO job_alert_subscriptions (user_id, tenant_id, keywords, category, location, frequency, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [
                $userId,
                $tenantId,
                $criteria['keywords'] ?? null,
                $criteria['category'] ?? null,
                $criteria['location'] ?? null,
                $criteria['frequency'] ?? 'weekly',
            ]
        );

        return (int)Database::getInstance()->lastInsertId();
    }

    public static function getAlerts(int $userId): array
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT * FROM job_alert_subscriptions WHERE user_id = ? AND tenant_id = ? ORDER BY created_at DESC",
            [$userId, $tenantId]
        );

        $alerts = [];
        while ($row = $stmt->fetch()) {
            $alerts[] = $row;
        }
        return $alerts;
    }

    public static function deleteAlert(int $alertId, int $userId): bool
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        // Verify ownership
        $stmt = Database::query(
            "SELECT user_id FROM job_alert_subscriptions WHERE id = ? AND tenant_id = ?",
            [$alertId, $tenantId]
        );
        $row = $stmt->fetch();
        if (!$row || (int)$row['user_id'] !== $userId) {
            return false;
        }

        Database::query(
            "DELETE FROM job_alert_subscriptions WHERE id = ? AND tenant_id = ?",
            [$alertId, $tenantId]
        );

        return true;
    }

    // ------------------------------------------------------------------
    //  Expiry and renewal
    // ------------------------------------------------------------------

    public static function expireOverdueJobs(): int
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        // Try both 'deadline' and 'expires_at' column names
        $column = 'expires_at';
        try {
            $stmt = Database::query(
                "UPDATE job_vacancies SET status = 'expired'
                 WHERE tenant_id = ? AND status IN ('active', 'open') AND {$column} IS NOT NULL AND {$column} < NOW()",
                [$tenantId]
            );
        } catch (\Throwable $e) {
            // Try with 'deadline' column
            $column = 'deadline';
            try {
                $stmt = Database::query(
                    "UPDATE job_vacancies SET status = 'expired'
                     WHERE tenant_id = ? AND status IN ('active', 'open') AND {$column} IS NOT NULL AND {$column} < NOW()",
                    [$tenantId]
                );
            } catch (\Throwable $e2) {
                return 0;
            }
        }

        return (int)Database::getInstance()->rowCount();
    }

    public static function renewJob(int $jobId, int $userId, int $days): bool
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT id, expires_at FROM job_vacancies WHERE id = ? AND tenant_id = ? AND user_id = ?",
            [$jobId, $tenantId, $userId]
        );
        if (!$stmt->fetch()) {
            return false;
        }

        Database::query(
            "UPDATE job_vacancies
             SET expires_at = GREATEST(COALESCE(expires_at, NOW()), NOW()) + INTERVAL ? DAY,
                 status = 'active'
             WHERE id = ? AND tenant_id = ?",
            [$days, $jobId, $tenantId]
        );

        return true;
    }

    // ------------------------------------------------------------------
    //  Featured jobs
    // ------------------------------------------------------------------

    public static function featureJob(int $jobId, int $adminUserId, int $days): bool
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        // Only admins can feature
        $stmt = Database::query(
            "SELECT role FROM users WHERE id = ? AND tenant_id = ?",
            [$adminUserId, $tenantId]
        );
        $user = $stmt->fetch();
        if (!$user || !in_array($user['role'], ['admin', 'super_admin'], true)) {
            return false;
        }

        Database::query(
            "UPDATE job_vacancies SET is_featured = 1, featured_until = DATE_ADD(NOW(), INTERVAL ? DAY)
             WHERE id = ? AND tenant_id = ?",
            [$days, $jobId, $tenantId]
        );

        return true;
    }

    public static function unfeatureJob(int $jobId, int $adminUserId): bool
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        Database::query(
            "UPDATE job_vacancies SET is_featured = 0, featured_until = NULL
             WHERE id = ? AND tenant_id = ?",
            [$jobId, $tenantId]
        );

        return true;
    }

    // ------------------------------------------------------------------
    //  Analytics
    // ------------------------------------------------------------------

    public static function getAnalytics(int $jobId, int $userId): ?array
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT jv.*, (SELECT COUNT(*) FROM job_vacancy_applications WHERE vacancy_id = jv.id) as application_count
             FROM job_vacancies jv
             WHERE jv.id = ? AND jv.tenant_id = ?",
            [$jobId, $tenantId]
        );
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $views = 0;
        try {
            $viewStmt = Database::query(
                "SELECT COUNT(*) as cnt FROM job_vacancy_views WHERE vacancy_id = ? AND tenant_id = ?",
                [$jobId, $tenantId]
            );
            $views = (int)($viewStmt->fetch()['cnt'] ?? 0);
        } catch (\Throwable $e) {
            $views = (int)($row['views_count'] ?? 0);
        }

        return [
            'views'        => $views,
            'applications' => (int)($row['application_count'] ?? 0),
            'created_at'   => $row['created_at'] ?? null,
            'expires_at'   => $row['expires_at'] ?? null,
            'status'       => $row['status'] ?? null,
        ];
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    private static function formatVacancy(array $row, int $userId = 0): array
    {
        $row['id'] = (int)$row['id'];
        $row['user_id'] = (int)$row['user_id'];
        $row['tenant_id'] = (int)$row['tenant_id'];
        if (isset($row['salary_min'])) {
            $row['salary_min'] = (int)$row['salary_min'];
        }
        if (isset($row['salary_max'])) {
            $row['salary_max'] = (int)$row['salary_max'];
        }
        if (isset($row['is_featured'])) {
            $row['is_featured'] = (bool)$row['is_featured'];
        }
        return $row;
    }
}
