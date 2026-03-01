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
 * and enrichment with creator info and application status.
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
    public static function getAll(array $filters = []): array
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

        // Search filter
        if ($search) {
            $where[] = "(jv.title LIKE ? OR jv.description LIKE ? OR jv.category LIKE ?)";
            $searchTerm = '%' . $search . '%';
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

        $whereClause = implode(' AND ', $where);
        $params[] = $limit + 1; // Fetch one extra to check for more

        $sql = "
            SELECT
                jv.*,
                u.first_name as creator_first_name,
                u.last_name as creator_last_name,
                u.avatar_url as creator_avatar,
                NULL as organization_name,
                NULL as organization_logo
            FROM job_vacancies jv
            LEFT JOIN users u ON jv.user_id = u.id
            WHERE {$whereClause}
            ORDER BY jv.created_at DESC, jv.id DESC
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

        // Enrich each vacancy
        foreach ($vacancies as &$vacancy) {
            $vacancy = self::enrichVacancy($vacancy);
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
                NULL as organization_name,
                NULL as organization_logo
            FROM job_vacancies jv
            LEFT JOIN users u ON jv.user_id = u.id
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

        // Check if user has applied
        if ($userId) {
            $application = Database::query(
                "SELECT id, status FROM job_vacancy_applications WHERE vacancy_id = ? AND user_id = ?",
                [$vacancy['id'], $userId]
            )->fetch();
            $vacancy['has_applied'] = !empty($application);
            $vacancy['application_status'] = $application ? $application['status'] : null;
        } else {
            $vacancy['has_applied'] = false;
            $vacancy['application_status'] = null;
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

        if ($deadline) {
            $deadlineTs = strtotime($deadline);
            if ($deadlineTs === false) {
                self::addError(ApiErrorCodes::VALIDATION_INVALID_VALUE, 'Invalid deadline format', 'deadline');
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
                     contact_email, contact_phone, deadline, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $tenantId, $userId, $organizationId, $title, $description, $location, $isRemote,
                    $type, $commitment, $category, $skillsRequired, $hoursPerWeek, $timeCredits,
                    $contactEmail, $contactPhone, $deadline, $status
                ]
            );

            $vacancyId = Database::lastInsertId();

            // Award gamification points
            try {
                if (class_exists('\Nexus\Models\Gamification')) {
                    \Nexus\Models\Gamification::awardPoints($userId, 10, 'Posted a job vacancy');
                }
            } catch (\Throwable $e) {
                // Gamification is optional
            }

            return (int)$vacancyId;
        } catch (\Throwable $e) {
            error_log("Job vacancy creation failed: " . $e->getMessage());
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
            error_log("Job vacancy update failed: " . $e->getMessage());
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

            return true;
        } catch (\Throwable $e) {
            error_log("Job vacancy deletion failed: " . $e->getMessage());
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
            Database::query(
                "INSERT INTO job_vacancy_applications (vacancy_id, user_id, message, status, created_at)
                 VALUES (?, ?, ?, 'pending', NOW())",
                [$vacancyId, $userId, $message ? trim($message) : null]
            );

            $applicationId = Database::lastInsertId();

            // Increment applications count
            Database::query(
                "UPDATE job_vacancies SET applications_count = applications_count + 1 WHERE id = ?",
                [$vacancyId]
            );

            // Award gamification points
            try {
                if (class_exists('\Nexus\Models\Gamification')) {
                    \Nexus\Models\Gamification::awardPoints($userId, 3, 'Applied to a job vacancy');
                }
            } catch (\Throwable $e) {
                // Gamification is optional
            }

            return (int)$applicationId;
        } catch (\Throwable $e) {
            error_log("Job application failed: " . $e->getMessage());
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
    public static function updateApplicationStatus(int $applicationId, int $userId, string $status, ?string $notes = null): bool
    {
        self::clearErrors();

        if (!in_array($status, ['reviewed', 'accepted', 'rejected'])) {
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

        try {
            Database::query(
                "UPDATE job_vacancy_applications
                 SET status = ?, reviewer_notes = ?, reviewed_by = ?, reviewed_at = NOW()
                 WHERE id = ?",
                [$status, $notes ? trim($notes) : null, $userId, $applicationId]
            );

            return true;
        } catch (\Throwable $e) {
            error_log("Application status update failed: " . $e->getMessage());
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

        if ($status && in_array($status, ['pending', 'reviewed', 'accepted', 'rejected', 'withdrawn'])) {
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
     * Increment the views count for a vacancy
     *
     * @param int $id
     */
    public static function incrementViews(int $id): void
    {
        $tenantId = TenantContext::getId();
        try {
            Database::query(
                "UPDATE job_vacancies SET views_count = views_count + 1 WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
        } catch (\Throwable $e) {
            // Non-critical — log and continue
            error_log("Failed to increment job vacancy views: " . $e->getMessage());
        }
    }
}
