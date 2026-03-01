<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\TenantContext;
use Nexus\Services\JobVacancyService;

/**
 * Admin Jobs API Controller
 *
 * GET    /api/v2/admin/jobs              - List all job vacancies
 * GET    /api/v2/admin/jobs/{id}         - Job detail
 * DELETE /api/v2/admin/jobs/{id}         - Delete job
 * POST   /api/v2/admin/jobs/{id}/feature - Feature a job
 * POST   /api/v2/admin/jobs/{id}/unfeature - Unfeature a job
 */
class AdminJobsApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function index(): void
    {
        $this->requireAdmin();

        $filters = [
            'status' => $this->query('status'),
            'search' => $this->query('search'),
            'page' => max(1, $this->queryInt('page', 1)),
            'limit' => min(200, max(1, $this->queryInt('limit', 50))),
        ];

        $result = JobVacancyService::getAll($filters);

        $items = $result['data'] ?? $result['items'] ?? $result;
        $total = $result['total'] ?? (is_array($items) ? count($items) : 0);
        $page = $filters['page'];
        $limit = $filters['limit'];

        if (is_array($items) && !isset($result['total'])) {
            $offset = ($page - 1) * $limit;
            $paged = array_slice($items, $offset, $limit);
            $this->respondWithPaginatedCollection($paged, count($items), $page, $limit);
        } else {
            $this->respondWithPaginatedCollection($items, $total, $page, $limit);
        }
    }

    public function show(int $id): void
    {
        $this->requireAdmin();

        $job = JobVacancyService::getById($id);

        if (!$job) {
            $this->respondWithError('NOT_FOUND', 'Job not found', null, 404);
            return;
        }

        $this->respondWithData($job);
    }

    public function destroy(int $id): void
    {
        $adminId = $this->requireAdmin();

        $job = JobVacancyService::getById($id);
        if (!$job) {
            $this->respondWithError('NOT_FOUND', 'Job not found', null, 404);
            return;
        }

        $deleted = JobVacancyService::delete($id, $adminId);

        if ($deleted) {
            $this->respondWithData(['deleted' => true, 'id' => $id]);
        } else {
            $this->respondWithError('DELETE_FAILED', 'Failed to delete job', null, 400);
        }
    }

    public function feature(int $id): void
    {
        $adminId = $this->requireAdmin();

        $days = $this->inputInt('duration_days', 7, 1, 90);
        $featured = JobVacancyService::featureJob($id, $adminId, $days);

        if ($featured) {
            $this->respondWithData(['featured' => true, 'id' => $id, 'duration_days' => $days]);
        } else {
            $this->respondWithError('FEATURE_FAILED', 'Failed to feature job', null, 400);
        }
    }

    public function unfeature(int $id): void
    {
        $adminId = $this->requireAdmin();

        $unfeatured = JobVacancyService::unfeatureJob($id, $adminId);

        if ($unfeatured) {
            $this->respondWithData(['featured' => false, 'id' => $id]);
        } else {
            $this->respondWithError('UNFEATURE_FAILED', 'Failed to unfeature job', null, 400);
        }
    }
}
