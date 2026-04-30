<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\CaringCommunity\TenantDataQualityService;
use Illuminate\Http\JsonResponse;

/**
 * AG84 — Tenant Data-Quality and Seed-to-Real Migration controller.
 *
 * Surfaces a read-only readiness checklist a Caring Community coordinator
 * runs BEFORE onboarding real residents. Each check counts the number of
 * affected rows in tenant-scoped data and assigns a severity (info / warning /
 * danger). The drill-down endpoint returns up to 50 affected rows for the
 * checks where a human review is appropriate.
 *
 * Both endpoints are admin-only and require the caring_community feature on
 * the current tenant.
 */
class TenantDataQualityController extends BaseApiController
{
    /** @var bool */
    protected bool $isV2Api = true;

    /** Whitelist of valid drill-down check keys. */
    private const ALLOWED_DRILLDOWN_KEYS = [
        'duplicate_emails',
        'duplicate_phones',
        'missing_preferred_language',
        'missing_sub_region',
        'missing_coordinator_assignment',
        'unverified_organisations',
        'seed_marker_users',
        'unanswered_help_requests',
        'members_without_role',
        'tenant_setting_completeness',
    ];

    public function __construct(
        private readonly TenantDataQualityService $service
    ) {
    }

    /**
     * GET /v2/admin/caring-community/data-quality/dashboard
     */
    public function dashboard(): JsonResponse
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }

        $report = $this->service->runChecks(TenantContext::getId());

        return $this->respondWithData($report);
    }

    /**
     * GET /v2/admin/caring-community/data-quality/checks/{checkKey}/rows
     */
    public function affectedRows(string $checkKey): JsonResponse
    {
        $denied = $this->guard();
        if ($denied !== null) {
            return $denied;
        }

        if (!in_array($checkKey, self::ALLOWED_DRILLDOWN_KEYS, true)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'Unknown data-quality check key.',
                'check_key',
                422
            );
        }

        $limit = $this->queryInt('limit', 50, 1, 200) ?? 50;

        $payload = $this->service->affectedRows(
            TenantContext::getId(),
            $checkKey,
            $limit
        );

        return $this->respondWithData([
            'check_key' => $checkKey,
            'limit'     => $limit,
            'rows'      => $payload['rows'],
            'note'      => $payload['note'] ?? null,
        ]);
    }

    /**
     * Combined admin + caring_community feature guard.
     * Returns a JsonResponse on rejection, null on success.
     */
    private function guard(): ?JsonResponse
    {
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError(
                'FEATURE_DISABLED',
                __('api.service_unavailable'),
                null,
                403
            );
        }

        return null;
    }
}

/*
 * Routes to register in routes/api.php:
 *   GET /v2/admin/caring-community/data-quality/dashboard => dashboard
 *   GET /v2/admin/caring-community/data-quality/checks/{checkKey}/rows => affectedRows
 */
