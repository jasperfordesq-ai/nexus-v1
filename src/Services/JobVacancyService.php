<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;

/**
 * JobVacancyService - Business logic for job vacancies
 *
 * Provides methods for job vacancy CRUD operations, applications management,
 * enrichment with creator info and application status, saved jobs, skills matching,
 * pipeline stages, alerts, expiry/renewal, analytics, salary, and featured jobs.
 *
 * @package Nexus\Services
 */
class JobVacancyService
{
    /** @var array Collected errors */
    private static array $errors = [];

    /**
     * Get all validation errors
     *
     * @return array
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Clear errors
     */
    private static function clearErrors(): void
    {
        self::$errors = [];
    }

    /**
     * Add an error
     */
    private static function addError(string $code, string $message, ?string $field = null): void
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];
        if ($field !== null) {
            $error['field'] = $field;
        }
        self::$errors[] = $error;
    }

    /**
     * Get all job vacancies with filtering and cursor-based pagination
     *
     * @param array $filters Optional filters: status, type, commitment, category, search, user_id, cursor, limit
     * @return array ['items' => [...], 'cursor' => ?string, 'has_more' => bool]
     */
    public static function getAll(array $filters = [], ?int $userId = null): array
    {
        $tenantId = TenantContext::getId();
        $limit = $filters['limit'] ?? 20;
        $cursor = $filters['cursor'] ?? null;
        $status = $filters['status'] ?? null;
        $type = $filters['type'] ?? null;
        $commitment = $filters['commitment'] ?? null;
        $category = $filters['category'] ?? null;
        $search = $filters['search'] ?? null;
        $userId = $filters['user_id'] ?? null;

        $params = [$tenantId];
        $where = ["jv.tenant_id = ?"];

        // Status filter
        if ($status && in_array($status, ['open', 'closed', 'filled', 'draft'])) {
            $where[] = "jv.status = ?";
            $params[] = $status;
        }

        // Type filter
        if ($type && in_array($type, ['paid', 'volunteer', 'timebank'])) {
            $where[] = "jv.type = ?";
            $params[] = $type;
        }

        // Commitment filter
        if ($commitment && in_array($commitment, ['full_time', 'part_time', 'flexible', 'one_off'])) {
            $where[] = "jv.commitment = ?";
            $params[] = $commitment;
        }

        // Category filter
        if ($category) {
            $where[] = "jv.category = ?";
            $params[] = $category;
        }

        // Search filter (escape LIKE wildcards to prevent pattern injection)
        if ($search) {
            $where[] = "(jv.title LIKE ? OR jv.description LIKE ? OR jv.category LIKE ?)";
            $escapedSearch = addcslashes($search, '%_\\');
            $searchTerm = '%' . $escapedSearch . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // User filter
        if ($userId) {
            $where[] = "jv.user_id = ?";
            $params[] = $userId;
        }

        // Cursor pagination
        if ($cursor) {
            $cursorId = base64_decode($cursor);
            if ($cursorId !== false) {
                $where[] = "jv.id < ?";
                $params[] = (int)$cursorId;
            }
        }

        // J10: Featured filter
        $featuredOnly = $filters['featured'] ?? false;
        if ($featuredOnly) {
            $where[] = "jv.is_featured = 1";
            $where[] = "(jv.featured_until IS NULL OR jv.featured_until > NOW())";
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit + 1; // Fetch one extra to check for more

        // J10: Featured jobs appear first in listing
        $sql = "
            SELECT
                jv.*,
                u.first_name as creator_first_name,
                u.last_name as creator_last_name,
                u.avatar_url as creator_avatar,
                o.name as organization_name,
                o.logo_url as organization_logo
            FROM job_vacancies jv
            LEFT JOIN users u ON jv.user_id = u.id
            LEFT JOIN organizations o ON jv.organization_id = o.id
            WHERE {$whereClause}
            ORDER BY
                (CASE WHEN jv.is_featured = 1 AND (jv.featured_until IS NULL OR jv.featured_until > NOW()) THEN 0 ELSE 1 END) ASC,
                jv.created_at DESC, jv.id DESC
            LIMIT ?
        ";

        $vacancies = Database::query($sql, $params)->fetchAll();

        $hasMore = count($vacancies) > $limit;
        if ($hasMore) {
            array_pop($vacancies); // Remove the extra item
        }

        $nextCursor = null;
        if ($hasMore && !empty($vacancies)) {
            $lastItem = end($vacancies);
            $nextCursor = base64_encode((string)$lastItem['id']);
        }

        // Enrich each vacancy (pass userId for has_applied/is_saved checks)
        foreach ($vacancies as &$vacancy) {
            $vacancy = self::enrichVacancy($vacancy, $userId);
        }

        return [
            'items' => $vacancies,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single job vacancy by ID
     *
     * @param int $id
     * @param int|null $userId Current user ID for has_applied check
     * @return array|null
     */
    public static function getById(int $id, ?int $userId = null): ?array
    {
        $tenantId = TenantContext::getId();

        $sql = "
            SELECT
                jv.*,
                u.first_name as creator_first_name,
                u.last_name as creator_last_name,
                u.avatar_url as creator_avatar,
                o.name as organization_name,
                o.logo_url as organization_logo
            FROM job_vacancies jv
            LEFT JOIN users u ON jv.user_id = u.id
            LEFT JOIN organizations o ON jv.organization_id = o.id
            WHERE jv.id = ? AND jv.tenant_id = ?
        ";

        $vacancy = Database::query($sql, [$id, $tenantId])->fetch();

        if (!$vacancy) {
            return null;
        }

        return self::enrichVacancy($vacancy, $userId);
    }

    /**
     * Enrich a vacancy with creator info, skills array, and application status
     *
     * @param array $vacancy
     * @param int|null $userId
     * @return array
     */
    private static function enrichVacancy(array $vacancy, ?int $userId = null): array
    {
        // Format creator info
        $vacancy['creator'] = [
            'id' => (int)$vacancy['user_id'],
            'name' => trim(($vacancy['creator_first_name'] ?? '') . ' ' . ($vacancy['creator_last_name'] ?? '')),
            'avatar_url' => $vacancy['creator_avatar'] ?? null,
        ];

        // Format organization info
        if ($vacancy['organization_id']) {
            $vacancy['organization'] = [
                'id' => (int)$vacancy['organization_id'],
                'name' => $vacancy['organization_name'] ?? null,
                'logo_url' => $vacancy['organization_logo'] ?? null,
            ];
        } else {
            $vacancy['organization'] = null;
        }

        // Parse skills as array
        $vacancy['skills'] = !empty($vacancy['skills_required'])
            ? array_map('trim', explode(',', $vacancy['skills_required']))
            : [];

        // Cast numeric fields
        $vacancy['id'] = (int)$vacancy['id'];
        $vacancy['tenant_id'] = (int)$vacancy['tenant_id'];
        $vacancy['user_id'] = (int)$vacancy['user_id'];
        $vacancy['views_count'] = (int)$vacancy['views_count'];
        $vacancy['applications_count'] = (int)$vacancy['applications_count'];
        $vacancy['is_remote'] = (bool)$vacancy['is_remote'];
        $vacancy['hours_per_week'] = $vacancy['hours_per_week'] !== null ? (float)$vacancy['hours_per_week'] : null;
        $vacancy['time_credits'] = $vacancy['time_credits'] !== null ? (float)$vacancy['time_credits'] : null;

        // J9: Salary/compensation fields
        $vacancy['salary_min'] = isset($vacancy['salary_min']) && $vacancy['salary_min'] !== null ? (float)$vacancy['salary_min'] : null;
        $vacancy['salary_max'] = isset($vacancy['salary_max']) && $vacancy['salary_max'] !== null ? (float)$vacancy['salary_max'] : null;
        $vacancy['salary_type'] = $vacancy['salary_type'] ?? null;
        $vacancy['salary_currency'] = $vacancy['salary_currency'] ?? null;
        $vacancy['salary_negotiable'] = isset($vacancy['salary_negotiable']) ? (bool)$vacancy['salary_negotiable'] : false;

        // J10: Featured jobs
        $vacancy['is_featured'] = isset($vacancy['is_featured']) ? (bool)$vacancy['is_featured'] : false;
        $vacancy['featured_until'] = $vacancy['featured_until'] ?? null;
        // Auto-expire featured status
        if ($vacancy['is_featured'] && $vacancy['featured_until'] && strtotime($vacancy['featured_until']) < time()) {
            $vacancy['is_featured'] = false;
        }

        // J7: Expiry/renewal
        $vacancy['expired_at'] = $vacancy['expired_at'] ?? null;
        $vacancy['renewed_at'] = $vacancy['renewed_at'] ?? null;
        $vacancy['renewal_count'] = isset($vacancy['renewal_count']) ? (int)$vacancy['renewal_count'] : 0;

        // Check if user has applied
        if ($userId) {
            $application = Database::query(
                "SELECT id, status, stage FROM job_vacancy_applications WHERE vacancy_id = ? AND user_id = ?",
                [$vacancy['id'], $userId]
            )->fetch();
            $vacancy['has_applied'] = !empty($application);
            $vacancy['application_status'] = $application ? $application['status'] : null;
            $vacancy['application_stage'] = $application ? ($application['stage'] ?? $application['status']) : null;

            // J1: Check if user has saved this job
            $saved = Database::query(
                "SELECT id FROM saved_jobs WHERE job_id = ? AND user_id = ? AND tenant_id = ?",
                [$vacancy['id'], $userId, $vacancy['tenant_id']]
            )->fetch();
            $vacancy['is_saved'] = !empty($saved);
        } else {
            $vacancy['has_applied'] = false;
            $vacancy['application_status'] = null;
            $vacancy['application_stage'] = null;
            $vacancy['is_saved'] = false;
        }

        // Clean up redundant fields
        unset(
            $vacancy['creator_first_name'],
            $vacancy['creator_last_name'],
            $vacancy['creator_avatar'],
            $vacancy['organization_name'],
            $vacancy['organization_logo']
        );

        return $vacancy;
    }

    /**
     * Create a new job vacancy
     *
     * @param int $userId
     * @param array $data
     * @return int|null Vacancy ID on success, null on failure
     */
    public static function create(int $userId, array $data): ?int
    {
        self::clearErrors();

        $tenantId = TenantContext::getId();
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $location = isset($data['location']) ? trim($data['location']) : null;
        $isRemote = !empty($data['is_remote']) ? 1 : 0;
        $type = $data['type'] ?? 'paid';
        $commitment = $data['commitment'] ?? 'flexible';
        $category = isset($data['category']) ? trim($data['category']) : null;
        $skillsRequired = isset($data['skills_required']) ? trim($data['skills_required']) : null;
        $hoursPerWeek = isset($data['hours_per_week']) ? (float)$data['hours_per_week'] : null;
        $timeCredits = isset($data['time_credits']) ? (float)$data['time_credits'] : null;
        $contactEmail = isset($data['contact_email']) ? trim($data['contact_email']) : null;
        $contactPhone = isset($data['contact_phone']) ? trim($data['contact_phone']) : null;
        $deadline = $data['deadline'] ?? null;
        $status = $data['status'] ?? 'open';
        $organizationId = isset($data['organization_id']) ? (int)$data['organization_id'] : null;

        // J9: Salary fields
        $salaryMin = isset($data['salary_min']) && $data['salary_min'] !== '' ? (float)$data['salary_min'] : null;
        $salaryMax = isset($data['salary_max']) && $data['salary_max'] !== '' ? (float)$data['salary_max'] : null;
        $salaryType = isset($data['salary_type']) && in_array($data['salary_type'], ['hourly', 'annual', 'time_credits']) ? $data['salary_type'] : null;
        $salaryCurrency = isset($data['salary_currency']) ? trim($data['salary_currency']) : null;
        $salaryNegotiable = !empty($data['salary_negotiable']) ? 1 : 0;

        // Validation
        if (empty($title)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Title is required', 'title');
        }

        if (mb_strlen($title) > 255) {
            self::addError(ApiErrorCodes::VALIDATION_ERROR, 'Title must be 255 characters or fewer', 'title');
        }

        if (empty($description)) {
            self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Description is required', 'description');
        }

        if (!in_array($type, ['paid', 'volunteer', 'timebank'])) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Invalid job type', 'type');
        }

        if (!in_array($commitment, ['full_time', 'part_time', 'flexible', 'one_off'])) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Invalid commitment type', 'commitment');
        }

        if (!in_array($status, ['open', 'draft'])) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Status must be open or draft', 'status');
        }

        if ($contactEmail && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Invalid contact email', 'contact_email');
        }

        // J9: Salary validation
        if ($salaryMin !== null && $salaryMin < 0) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Salary minimum must be non-negative', 'salary_min');
        }
        if ($salaryMax !== null && $salaryMax < 0) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Salary maximum must be non-negative', 'salary_max');
        }
        if ($salaryMin !== null && $salaryMax !== null && $salaryMin > $salaryMax) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Salary minimum cannot exceed maximum', 'salary_min');
        }

        if ($deadline) {
            $deadlineTs = strtotime($deadline);
            if ($deadlineTs === false) {
                self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Invalid deadline format', 'deadline');
            } elseif ($deadlineTs < time()) {
                self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Deadline must be in the future', 'deadline');
            }
        }

        if (!empty(self::$errors)) {
            return null;
        }

        try {
            Database::query(
                "INSERT INTO job_vacancies
                    (tenant_id, user_id, organization_id, title, description, location, is_remote,
                     type, commitment, category, skills_required, hours_per_week, time_credits,
                     contact_email, contact_phone, deadline, status,
                     salary_min, salary_max, salary_type, salary_currency, salary_negotiable,
                     created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $tenantId, $userId, $organizationId, $title, $description, $location, $isRemote,
                    $type, $commitment, $category, $skillsRequired, $hoursPerWeek, $timeCredits,
                    $contactEmail, $contactPhone, $deadline, $status,
                    $salaryMin, $salaryMax, $salaryType, $salaryCurrency, $salaryNegotiable
                ]
            );

            $vacancyId = Database::lastInsertId();

            // Record in feed_activity table
            try {
                FeedActivityService::recordActivity($tenantId, $userId, 'job', (int)$vacancyId, [
                    'title' => $title,
                    'content' => $description,
                    'metadata' => [
                        'location' => $location,
                        'job_type' => $type,
                        'commitment' => $commitment,
                    ],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Exception $faEx) {
                error_log("JobVacancyService: feed_activity record failed (create): " . get_class($faEx));
            }

            // J6: Check matching alerts for the new job
            if ($status === 'open') {
                self::checkJobAlertsForVacancy((int)$vacancyId);
            }

            // Award gamification points
            try {
                if (class_exists('\Nexus\Models\Gamification')) {
                    \Nexus\Models\Gamification::awardPoints($userId, 10, 'Posted a job vacancy');
                }
            } catch (\Throwable $e) {
                error_log("JobVacancyService: Gamification award failed (create): " . get_class($e));
            }

            return (int)$vacancyId;
        } catch (\Throwable $e) {
            error_log("JobVacancyService: Job vacancy creation failed: " . get_class($e));
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to create job vacancy');
            return null;
        }
    }

    /**
     * Update an existing job vacancy
     *
     * @param int $id
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public static function update(int $id, int $userId, array $data): bool
    {
        self::clearErrors();

        $vacancy = self::getById($id);

        if (!$vacancy) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Job vacancy not found');
            return false;
        }

        // Check ownership
        if ((int)$vacancy['user_id'] !== $userId) {
            // Check if user is admin
            $user = Database::query("SELECT role FROM users WHERE id = ?", [$userId])->fetch();
            if (!$user || !in_array($user['role'], ['admin', 'super_admin'])) {
                self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'You can only edit your own job vacancies');
                return false;
            }
        }

        $updates = [];
        $params = [];

        $allowedFields = [
            'title' => 'string',
            'description' => 'string',
            'location' => 'string_nullable',
            'is_remote' => 'bool',
            'type' => 'enum:paid,volunteer,timebank',
            'commitment' => 'enum:full_time,part_time,flexible,one_off',
            'category' => 'string_nullable',
            'skills_required' => 'string_nullable',
            'hours_per_week' => 'float_nullable',
            'time_credits' => 'float_nullable',
            'contact_email' => 'string_nullable',
            'contact_phone' => 'string_nullable',
            'deadline' => 'string_nullable',
            'status' => 'enum:open,closed,filled,draft',
            'organization_id' => 'int_nullable',
            // J9: Salary fields
            'salary_min' => 'float_nullable',
            'salary_max' => 'float_nullable',
            'salary_type' => 'enum_nullable:hourly,annual,time_credits',
            'salary_currency' => 'string_nullable',
            'salary_negotiable' => 'bool',
        ];

        foreach ($allowedFields as $field => $fieldType) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            // Type-specific validation
            if ($fieldType === 'string') {
                $value = trim($value);
                if ($field === 'title' && empty($value)) {
                    self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Title cannot be empty', 'title');
                    return false;
                }
                if ($field === 'description' && empty($value)) {
                    self::addError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Description cannot be empty', 'description');
                    return false;
                }
            } elseif ($fieldType === 'string_nullable') {
                $value = $value !== null ? trim($value) : null;
                $value = $value === '' ? null : $value;
            } elseif ($fieldType === 'bool') {
                $value = $value ? 1 : 0;
            } elseif ($fieldType === 'float_nullable') {
                $value = $value !== null && $value !== '' ? (float)$value : null;
            } elseif ($fieldType === 'int_nullable') {
                $value = $value !== null && $value !== '' ? (int)$value : null;
            } elseif (str_starts_with($fieldType, 'enum_nullable:')) {
                if ($value !== null && $value !== '') {
                    $allowed = explode(',', substr($fieldType, 14));
                    if (!in_array($value, $allowed)) {
                        self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, "Invalid value for {$field}", $field);
                        return false;
                    }
                } else {
                    $value = null;
                }
            } elseif (str_starts_with($fieldType, 'enum:')) {
                $allowed = explode(',', substr($fieldType, 5));
                if (!in_array($value, $allowed)) {
                    self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, "Invalid value for {$field}", $field);
                    return false;
                }
            }

            // Email validation
            if ($field === 'contact_email' && $value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Invalid contact email', 'contact_email');
                return false;
            }

            $updates[] = "{$field} = ?";
            $params[] = $value;
        }

        if (empty($updates)) {
            return true; // Nothing to update
        }

        $tenantId = TenantContext::getId();
        $params[] = $id;
        $params[] = $tenantId;

        try {
            Database::query(
                "UPDATE job_vacancies SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?",
                $params
            );
            return true;
        } catch (\Throwable $e) {
            error_log("JobVacancyService: Job vacancy update failed: " . get_class($e));
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to update job vacancy');
            return false;
        }
    }

    /**
     * Delete a job vacancy
     *
     * @param int $id
     * @param int $userId
     * @return bool
     */
    public static function delete(int $id, int $userId): bool
    {
        self::clearErrors();

        $vacancy = self::getById($id);

        if (!$vacancy) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Job vacancy not found');
            return false;
        }

        // Check ownership (or admin)
        if ((int)$vacancy['user_id'] !== $userId) {
            $user = Database::query("SELECT role FROM users WHERE id = ?", [$userId])->fetch();
            if (!$user || !in_array($user['role'], ['admin', 'super_admin'])) {
                self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'You can only delete your own job vacancies');
                return false;
            }
        }

        try {
            $tenantId = TenantContext::getId();

            // Applications cascade via FK, but delete explicitly for safety
            Database::query(
                "DELETE FROM job_vacancy_applications WHERE vacancy_id = ?",
                [$id]
            );

            Database::query(
                "DELETE FROM job_vacancies WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            // Remove from feed_activity
            try {
                FeedActivityService::removeActivity('job', $id);
            } catch (\Exception $faEx) {
                error_log("JobVacancyService: feed_activity remove failed (delete): " . get_class($faEx));
            }

            return true;
        } catch (\Throwable $e) {
            error_log("JobVacancyService: Job vacancy deletion failed: " . get_class($e));
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to delete job vacancy');
            return false;
        }
    }

    /**
     * Apply for a job vacancy
     *
     * @param int $vacancyId
     * @param int $userId
     * @param string|null $message
     * @return int|null Application ID on success, null on failure
     */
    public static function apply(int $vacancyId, int $userId, ?string $message = null): ?int
    {
        self::clearErrors();

        $vacancy = self::getById($vacancyId, $userId);

        if (!$vacancy) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Job vacancy not found');
            return null;
        }

        // Can't apply to own vacancy
        if ((int)$vacancy['user_id'] === $userId) {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'You cannot apply to your own job vacancy');
            return null;
        }

        // Check if vacancy is open
        if ($vacancy['status'] !== 'open') {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'This job vacancy is no longer accepting applications');
            return null;
        }

        // Check if already applied
        if (!empty($vacancy['has_applied'])) {
            self::addError(ApiErrorCodes::RESOURCE_CONFLICT, 'You have already applied to this job vacancy');
            return null;
        }

        try {
            Database::beginTransaction();

            // J3: Use 'applied' as initial stage
            Database::query(
                "INSERT INTO job_vacancy_applications (vacancy_id, user_id, message, status, stage, created_at)
                 VALUES (?, ?, ?, 'applied', 'applied', NOW())",
                [$vacancyId, $userId, $message ? trim($message) : null]
            );

            $applicationId = Database::lastInsertId();

            // J4: Log initial application in history
            self::logApplicationHistory((int)$applicationId, null, 'applied', $userId, 'Application submitted');

            // Increment applications count (atomic within transaction)
            Database::query(
                "UPDATE job_vacancies SET applications_count = applications_count + 1 WHERE id = ?",
                [$vacancyId]
            );

            Database::commit();

            // Check for matching job alerts and trigger notifications
            self::checkJobAlertsForVacancy($vacancyId);

            // Award gamification points
            try {
                if (class_exists('\Nexus\Models\Gamification')) {
                    \Nexus\Models\Gamification::awardPoints($userId, 3, 'Applied to a job vacancy');
                }
            } catch (\Throwable $e) {
                error_log("JobVacancyService: Gamification award failed (apply): " . get_class($e));
            }

            return (int)$applicationId;
        } catch (\Throwable $e) {
            Database::rollBack();
            error_log("JobVacancyService: Job application failed: " . get_class($e));
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to submit application');
            return null;
        }
    }

    /**
     * Get applications for a vacancy (owner only)
     *
     * @param int $vacancyId
     * @param int $userId
     * @return array|null
     */
    public static function getApplications(int $vacancyId, int $userId): ?array
    {
        self::clearErrors();

        $vacancy = self::getById($vacancyId);

        if (!$vacancy) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Job vacancy not found');
            return null;
        }

        // Check ownership or admin
        if ((int)$vacancy['user_id'] !== $userId) {
            $user = Database::query("SELECT role FROM users WHERE id = ?", [$userId])->fetch();
            if (!$user || !in_array($user['role'], ['admin', 'super_admin'])) {
                self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only the vacancy owner can view applications');
                return null;
            }
        }

        $applications = Database::query(
            "SELECT
                a.*,
                u.first_name as applicant_first_name,
                u.last_name as applicant_last_name,
                u.avatar_url as applicant_avatar,
                u.email as applicant_email
             FROM job_vacancy_applications a
             LEFT JOIN users u ON a.user_id = u.id
             WHERE a.vacancy_id = ?
             ORDER BY a.created_at DESC",
            [$vacancyId]
        )->fetchAll();

        // Format applications
        foreach ($applications as &$app) {
            $app['id'] = (int)$app['id'];
            $app['vacancy_id'] = (int)$app['vacancy_id'];
            $app['user_id'] = (int)$app['user_id'];
            $app['applicant'] = [
                'id' => (int)$app['user_id'],
                'name' => trim(($app['applicant_first_name'] ?? '') . ' ' . ($app['applicant_last_name'] ?? '')),
                'avatar_url' => $app['applicant_avatar'] ?? null,
                'email' => $app['applicant_email'] ?? null,
            ];
            unset($app['applicant_first_name'], $app['applicant_last_name'], $app['applicant_avatar'], $app['applicant_email']);
        }

        return $applications;
    }

    /**
     * Update an application status (accept/reject)
     *
     * @param int $applicationId
     * @param int $userId
     * @param string $status
     * @param string|null $notes
     * @return bool
     */
    /**
     * Valid pipeline stages (J3)
     */
    private static array $validStages = [
        'applied', 'screening', 'interview', 'offer', 'accepted', 'rejected', 'withdrawn'
    ];

    /**
     * Update an application status/stage (J3: full pipeline support)
     *
     * @param int $applicationId
     * @param int $userId
     * @param string $status
     * @param string|null $notes
     * @return bool
     */
    public static function updateApplicationStatus(int $applicationId, int $userId, string $status, ?string $notes = null): bool
    {
        self::clearErrors();

        // J3: Support full pipeline stages (includes 'pending' for backwards compatibility)
        $validStatuses = ['applied', 'pending', 'screening', 'reviewed', 'interview', 'offer', 'accepted', 'rejected', 'withdrawn'];
        if (!in_array($status, $validStatuses)) {
            self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Invalid application status', 'status');
            return false;
        }

        // Get the application with its vacancy
        $application = Database::query(
            "SELECT a.*, jv.user_id as vacancy_owner_id, jv.tenant_id
             FROM job_vacancy_applications a
             JOIN job_vacancies jv ON a.vacancy_id = jv.id
             WHERE a.id = ?",
            [$applicationId]
        )->fetch();

        if (!$application) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Application not found');
            return false;
        }

        // Must be tenant-scoped
        $tenantId = TenantContext::getId();
        if ((int)$application['tenant_id'] !== $tenantId) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Application not found');
            return false;
        }

        // Check vacancy ownership or admin
        if ((int)$application['vacancy_owner_id'] !== $userId) {
            $user = Database::query("SELECT role FROM users WHERE id = ?", [$userId])->fetch();
            if (!$user || !in_array($user['role'], ['admin', 'super_admin'])) {
                self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only the vacancy owner can update applications');
                return false;
            }
        }

        $previousStatus = $application['status'] ?? $application['stage'] ?? 'applied';

        try {
            // J3: Update both status and stage columns
            Database::query(
                "UPDATE job_vacancy_applications
                 SET status = ?, stage = ?, reviewer_notes = ?, reviewed_by = ?, reviewed_at = NOW()
                 WHERE id = ?",
                [$status, $status, $notes ? trim($notes) : null, $userId, $applicationId]
            );

            // J4: Log status change in history
            self::logApplicationHistory($applicationId, $previousStatus, $status, $userId, $notes);

            return true;
        } catch (\Throwable $e) {
            error_log("JobVacancyService: Application status update failed: " . get_class($e));
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to update application');
            return false;
        }
    }

    /**
     * Get user's own applications with vacancy info
     *
     * @param int $userId
     * @param array $filters Optional filters: status, cursor, limit
     * @return array ['items' => [...], 'cursor' => ?string, 'has_more' => bool]
     */
    public static function getMyApplications(int $userId, array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = $filters['limit'] ?? 20;
        $cursor = $filters['cursor'] ?? null;
        $status = $filters['status'] ?? null;

        $params = [$userId, $tenantId];
        $where = ["a.user_id = ?", "jv.tenant_id = ?"];

        if ($status && in_array($status, ['applied', 'pending', 'screening', 'reviewed', 'interview', 'offer', 'accepted', 'rejected', 'withdrawn'])) {
            $where[] = "a.status = ?";
            $params[] = $status;
        }

        if ($cursor) {
            $cursorId = base64_decode($cursor);
            if ($cursorId !== false) {
                $where[] = "a.id < ?";
                $params[] = (int)$cursorId;
            }
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit + 1;

        $sql = "
            SELECT
                a.*,
                jv.title as vacancy_title,
                jv.type as vacancy_type,
                jv.commitment as vacancy_commitment,
                jv.status as vacancy_status,
                jv.location as vacancy_location,
                jv.is_remote as vacancy_is_remote,
                jv.deadline as vacancy_deadline
            FROM job_vacancy_applications a
            JOIN job_vacancies jv ON a.vacancy_id = jv.id
            WHERE {$whereClause}
            ORDER BY a.created_at DESC, a.id DESC
            LIMIT ?
        ";

        $applications = Database::query($sql, $params)->fetchAll();

        $hasMore = count($applications) > $limit;
        if ($hasMore) {
            array_pop($applications);
        }

        $nextCursor = null;
        if ($hasMore && !empty($applications)) {
            $lastItem = end($applications);
            $nextCursor = base64_encode((string)$lastItem['id']);
        }

        // Format
        foreach ($applications as &$app) {
            $app['id'] = (int)$app['id'];
            $app['vacancy_id'] = (int)$app['vacancy_id'];
            $app['user_id'] = (int)$app['user_id'];
            $app['vacancy'] = [
                'id' => (int)$app['vacancy_id'],
                'title' => $app['vacancy_title'],
                'type' => $app['vacancy_type'],
                'commitment' => $app['vacancy_commitment'],
                'status' => $app['vacancy_status'],
                'location' => $app['vacancy_location'],
                'is_remote' => (bool)$app['vacancy_is_remote'],
                'deadline' => $app['vacancy_deadline'],
            ];
            unset(
                $app['vacancy_title'], $app['vacancy_type'], $app['vacancy_commitment'],
                $app['vacancy_status'], $app['vacancy_location'], $app['vacancy_is_remote'],
                $app['vacancy_deadline']
            );
        }

        return [
            'items' => $applications,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Increment the views count for a vacancy (J8: also log to analytics table)
     *
     * @param int $id
     * @param int|null $userId
     */
    public static function incrementViews(int $id, ?int $userId = null): void
    {
        $tenantId = TenantContext::getId();
        try {
            Database::query(
                "UPDATE job_vacancies SET views_count = views_count + 1 WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );

            // J8: Log individual view for analytics
            $ipHash = null;
            if (class_exists('\Nexus\Core\ClientIp')) {
                $ipHash = hash('sha256', \Nexus\Core\ClientIp::get() . date('Y-m-d'));
            }
            Database::query(
                "INSERT INTO job_vacancy_views (vacancy_id, user_id, tenant_id, viewed_at, ip_hash)
                 VALUES (?, ?, ?, NOW(), ?)",
                [$id, $userId, $tenantId, $ipHash]
            );
        } catch (\Throwable $e) {
            // Non-critical — log and continue
            error_log("JobVacancyService: Failed to increment views: " . get_class($e));
        }
    }

    // =========================================================================
    // J1: SAVED JOBS (BOOKMARKS)
    // =========================================================================

    /**
     * Save (bookmark) a job for the current user
     *
     * @param int $jobId
     * @param int $userId
     * @return bool
     */
    public static function saveJob(int $jobId, int $userId): bool
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        // Verify job exists in this tenant
        $job = Database::query(
            "SELECT id FROM job_vacancies WHERE id = ? AND tenant_id = ?",
            [$jobId, $tenantId]
        )->fetch();

        if (!$job) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Job vacancy not found');
            return false;
        }

        // Check if already saved
        $existing = Database::query(
            "SELECT id FROM saved_jobs WHERE job_id = ? AND user_id = ? AND tenant_id = ?",
            [$jobId, $userId, $tenantId]
        )->fetch();

        if ($existing) {
            return true; // Already saved, idempotent
        }

        try {
            Database::query(
                "INSERT INTO saved_jobs (user_id, job_id, tenant_id, saved_at) VALUES (?, ?, ?, NOW())",
                [$userId, $jobId, $tenantId]
            );
            return true;
        } catch (\Throwable $e) {
            error_log("JobVacancyService: Failed to save job: " . get_class($e));
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to save job');
            return false;
        }
    }

    /**
     * Unsave (remove bookmark) a job for the current user
     *
     * @param int $jobId
     * @param int $userId
     * @return bool
     */
    public static function unsaveJob(int $jobId, int $userId): bool
    {
        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "DELETE FROM saved_jobs WHERE job_id = ? AND user_id = ? AND tenant_id = ?",
                [$jobId, $userId, $tenantId]
            );
            return true;
        } catch (\Throwable $e) {
            error_log("JobVacancyService: Failed to unsave job: " . get_class($e));
            return false;
        }
    }

    /**
     * Get saved jobs for a user
     *
     * @param int $userId
     * @param array $filters Optional: cursor, limit
     * @return array ['items' => [...], 'cursor' => ?string, 'has_more' => bool]
     */
    public static function getSavedJobs(int $userId, array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = $filters['limit'] ?? 20;
        $cursor = $filters['cursor'] ?? null;

        $params = [$userId, $tenantId];
        $where = ["sj.user_id = ?", "sj.tenant_id = ?"];

        if ($cursor) {
            $cursorId = base64_decode($cursor);
            if ($cursorId !== false) {
                $where[] = "sj.id < ?";
                $params[] = (int)$cursorId;
            }
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit + 1;

        $sql = "
            SELECT
                jv.*,
                u.first_name as creator_first_name,
                u.last_name as creator_last_name,
                u.avatar_url as creator_avatar,
                o.name as organization_name,
                o.logo_url as organization_logo,
                sj.id as saved_id,
                sj.saved_at
            FROM saved_jobs sj
            JOIN job_vacancies jv ON sj.job_id = jv.id
            LEFT JOIN users u ON jv.user_id = u.id
            LEFT JOIN organizations o ON jv.organization_id = o.id
            WHERE {$whereClause}
            ORDER BY sj.saved_at DESC, sj.id DESC
            LIMIT ?
        ";

        $rows = Database::query($sql, $params)->fetchAll();

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $nextCursor = null;
        if ($hasMore && !empty($rows)) {
            $lastItem = end($rows);
            $nextCursor = base64_encode((string)$lastItem['saved_id']);
        }

        // Enrich
        foreach ($rows as &$row) {
            $savedAt = $row['saved_at'];
            $row = self::enrichVacancy($row, $userId);
            $row['saved_at'] = $savedAt;
            $row['is_saved'] = true;
        }

        return [
            'items' => $rows,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    // =========================================================================
    // J2: SKILLS MATCHING WITH MATCH PERCENTAGE
    // =========================================================================

    /**
     * Calculate match percentage between a user's skills and a job's required skills
     *
     * @param int $userId
     * @param int $jobId
     * @return array ['percentage' => int, 'matched' => array, 'missing' => array, 'user_skills' => array, 'required_skills' => array]
     */
    public static function calculateMatchPercentage(int $userId, int $jobId): array
    {
        $tenantId = TenantContext::getId();

        // Get user skills
        $user = Database::query(
            "SELECT skills FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();

        $userSkills = [];
        if ($user && !empty($user['skills'])) {
            $userSkills = array_map(function ($s) {
                return strtolower(trim($s));
            }, explode(',', $user['skills']));
            $userSkills = array_filter($userSkills);
        }

        // Get job required skills
        $job = Database::query(
            "SELECT skills_required FROM job_vacancies WHERE id = ? AND tenant_id = ?",
            [$jobId, $tenantId]
        )->fetch();

        $requiredSkills = [];
        if ($job && !empty($job['skills_required'])) {
            $requiredSkills = array_map(function ($s) {
                return strtolower(trim($s));
            }, explode(',', $job['skills_required']));
            $requiredSkills = array_filter($requiredSkills);
        }

        if (empty($requiredSkills)) {
            return [
                'percentage' => 100,
                'matched' => [],
                'missing' => [],
                'user_skills' => $userSkills,
                'required_skills' => $requiredSkills,
            ];
        }

        if (empty($userSkills)) {
            return [
                'percentage' => 0,
                'matched' => [],
                'missing' => $requiredSkills,
                'user_skills' => $userSkills,
                'required_skills' => $requiredSkills,
            ];
        }

        // Match skills — use fuzzy matching (substring/partial match)
        $matched = [];
        $missing = [];

        foreach ($requiredSkills as $required) {
            $isMatched = false;
            foreach ($userSkills as $userSkill) {
                // Exact match or substring containment in either direction
                if ($required === $userSkill
                    || str_contains($required, $userSkill)
                    || str_contains($userSkill, $required)
                    || similar_text($required, $userSkill, $pct) !== false && $pct >= 75
                ) {
                    $matched[] = $required;
                    $isMatched = true;
                    break;
                }
            }
            if (!$isMatched) {
                $missing[] = $required;
            }
        }

        $percentage = (int)round((count($matched) / count($requiredSkills)) * 100);

        return [
            'percentage' => $percentage,
            'matched' => $matched,
            'missing' => $missing,
            'user_skills' => $userSkills,
            'required_skills' => $requiredSkills,
        ];
    }

    // =========================================================================
    // J4: APPLICATION STATUS HISTORY
    // =========================================================================

    /**
     * Log an application status change to history
     *
     * @param int $applicationId
     * @param string|null $fromStatus
     * @param string $toStatus
     * @param int $changedBy
     * @param string|null $notes
     */
    private static function logApplicationHistory(int $applicationId, ?string $fromStatus, string $toStatus, int $changedBy, ?string $notes = null): void
    {
        try {
            Database::query(
                "INSERT INTO job_application_history (application_id, from_status, to_status, changed_by, changed_at, notes)
                 VALUES (?, ?, ?, ?, NOW(), ?)",
                [$applicationId, $fromStatus, $toStatus, $changedBy, $notes ? trim($notes) : null]
            );
        } catch (\Throwable $e) {
            // Non-critical — log and continue
            error_log("JobVacancyService: Failed to log application history: " . get_class($e));
        }
    }

    /**
     * Get status history for an application
     *
     * @param int $applicationId
     * @param int $userId Requesting user
     * @return array|null
     */
    public static function getApplicationHistory(int $applicationId, int $userId): ?array
    {
        self::clearErrors();

        // Get the application with its vacancy to check permissions
        $application = Database::query(
            "SELECT a.*, jv.user_id as vacancy_owner_id, jv.tenant_id
             FROM job_vacancy_applications a
             JOIN job_vacancies jv ON a.vacancy_id = jv.id
             WHERE a.id = ?",
            [$applicationId]
        )->fetch();

        if (!$application) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Application not found');
            return null;
        }

        $tenantId = TenantContext::getId();
        if ((int)$application['tenant_id'] !== $tenantId) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Application not found');
            return null;
        }

        // Must be either the applicant or vacancy owner or admin
        $isApplicant = (int)$application['user_id'] === $userId;
        $isOwner = (int)$application['vacancy_owner_id'] === $userId;

        if (!$isApplicant && !$isOwner) {
            $user = Database::query("SELECT role FROM users WHERE id = ?", [$userId])->fetch();
            if (!$user || !in_array($user['role'], ['admin', 'super_admin'])) {
                self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Access denied');
                return null;
            }
        }

        $history = Database::query(
            "SELECT h.*, u.first_name, u.last_name
             FROM job_application_history h
             JOIN job_vacancy_applications a ON h.application_id = a.id
             JOIN job_vacancies jv ON a.vacancy_id = jv.id AND jv.tenant_id = ?
             LEFT JOIN users u ON h.changed_by = u.id
             WHERE h.application_id = ?
             ORDER BY h.changed_at ASC",
            [$tenantId, $applicationId]
        )->fetchAll();

        foreach ($history as &$entry) {
            $entry['id'] = (int)$entry['id'];
            $entry['application_id'] = (int)$entry['application_id'];
            $entry['changed_by_name'] = trim(($entry['first_name'] ?? '') . ' ' . ($entry['last_name'] ?? ''));
            unset($entry['first_name'], $entry['last_name']);
        }

        return $history;
    }

    // =========================================================================
    // J5: "AM I QUALIFIED?" TOOL
    // =========================================================================

    /**
     * Get qualification assessment for a user against a job
     *
     * @param int $userId
     * @param int $jobId
     * @return array|null
     */
    public static function getQualificationAssessment(int $userId, int $jobId): ?array
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        $vacancy = self::getById($jobId, $userId);
        if (!$vacancy) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Job vacancy not found');
            return null;
        }

        $matchData = self::calculateMatchPercentage($userId, $jobId);

        $totalRequired = count($matchData['required_skills']);
        $totalMatched = count($matchData['matched']);

        // Build detailed breakdown
        $breakdown = [];
        foreach ($matchData['required_skills'] as $skill) {
            $isMatched = in_array($skill, $matchData['matched']);
            $breakdown[] = [
                'skill' => $skill,
                'matched' => $isMatched,
            ];
        }

        // Determine qualification level
        $level = 'low';
        if ($matchData['percentage'] >= 80) {
            $level = 'excellent';
        } elseif ($matchData['percentage'] >= 60) {
            $level = 'good';
        } elseif ($matchData['percentage'] >= 40) {
            $level = 'moderate';
        }

        return [
            'job_id' => $jobId,
            'job_title' => $vacancy['title'],
            'percentage' => $matchData['percentage'],
            'level' => $level,
            'total_required' => $totalRequired,
            'total_matched' => $totalMatched,
            'total_missing' => count($matchData['missing']),
            'breakdown' => $breakdown,
            'matched_skills' => $matchData['matched'],
            'missing_skills' => $matchData['missing'],
            'user_skills' => $matchData['user_skills'],
        ];
    }

    // =========================================================================
    // J6: JOB ALERTS / NOTIFICATIONS
    // =========================================================================

    /**
     * Create or update a job alert subscription
     *
     * @param int $userId
     * @param array $preferences
     * @return int|null Alert ID
     */
    public static function subscribeAlert(int $userId, array $preferences): ?int
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        $keywords = isset($preferences['keywords']) ? trim($preferences['keywords']) : null;
        $categories = isset($preferences['categories']) ? trim($preferences['categories']) : null;
        $type = isset($preferences['type']) && in_array($preferences['type'], ['paid', 'volunteer', 'timebank']) ? $preferences['type'] : null;
        $commitment = isset($preferences['commitment']) && in_array($preferences['commitment'], ['full_time', 'part_time', 'flexible', 'one_off']) ? $preferences['commitment'] : null;
        $location = isset($preferences['location']) ? trim($preferences['location']) : null;
        $isRemoteOnly = !empty($preferences['is_remote_only']) ? 1 : 0;

        try {
            Database::query(
                "INSERT INTO job_alerts (user_id, tenant_id, keywords, categories, type, commitment, location, is_remote_only, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
                [$userId, $tenantId, $keywords, $categories, $type, $commitment, $location, $isRemoteOnly]
            );

            return (int)Database::lastInsertId();
        } catch (\Throwable $e) {
            error_log("JobVacancyService: Failed to create job alert: " . get_class($e));
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to create alert');
            return null;
        }
    }

    /**
     * Unsubscribe (deactivate) a job alert
     *
     * @param int $alertId
     * @param int $userId
     * @return bool
     */
    public static function unsubscribeAlert(int $alertId, int $userId): bool
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "UPDATE job_alerts SET is_active = 0 WHERE id = ? AND user_id = ? AND tenant_id = ?",
                [$alertId, $userId, $tenantId]
            );
            return true;
        } catch (\Throwable $e) {
            error_log("JobVacancyService: Failed to unsubscribe alert: " . get_class($e));
            return false;
        }
    }

    /**
     * Resubscribe (reactivate) a paused job alert
     *
     * @param int $alertId
     * @param int $userId
     * @return bool
     */
    public static function resubscribeAlert(int $alertId, int $userId): bool
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "UPDATE job_alerts SET is_active = 1 WHERE id = ? AND user_id = ? AND tenant_id = ?",
                [$alertId, $userId, $tenantId]
            );
            return true;
        } catch (\Throwable $e) {
            error_log("JobVacancyService: Failed to resubscribe alert: " . get_class($e));
            return false;
        }
    }

    /**
     * Delete a job alert permanently
     *
     * @param int $alertId
     * @param int $userId
     * @return bool
     */
    public static function deleteAlert(int $alertId, int $userId): bool
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        try {
            Database::query(
                "DELETE FROM job_alerts WHERE id = ? AND user_id = ? AND tenant_id = ?",
                [$alertId, $userId, $tenantId]
            );
            return true;
        } catch (\Throwable $e) {
            error_log("JobVacancyService: Failed to delete alert: " . get_class($e));
            return false;
        }
    }

    /**
     * Get alerts for a user
     *
     * @param int $userId
     * @return array
     */
    public static function getAlerts(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $alerts = Database::query(
            "SELECT * FROM job_alerts WHERE user_id = ? AND tenant_id = ? ORDER BY created_at DESC",
            [$userId, $tenantId]
        )->fetchAll();

        foreach ($alerts as &$alert) {
            $alert['id'] = (int)$alert['id'];
            $alert['user_id'] = (int)$alert['user_id'];
            $alert['tenant_id'] = (int)$alert['tenant_id'];
            $alert['is_active'] = (bool)$alert['is_active'];
            $alert['is_remote_only'] = (bool)$alert['is_remote_only'];
        }

        return $alerts;
    }

    /**
     * Check all active alerts against a newly posted vacancy
     * Called when a new job is created
     *
     * @param int $vacancyId
     */
    private static function checkJobAlertsForVacancy(int $vacancyId): void
    {
        // Non-critical — silently fail
        try {
            $tenantId = TenantContext::getId();

            $vacancy = Database::query(
                "SELECT * FROM job_vacancies WHERE id = ? AND tenant_id = ?",
                [$vacancyId, $tenantId]
            )->fetch();

            if (!$vacancy) {
                return;
            }

            $alerts = Database::query(
                "SELECT ja.* FROM job_alerts ja
                 WHERE ja.tenant_id = ? AND ja.is_active = 1",
                [$tenantId]
            )->fetchAll();

            foreach ($alerts as $alert) {
                if (self::alertMatchesVacancy($alert, $vacancy)) {
                    // Update last_notified_at
                    Database::query(
                        "UPDATE job_alerts SET last_notified_at = NOW() WHERE id = ?",
                        [$alert['id']]
                    );

                    // Create in-app notification if NotificationService exists
                    try {
                        if (class_exists('\Nexus\Services\NotificationService')) {
                            \Nexus\Services\NotificationService::create(
                                (int)$alert['user_id'],
                                'job_alert',
                                'New job matching your alert: ' . $vacancy['title'],
                                '/jobs/' . $vacancy['id'],
                                $tenantId
                            );
                        }
                    } catch (\Throwable $e) {
                        // Notifications are optional
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("JobVacancyService: Job alert check failed: " . get_class($e));
        }
    }

    /**
     * Check if an alert matches a vacancy
     *
     * @param array $alert
     * @param array $vacancy
     * @return bool
     */
    private static function alertMatchesVacancy(array $alert, array $vacancy): bool
    {
        // Type filter
        if (!empty($alert['type']) && $alert['type'] !== $vacancy['type']) {
            return false;
        }

        // Commitment filter
        if (!empty($alert['commitment']) && $alert['commitment'] !== $vacancy['commitment']) {
            return false;
        }

        // Remote filter
        if ($alert['is_remote_only'] && !$vacancy['is_remote']) {
            return false;
        }

        // Keywords
        if (!empty($alert['keywords'])) {
            $keywords = array_map('trim', explode(',', strtolower($alert['keywords'])));
            $searchText = strtolower($vacancy['title'] . ' ' . $vacancy['description'] . ' ' . ($vacancy['category'] ?? ''));
            $anyMatch = false;
            foreach ($keywords as $kw) {
                if (!empty($kw) && str_contains($searchText, $kw)) {
                    $anyMatch = true;
                    break;
                }
            }
            if (!$anyMatch) {
                return false;
            }
        }

        // Categories
        if (!empty($alert['categories']) && !empty($vacancy['category'])) {
            $alertCats = array_map('trim', explode(',', strtolower($alert['categories'])));
            if (!in_array(strtolower($vacancy['category']), $alertCats)) {
                return false;
            }
        }

        // Location
        if (!empty($alert['location']) && !empty($vacancy['location'])) {
            if (!str_contains(strtolower($vacancy['location']), strtolower($alert['location']))) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    // J7: JOB EXPIRY + RENEWAL
    // =========================================================================

    /**
     * Expire jobs past their deadline
     * Called by cron job
     *
     * @return int Number of jobs expired
     */
    public static function expireOverdueJobs(): int
    {
        try {
            $result = Database::query(
                "UPDATE job_vacancies
                 SET status = 'closed', expired_at = NOW()
                 WHERE status = 'open'
                 AND deadline IS NOT NULL
                 AND deadline < NOW()
                 AND expired_at IS NULL"
            );
            return $result->rowCount();
        } catch (\Throwable $e) {
            error_log("JobVacancyService: Failed to expire overdue jobs: " . get_class($e));
            return 0;
        }
    }

    /**
     * Get jobs expiring within N days (for renewal reminders)
     *
     * @param int $daysBeforeDeadline
     * @return array
     */
    public static function getJobsExpiringWithin(int $daysBeforeDeadline = 3): array
    {
        try {
            return Database::query(
                "SELECT jv.*, u.email as owner_email, u.first_name as owner_name
                 FROM job_vacancies jv
                 JOIN users u ON jv.user_id = u.id
                 WHERE jv.status = 'open'
                 AND jv.deadline IS NOT NULL
                 AND jv.deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
                 AND jv.expired_at IS NULL",
                [$daysBeforeDeadline]
            )->fetchAll();
        } catch (\Throwable $e) {
            error_log("JobVacancyService: Failed to get expiring jobs: " . get_class($e));
            return [];
        }
    }

    /**
     * Renew a job vacancy (extend deadline)
     *
     * @param int $jobId
     * @param int $userId
     * @param int $daysToExtend
     * @return bool
     */
    public static function renewJob(int $jobId, int $userId, int $daysToExtend = 30): bool
    {
        self::clearErrors();

        $vacancy = self::getById($jobId);
        if (!$vacancy) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Job vacancy not found');
            return false;
        }

        // Check ownership or admin
        if ((int)$vacancy['user_id'] !== $userId) {
            $user = Database::query("SELECT role FROM users WHERE id = ?", [$userId])->fetch();
            if (!$user || !in_array($user['role'], ['admin', 'super_admin'])) {
                self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'You can only renew your own job vacancies');
                return false;
            }
        }

        $tenantId = TenantContext::getId();

        try {
            // New deadline: from today + extension, or from current deadline + extension
            $baseDate = ($vacancy['deadline'] && strtotime($vacancy['deadline']) > time())
                ? $vacancy['deadline']
                : date('Y-m-d H:i:s');
            $newDeadline = date('Y-m-d H:i:s', strtotime($baseDate . " +{$daysToExtend} days"));

            Database::query(
                "UPDATE job_vacancies
                 SET deadline = ?, status = 'open', expired_at = NULL,
                     renewed_at = NOW(), renewal_count = renewal_count + 1
                 WHERE id = ? AND tenant_id = ?",
                [$newDeadline, $jobId, $tenantId]
            );

            return true;
        } catch (\Throwable $e) {
            error_log("JobVacancyService: Job renewal failed: " . get_class($e));
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to renew job');
            return false;
        }
    }

    // =========================================================================
    // J8: JOB ANALYTICS
    // =========================================================================

    /**
     * Get analytics for a job vacancy
     *
     * @param int $jobId
     * @param int $userId
     * @return array|null
     */
    public static function getAnalytics(int $jobId, int $userId): ?array
    {
        self::clearErrors();

        $vacancy = self::getById($jobId);
        if (!$vacancy) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Job vacancy not found');
            return null;
        }

        // Check ownership or admin
        if ((int)$vacancy['user_id'] !== $userId) {
            $user = Database::query("SELECT role FROM users WHERE id = ?", [$userId])->fetch();
            if (!$user || !in_array($user['role'], ['admin', 'super_admin'])) {
                self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Access denied');
                return null;
            }
        }

        $tenantId = TenantContext::getId();

        // Views over time (last 30 days, grouped by day)
        $viewsByDay = Database::query(
            "SELECT DATE(viewed_at) as date, COUNT(*) as count
             FROM job_vacancy_views
             WHERE vacancy_id = ? AND tenant_id = ?
             AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(viewed_at)
             ORDER BY date ASC",
            [$jobId, $tenantId]
        )->fetchAll();

        // Unique viewers
        $uniqueViewers = Database::query(
            "SELECT COUNT(DISTINCT COALESCE(user_id, ip_hash)) as count
             FROM job_vacancy_views
             WHERE vacancy_id = ? AND tenant_id = ?",
            [$jobId, $tenantId]
        )->fetch();

        // Application count by status (tenant-scoped via join)
        $applicationsByStatus = Database::query(
            "SELECT COALESCE(a.stage, a.status) as stage, COUNT(*) as count
             FROM job_vacancy_applications a
             JOIN job_vacancies jv ON a.vacancy_id = jv.id AND jv.tenant_id = ?
             WHERE a.vacancy_id = ?
             GROUP BY COALESCE(a.stage, a.status)",
            [$tenantId, $jobId]
        )->fetchAll();

        // Conversion rate (views to applications)
        $totalViews = (int)$vacancy['views_count'];
        $totalApps = (int)$vacancy['applications_count'];
        $conversionRate = $totalViews > 0 ? round(($totalApps / $totalViews) * 100, 1) : 0;

        // Average time to first application
        $avgTimeToApply = Database::query(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, jv.created_at, a.created_at)) as avg_hours
             FROM job_vacancy_applications a
             JOIN job_vacancies jv ON a.vacancy_id = jv.id
             WHERE a.vacancy_id = ?",
            [$jobId]
        )->fetch();

        // Time to fill (if filled)
        $timeToFill = null;
        if ($vacancy['status'] === 'filled') {
            $firstAccepted = Database::query(
                "SELECT MIN(reviewed_at) as accepted_at
                 FROM job_vacancy_applications
                 WHERE vacancy_id = ? AND status = 'accepted'",
                [$jobId]
            )->fetch();
            if ($firstAccepted && $firstAccepted['accepted_at']) {
                $timeToFill = (int)((strtotime($firstAccepted['accepted_at']) - strtotime($vacancy['created_at'])) / 86400);
            }
        }

        return [
            'job_id' => $jobId,
            'total_views' => $totalViews,
            'unique_viewers' => (int)($uniqueViewers['count'] ?? 0),
            'total_applications' => $totalApps,
            'conversion_rate' => $conversionRate,
            'avg_time_to_apply_hours' => $avgTimeToApply ? round((float)$avgTimeToApply['avg_hours'], 1) : null,
            'time_to_fill_days' => $timeToFill,
            'views_by_day' => $viewsByDay,
            'applications_by_stage' => $applicationsByStatus,
            'created_at' => $vacancy['created_at'],
            'status' => $vacancy['status'],
        ];
    }

    // =========================================================================
    // J10: FEATURED JOBS
    // =========================================================================

    /**
     * Feature a job vacancy (admin only)
     *
     * @param int $jobId
     * @param int $userId Admin user ID
     * @param int $durationDays How many days the job stays featured
     * @return bool
     */
    public static function featureJob(int $jobId, int $userId, int $durationDays = 7): bool
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        // Validate duration bounds
        $durationDays = max(1, min(90, $durationDays));

        // Admin check
        $user = Database::query("SELECT role FROM users WHERE id = ?", [$userId])->fetch();
        if (!$user || !in_array($user['role'], ['admin', 'super_admin'])) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can feature jobs');
            return false;
        }

        $job = Database::query(
            "SELECT id FROM job_vacancies WHERE id = ? AND tenant_id = ?",
            [$jobId, $tenantId]
        )->fetch();

        if (!$job) {
            self::addError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Job vacancy not found');
            return false;
        }

        try {
            $featuredUntil = date('Y-m-d H:i:s', strtotime("+{$durationDays} days"));
            Database::query(
                "UPDATE job_vacancies SET is_featured = 1, featured_until = ? WHERE id = ? AND tenant_id = ?",
                [$featuredUntil, $jobId, $tenantId]
            );
            return true;
        } catch (\Throwable $e) {
            error_log("JobVacancyService: Failed to feature job: " . get_class($e));
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to feature job');
            return false;
        }
    }

    /**
     * Unfeature a job vacancy (admin only)
     *
     * @param int $jobId
     * @param int $userId Admin user ID
     * @return bool
     */
    public static function unfeatureJob(int $jobId, int $userId): bool
    {
        self::clearErrors();
        $tenantId = TenantContext::getId();

        // Admin check
        $user = Database::query("SELECT role FROM users WHERE id = ?", [$userId])->fetch();
        if (!$user || !in_array($user['role'], ['admin', 'super_admin'])) {
            self::addError(ApiErrorCodes::RESOURCE_FORBIDDEN, 'Only admins can unfeature jobs');
            return false;
        }

        try {
            Database::query(
                "UPDATE job_vacancies SET is_featured = 0, featured_until = NULL WHERE id = ? AND tenant_id = ?",
                [$jobId, $tenantId]
            );
            return true;
        } catch (\Throwable $e) {
            error_log("JobVacancyService: Failed to unfeature job: " . get_class($e));
            self::addError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Failed to unfeature job');
            return false;
        }
    }

    /**
     * Auto-expire featured status for jobs past their featured_until date
     * Called by cron job
     *
     * @return int Number updated
     */
    public static function expireFeaturedJobs(): int
    {
        try {
            $result = Database::query(
                "UPDATE job_vacancies
                 SET is_featured = 0
                 WHERE is_featured = 1
                 AND featured_until IS NOT NULL
                 AND featured_until < NOW()"
            );
            return $result->rowCount();
        } catch (\Throwable $e) {
            error_log("JobVacancyService: Failed to expire featured jobs: " . get_class($e));
            return 0;
        }
    }
}
