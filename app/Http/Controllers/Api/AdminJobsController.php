<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Nexus\Services\JobVacancyService;

/**
 * AdminJobsController -- Admin job vacancy management.
 *
 * All methods require admin authentication.
 */
class AdminJobsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /** GET /api/v2/admin/jobs */
    public function index(): JsonResponse
    {
        $this->requireAdmin();

        $page = $this->queryInt('page', 1, 1);
        $limit = $this->queryInt('limit', 50, 1, 200);

        $filters = ['limit' => 10000];
        if ($this->query('status')) $filters['status'] = $this->query('status');
        if ($this->query('search')) $filters['search'] = $this->query('search');

        $result = JobVacancyService::getAll($filters);
        $allItems = $result['items'] ?? [];

        $total = count($allItems);
        $offset = ($page - 1) * $limit;
        $paged = array_slice($allItems, $offset, $limit);

        return $this->respondWithPaginatedCollection($paged, $total, $page, $limit);
    }

    /** GET /api/v2/admin/jobs/{id} */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();
        $job = JobVacancyService::getById($id);
        if (!$job) return $this->respondWithError('NOT_FOUND', 'Job not found', null, 404);
        return $this->respondWithData($job);
    }

    /** DELETE /api/v2/admin/jobs/{id} */
    public function destroy(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $job = JobVacancyService::getById($id);
        if (!$job) return $this->respondWithError('NOT_FOUND', 'Job not found', null, 404);

        $deleted = JobVacancyService::delete($id, $adminId);
        if ($deleted) return $this->respondWithData(['deleted' => true, 'id' => $id]);
        return $this->respondWithError('DELETE_FAILED', 'Failed to delete job', null, 400);
    }

    /** POST /api/v2/admin/jobs/{id}/feature */
    public function feature(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $days = $this->inputInt('duration_days', 7, 1, 90);

        $featured = JobVacancyService::featureJob($id, $adminId, $days);
        if ($featured) return $this->respondWithData(['featured' => true, 'id' => $id, 'duration_days' => $days]);
        return $this->respondWithError('FEATURE_FAILED', 'Failed to feature job', null, 400);
    }

    /** POST /api/v2/admin/jobs/{id}/unfeature */
    public function unfeature(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $unfeatured = JobVacancyService::unfeatureJob($id, $adminId);
        if ($unfeatured) return $this->respondWithData(['featured' => false, 'id' => $id]);
        return $this->respondWithError('UNFEATURE_FAILED', 'Failed to unfeature job', null, 400);
    }

    /** GET /api/v2/admin/jobs/{id}/applications */
    public function getApplications(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $job = JobVacancyService::getById($id);
        if (!$job) return $this->respondWithError('NOT_FOUND', 'Job not found', null, 404);

        $applications = JobVacancyService::getApplications($id, $adminId);
        if ($applications === null) return $this->respondWithError('FETCH_FAILED', 'Failed to load applications', null, 400);
        return $this->respondWithData($applications);
    }

    /** PUT /api/v2/admin/jobs/{id}/app-status */
    public function updateApplicationStatus(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $status = $this->input('status');
        $notes = $this->input('notes');

        if (!$status) return $this->respondWithError('VALIDATION_REQUIRED', 'Status is required', 'status', 422);

        $updated = JobVacancyService::updateApplicationStatus($id, $adminId, $status, $notes);
        if ($updated) return $this->respondWithData(['updated' => true, 'id' => $id, 'status' => $status]);

        $errors = JobVacancyService::getErrors();
        $first = $errors[0] ?? [];
        return $this->respondWithError($first['code'] ?? 'UPDATE_FAILED', $first['message'] ?? 'Failed to update', null, 400);
    }
}
