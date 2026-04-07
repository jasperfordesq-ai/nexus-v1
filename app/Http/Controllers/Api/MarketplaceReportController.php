<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\MarketplaceReportService;
use Illuminate\Http\JsonResponse;

/**
 * MarketplaceReportController — DSA notice-and-action reporting (MKT6).
 *
 * Endpoints:
 *   POST /v2/marketplace/listings/{id}/report  — user submits a report (auth)
 *   GET  /v2/marketplace/listings/{id}/reports  — reports for a listing (admin)
 */
class MarketplaceReportController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * Ensure the marketplace feature is enabled for the current tenant.
     */
    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('marketplace')) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('FEATURE_DISABLED', __('api_controllers_2.marketplace_report.feature_disabled'), null, 403)
            );
        }
    }

    // -----------------------------------------------------------------
    //  POST /v2/marketplace/listings/{id}/report
    // -----------------------------------------------------------------

    /**
     * Submit a report against a marketplace listing.
     *
     * Requires authentication. The reporter cannot report their own listing.
     * Duplicate active reports from the same user are prevented.
     */
    public function store(int $id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $this->rateLimit('marketplace_report', 5, 60);

        $validated = request()->validate([
            'reason' => 'required|string|in:counterfeit,illegal,unsafe,misleading,discrimination,ip_violation,other',
            'description' => 'required|string|max:5000',
            'evidence_urls' => 'nullable|array|max:10',
            'evidence_urls.*' => 'url|max:2000',
        ]);

        try {
            $report = MarketplaceReportService::createReport($userId, $id, $validated);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }

        return $this->respondWithData([
            'id' => $report->id,
            'status' => $report->status,
            'message' => __('api_controllers_2.marketplace_report.report_submitted'),
        ], null, 201);
    }

    // -----------------------------------------------------------------
    //  GET /v2/marketplace/listings/{id}/reports
    // -----------------------------------------------------------------

    /**
     * Get all reports for a specific listing (admin only).
     */
    public function index(int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();
        $this->rateLimit('admin_marketplace_reports', 30, 60);

        $reports = MarketplaceReportService::getReportsForListing($id);

        return $this->respondWithData($reports);
    }
}
