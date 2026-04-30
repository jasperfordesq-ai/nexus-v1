<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\CaringCommunity\MunicipalityFeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * AdminMunicipalityFeedbackController — AG92 admin endpoints.
 *
 * Routes (registered by orchestrator in routes/api.php):
 *   GET    /v2/admin/caring-community/feedback              => index
 *   GET    /v2/admin/caring-community/feedback/dashboard    => dashboard
 *   GET    /v2/admin/caring-community/feedback/export.csv   => exportCsv
 *   GET    /v2/admin/caring-community/feedback/{id}         => show
 *   PUT    /v2/admin/caring-community/feedback/{id}/triage  => triage
 *   POST   /v2/admin/caring-community/feedback/{id}/resolve => resolve
 *   POST   /v2/admin/caring-community/feedback/{id}/close   => close
 */
class AdminMunicipalityFeedbackController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(private readonly MunicipalityFeedbackService $service)
    {
    }

    private function ensureCaringCommunity(): ?JsonResponse
    {
        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError(
                'FEATURE_DISABLED',
                __('api.service_unavailable'),
                null,
                403,
            );
        }
        return null;
    }

    /**
     * GET /v2/admin/caring-community/feedback
     */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        if ($r = $this->ensureCaringCommunity()) {
            return $r;
        }

        $tenantId = TenantContext::getId();

        $status = $this->query('status');
        $category = $this->query('category');
        $subRegionId = $this->query('sub_region_id');
        $page = $this->queryInt('page', 1, 1) ?? 1;
        $perPage = $this->queryInt('per_page', 25, 1, 200) ?? 25;

        $result = $this->service->listForAdmin(
            $tenantId,
            is_string($status) ? $status : null,
            is_string($category) ? $category : null,
            is_scalar($subRegionId) ? (string) $subRegionId : null,
            $page,
            $perPage,
        );

        return $this->respondWithPaginatedCollection(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page'],
        );
    }

    /**
     * GET /v2/admin/caring-community/feedback/{id}
     */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();
        if ($r = $this->ensureCaringCommunity()) {
            return $r;
        }

        $tenantId = TenantContext::getId();

        $row = $this->service->show($tenantId, $id, true);
        if (!$row) {
            return $this->respondNotFound(__('api.not_found'));
        }

        return $this->respondWithData($row);
    }

    /**
     * PUT /v2/admin/caring-community/feedback/{id}/triage
     */
    public function triage(int $id): JsonResponse
    {
        $this->requireAdmin();
        if ($r = $this->ensureCaringCommunity()) {
            return $r;
        }

        $tenantId = TenantContext::getId();

        $payload = [];
        foreach (['status', 'assigned_user_id', 'assigned_role', 'triage_notes'] as $key) {
            if (request()->has($key)) {
                $payload[$key] = $this->input($key);
            }
        }

        $result = $this->service->triage($tenantId, $id, $payload);

        if (isset($result['errors'])) {
            $code = (string) ($result['errors'][0]['code'] ?? '');
            $status = $code === 'NOT_FOUND' ? 404 : 422;
            return $this->respondWithErrors($result['errors'], $status);
        }

        return $this->respondWithData($result['feedback'] ?? null);
    }

    /**
     * POST /v2/admin/caring-community/feedback/{id}/resolve
     */
    public function resolve(int $id): JsonResponse
    {
        $this->requireAdmin();
        if ($r = $this->ensureCaringCommunity()) {
            return $r;
        }

        $tenantId = TenantContext::getId();

        $notes = (string) ($this->input('resolution_notes', '') ?? '');

        $result = $this->service->resolve($tenantId, $id, $notes);

        if (isset($result['errors'])) {
            $code = (string) ($result['errors'][0]['code'] ?? '');
            $status = $code === 'NOT_FOUND' ? 404 : 422;
            return $this->respondWithErrors($result['errors'], $status);
        }

        return $this->respondWithData($result['feedback'] ?? null);
    }

    /**
     * POST /v2/admin/caring-community/feedback/{id}/close
     */
    public function close(int $id): JsonResponse
    {
        $this->requireAdmin();
        if ($r = $this->ensureCaringCommunity()) {
            return $r;
        }

        $tenantId = TenantContext::getId();

        $result = $this->service->close($tenantId, $id);

        if (isset($result['errors'])) {
            $code = (string) ($result['errors'][0]['code'] ?? '');
            $status = $code === 'NOT_FOUND' ? 404 : 422;
            return $this->respondWithErrors($result['errors'], $status);
        }

        return $this->respondWithData($result['feedback'] ?? null);
    }

    /**
     * GET /v2/admin/caring-community/feedback/dashboard
     */
    public function dashboard(): JsonResponse
    {
        $this->requireAdmin();
        if ($r = $this->ensureCaringCommunity()) {
            return $r;
        }

        $tenantId = TenantContext::getId();

        return $this->respondWithData($this->service->dashboardStats($tenantId));
    }

    /**
     * GET /v2/admin/caring-community/feedback/export.csv
     */
    public function exportCsv(): Response
    {
        $this->requireAdmin();
        if ($r = $this->ensureCaringCommunity()) {
            return $r;
        }

        $tenantId = TenantContext::getId();

        $status = $this->query('status');
        $category = $this->query('category');

        $csv = $this->service->exportCsv(
            $tenantId,
            is_string($status) ? $status : null,
            is_string($category) ? $category : null,
        );

        return response($csv, 200, [
            'Content-Type'        => 'application/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="municipality-feedback-export.csv"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
