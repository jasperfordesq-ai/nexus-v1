<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\JobBiasAuditService;
use App\Services\JobModerationService;
use App\Services\JobSpamDetectionService;
use App\Services\JobVacancyService;
use App\Core\TenantContext;

/**
 * AdminJobsController -- Admin job vacancy management.
 *
 * All methods require admin authentication.
 */
class AdminJobsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly JobVacancyService $jobVacancyService,
        private readonly JobBiasAuditService $biasAuditService,
    ) {}

    /** GET /api/v2/admin/jobs */
    public function index(): JsonResponse
    {
        $this->requireAdmin();

        $page = $this->queryInt('page', 1, 1);
        $limit = $this->queryInt('limit', 50, 1, 200);

        $filters = ['limit' => 10000];
        if ($this->query('status')) $filters['status'] = $this->query('status');
        if ($this->query('search')) $filters['search'] = $this->query('search');

        $result = $this->jobVacancyService->getAll($filters);
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
        $job = $this->jobVacancyService->getById($id);
        if (!$job) return $this->respondWithError('NOT_FOUND', 'Job not found', null, 404);
        return $this->respondWithData($job);
    }

    /** DELETE /api/v2/admin/jobs/{id} */
    public function destroy(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $job = $this->jobVacancyService->getById($id);
        if (!$job) return $this->respondWithError('NOT_FOUND', 'Job not found', null, 404);

        $deleted = $this->jobVacancyService->delete($id, $adminId);
        if ($deleted) return $this->respondWithData(['deleted' => true, 'id' => $id]);
        return $this->respondWithError('DELETE_FAILED', 'Failed to delete job', null, 400);
    }

    /** POST /api/v2/admin/jobs/{id}/feature */
    public function feature(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $days = $this->inputInt('duration_days', 7, 1, 90);

        $featured = $this->jobVacancyService->featureJob($id, $adminId, $days);
        if ($featured) return $this->respondWithData(['featured' => true, 'id' => $id, 'duration_days' => $days]);
        return $this->respondWithError('FEATURE_FAILED', 'Failed to feature job', null, 400);
    }

    /** POST /api/v2/admin/jobs/{id}/unfeature */
    public function unfeature(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $unfeatured = $this->jobVacancyService->unfeatureJob($id, $adminId);
        if ($unfeatured) return $this->respondWithData(['featured' => false, 'id' => $id]);
        return $this->respondWithError('UNFEATURE_FAILED', 'Failed to unfeature job', null, 400);
    }

    /** GET /api/v2/admin/jobs/{id}/applications */
    public function getApplications(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $job = $this->jobVacancyService->getById($id);
        if (!$job) return $this->respondWithError('NOT_FOUND', 'Job not found', null, 404);

        $applications = $this->jobVacancyService->getApplications($id, $adminId);
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

        $updated = $this->jobVacancyService->updateApplicationStatus($id, $adminId, $status, $notes);
        if ($updated) return $this->respondWithData(['updated' => true, 'id' => $id, 'status' => $status]);

        $errors = $this->jobVacancyService->getErrors();
        $first = $errors[0] ?? [];
        return $this->respondWithError($first['code'] ?? 'UPDATE_FAILED', $first['message'] ?? 'Failed to update', null, 400);
    }

    // =====================================================================
    // MODERATION QUEUE (Agent B)
    // =====================================================================

    /** GET /api/v2/admin/jobs/moderation-queue */
    public function moderationQueue(): JsonResponse
    {
        $this->requireAdmin();

        $tenantId = TenantContext::getId();
        $limit = $this->queryInt('limit', 50, 1, 200);
        $offset = $this->queryInt('offset', 0, 0);

        $result = JobModerationService::getPendingJobs($tenantId, $limit, $offset);

        return $this->respondWithData($result);
    }

    /** POST /api/v2/admin/jobs/{id}/approve */
    public function approve(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $notes = $this->input('notes');

        $approved = JobModerationService::approveJob($id, $adminId, $notes);

        if ($approved) {
            return $this->respondWithData([
                'approved' => true,
                'id' => $id,
                'message' => 'Job approved and published successfully',
            ]);
        }

        return $this->respondWithError('APPROVE_FAILED', 'Failed to approve job — job not found or already processed', null, 400);
    }

    /** POST /api/v2/admin/jobs/{id}/reject */
    public function reject(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $reason = $this->input('reason');

        if (empty($reason)) {
            return $this->respondWithError('VALIDATION_REQUIRED', 'A reason is required when rejecting a job', 'reason', 422);
        }

        $rejected = JobModerationService::rejectJob($id, $adminId, $reason);

        if ($rejected) {
            return $this->respondWithData([
                'rejected' => true,
                'id' => $id,
                'message' => 'Job rejected successfully',
            ]);
        }

        return $this->respondWithError('REJECT_FAILED', 'Failed to reject job — job not found or already processed', null, 400);
    }

    /** POST /api/v2/admin/jobs/{id}/flag */
    public function flag(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $reason = $this->input('reason');

        if (empty($reason)) {
            return $this->respondWithError('VALIDATION_REQUIRED', 'A reason is required when flagging a job', 'reason', 422);
        }

        $flagged = JobModerationService::flagJob($id, $adminId, $reason);

        if ($flagged) {
            return $this->respondWithData([
                'flagged' => true,
                'id' => $id,
                'message' => 'Job flagged for further review',
            ]);
        }

        return $this->respondWithError('FLAG_FAILED', 'Failed to flag job — job not found', null, 400);
    }

    /** GET /api/v2/admin/jobs/moderation-stats */
    public function moderationStats(): JsonResponse
    {
        $this->requireAdmin();

        $tenantId = TenantContext::getId();
        $stats = JobModerationService::getModerationStats($tenantId);

        return $this->respondWithData($stats);
    }

    // =====================================================================
    // SPAM DETECTION STATS (Agent B)
    // =====================================================================

    /** GET /api/v2/admin/jobs/spam-stats */
    public function spamStats(): JsonResponse
    {
        $this->requireAdmin();

        $tenantId = TenantContext::getId();
        $stats = JobSpamDetectionService::getSpamStats($tenantId);

        return $this->respondWithData($stats);
    }

    // =====================================================================
    // BIAS AUDIT (Agent D)
    // =====================================================================

    /**
     * GET /api/v2/admin/jobs/bias-audit — Generate hiring bias audit report.
     *
     * Query params:
     *   job_id    (int, optional) — filter to a specific job
     *   date_from (string, optional) — start date (Y-m-d), defaults to 12 months ago
     *   date_to   (string, optional) — end date (Y-m-d), defaults to today
     */
    public function biasAudit(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $jobId = $this->query('job_id') ? $this->queryInt('job_id') : null;
        $dateFrom = $this->query('date_from');
        $dateTo = $this->query('date_to');

        $report = $this->biasAuditService->generateReport($tenantId, $jobId, $dateFrom, $dateTo);

        return $this->respondWithData($report);
    }
}
