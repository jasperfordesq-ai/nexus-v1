<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\FadpComplianceService;
use App\Core\TenantContext;

/**
 * FadpComplianceController — AG42 Swiss FADP/nDSG compliance endpoints.
 *
 * Member routes  : GET/POST /v2/me/fadp/*
 * Admin routes   : GET/PUT/POST/DELETE /v2/admin/fadp/*
 */
class FadpComplianceController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly FadpComplianceService $service,
    ) {}

    // =========================================================================
    // MEMBER ENDPOINTS
    // =========================================================================

    /**
     * GET /v2/me/fadp/consent-history
     *
     * Returns the authenticated member's own consent history.
     */
    public function myConsentHistory(): JsonResponse
    {
        $userId   = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if (! FadpComplianceService::isAvailable()) {
            return $this->respondWithData([]);
        }

        $history = FadpComplianceService::getConsentHistory($userId, $tenantId);

        return $this->respondWithData($history);
    }

    /**
     * POST /v2/me/fadp/consent
     *
     * Record a consent grant or withdrawal for the authenticated member.
     *
     * Body:
     *   - consent_type: string (required) — e.g. 'profiling', 'ai_matching'
     *   - action:       string (required) — 'granted' | 'withdrawn'
     */
    public function recordConsent(): JsonResponse
    {
        $userId   = $this->requireAuth();
        $tenantId = TenantContext::getId();

        $consentType = trim((string) ($this->input('consent_type') ?? ''));
        $action      = trim((string) ($this->input('action') ?? ''));

        if ($consentType === '') {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                __('api.missing_required_field', ['field' => 'consent_type']),
                'consent_type',
                400
            );
        }

        if (! in_array($action, ['granted', 'withdrawn'], true)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'action must be one of: granted, withdrawn',
                'action',
                400
            );
        }

        if (! FadpComplianceService::isAvailable()) {
            return $this->respondWithError('SERVICE_UNAVAILABLE', 'FADP compliance tables not yet migrated.', null, 503);
        }

        $request = app('request');
        $meta    = [
            'ip_address'      => $request->ip(),
            'user_agent'      => $request->userAgent(),
            'consent_version' => $this->input('consent_version'),
        ];

        FadpComplianceService::recordConsent($userId, $tenantId, $consentType, $action, $meta);

        return $this->respondWithData([
            'recorded'     => true,
            'consent_type' => $consentType,
            'action'       => $action,
        ]);
    }

    // =========================================================================
    // ADMIN ENDPOINTS — RETENTION CONFIG
    // =========================================================================

    /**
     * GET /v2/admin/fadp/retention-config
     */
    public function getRetentionConfig(): JsonResponse
    {
        $this->requireAuth();
        $this->requirePermission('admin');
        $tenantId = TenantContext::getId();

        if (! FadpComplianceService::isAvailable()) {
            return $this->respondWithData([]);
        }

        return $this->respondWithData(FadpComplianceService::getRetentionConfig($tenantId));
    }

    /**
     * PUT /v2/admin/fadp/retention-config
     *
     * Body:
     *   - config:            object  — {member_data_years, transaction_data_years, ...}
     *   - data_residency:    string  — 'Switzerland'|'EU'|'International'
     *   - dpa_contact_email: string? — DPA officer e-mail
     */
    public function updateRetentionConfig(): JsonResponse
    {
        $this->requireAuth();
        $this->requirePermission('admin');
        $tenantId = TenantContext::getId();

        $config          = $this->input('config');
        $dataResidency   = $this->input('data_residency') ?? 'EU';
        $dpaContactEmail = $this->input('dpa_contact_email');

        if (! in_array($dataResidency, ['Switzerland', 'EU', 'International'], true)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'data_residency must be Switzerland, EU, or International',
                'data_residency',
                400
            );
        }

        if (! FadpComplianceService::isAvailable()) {
            return $this->respondWithError('SERVICE_UNAVAILABLE', 'FADP compliance tables not yet migrated.', null, 503);
        }

        FadpComplianceService::updateRetentionConfig($tenantId, [
            'config'           => $config ?? [],
            'data_residency'   => $dataResidency,
            'dpa_contact_email' => $dpaContactEmail,
        ]);

        return $this->respondWithData(FadpComplianceService::getRetentionConfig($tenantId));
    }

    // =========================================================================
    // ADMIN ENDPOINTS — PROCESSING ACTIVITIES
    // =========================================================================

    /**
     * GET /v2/admin/fadp/processing-activities
     */
    public function getProcessingActivities(): JsonResponse
    {
        $this->requireAuth();
        $this->requirePermission('admin');
        $tenantId = TenantContext::getId();

        if (! FadpComplianceService::isAvailable()) {
            return $this->respondWithData([]);
        }

        return $this->respondWithData(FadpComplianceService::getProcessingActivities($tenantId));
    }

    /**
     * POST /v2/admin/fadp/processing-activities
     *
     * Body:
     *   - id:                    int?    — if present, updates existing activity
     *   - activity_name:         string  (required)
     *   - purpose:               string  (required)
     *   - legal_basis:           string  (required) — consent|contract|legal_obligation|legitimate_interest
     *   - data_categories:       array
     *   - recipients:            array?
     *   - retention_period:      string
     *   - is_automated_profiling: bool
     *   - sort_order:            int
     */
    public function upsertProcessingActivity(): JsonResponse
    {
        $this->requireAuth();
        $this->requirePermission('admin');
        $tenantId = TenantContext::getId();

        $activityName = trim((string) ($this->input('activity_name') ?? ''));
        $purpose      = trim((string) ($this->input('purpose') ?? ''));
        $legalBasis   = trim((string) ($this->input('legal_basis') ?? ''));

        if ($activityName === '') {
            return $this->respondWithError('VALIDATION_ERROR', 'activity_name is required', 'activity_name', 400);
        }
        if ($purpose === '') {
            return $this->respondWithError('VALIDATION_ERROR', 'purpose is required', 'purpose', 400);
        }

        $validBases = ['consent', 'contract', 'legal_obligation', 'legitimate_interest'];
        if (! in_array($legalBasis, $validBases, true)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'legal_basis must be one of: ' . implode(', ', $validBases),
                'legal_basis',
                400
            );
        }

        if (! FadpComplianceService::isAvailable()) {
            return $this->respondWithError('SERVICE_UNAVAILABLE', 'FADP compliance tables not yet migrated.', null, 503);
        }

        $data = [
            'id'                     => $this->input('id'),
            'activity_name'          => $activityName,
            'purpose'                => $purpose,
            'legal_basis'            => $legalBasis,
            'data_categories'        => $this->input('data_categories') ?? [],
            'recipients'             => $this->input('recipients'),
            'retention_period'       => (string) ($this->input('retention_period') ?? ''),
            'is_automated_profiling' => (bool) ($this->input('is_automated_profiling') ?? false),
            'sort_order'             => (int) ($this->input('sort_order') ?? 0),
        ];

        $activity = FadpComplianceService::upsertProcessingActivity($tenantId, $data);

        return $this->respondWithData($activity);
    }

    /**
     * DELETE /v2/admin/fadp/processing-activities/{id}
     */
    public function deleteProcessingActivity(int $id): JsonResponse
    {
        $this->requireAuth();
        $this->requirePermission('admin');
        $tenantId = TenantContext::getId();

        if (! FadpComplianceService::isAvailable()) {
            return $this->respondWithError('SERVICE_UNAVAILABLE', 'FADP compliance tables not yet migrated.', null, 503);
        }

        FadpComplianceService::deleteProcessingActivity($id, $tenantId);

        return $this->respondWithData(['deleted' => true, 'id' => $id]);
    }

    // =========================================================================
    // ADMIN ENDPOINTS — CONSENT LEDGER & REGISTER
    // =========================================================================

    /**
     * GET /v2/admin/fadp/consent-ledger
     *
     * Returns all consent records as a JSON array (for CSV download).
     */
    public function exportConsentLedger(): JsonResponse
    {
        $this->requireAuth();
        $this->requirePermission('admin');
        $tenantId = TenantContext::getId();

        if (! FadpComplianceService::isAvailable()) {
            return $this->respondWithData([]);
        }

        return $this->respondWithData(FadpComplianceService::exportConsentLedger($tenantId));
    }

    /**
     * GET /v2/admin/fadp/processing-register
     *
     * Returns the full processing register (register + retention + residency).
     */
    public function processingRegister(): JsonResponse
    {
        $this->requireAuth();
        $this->requirePermission('admin');
        $tenantId = TenantContext::getId();

        if (! FadpComplianceService::isAvailable()) {
            return $this->respondWithData([]);
        }

        return $this->respondWithData(FadpComplianceService::generateProcessingRegister($tenantId));
    }
}
