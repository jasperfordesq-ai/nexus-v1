<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CaringCommunity\ResidencyVerificationService;
use Illuminate\Http\JsonResponse;

/**
 * AG43 — Citizen residency verification for KISS cooperative catchments.
 */
class ResidencyVerificationController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly ResidencyVerificationService $service,
    ) {
    }

    /**
     * GET /api/v2/me/residency-verification
     */
    public function myStatus(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        $guard = $this->guardAvailability();
        if ($guard !== null) {
            return $guard;
        }

        return $this->respondWithData($this->service->statusForUser($tenantId, $userId));
    }

    /**
     * POST /api/v2/me/residency-verification
     */
    public function submitDeclaration(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        $guard = $this->guardAvailability();
        if ($guard !== null) {
            return $guard;
        }

        $municipality = trim((string) ($this->input('declared_municipality') ?? ''));
        $postcode = trim((string) ($this->input('declared_postcode') ?? ''));
        $address = trim((string) ($this->input('declared_address') ?? ''));
        $evidenceNote = trim((string) ($this->input('evidence_note') ?? ''));

        if ($municipality === '') {
            return $this->respondWithError('VALIDATION_ERROR', __('api.missing_required_field', ['field' => 'declared_municipality']), 'declared_municipality', 422);
        }

        if ($postcode === '') {
            return $this->respondWithError('VALIDATION_ERROR', __('api.missing_required_field', ['field' => 'declared_postcode']), 'declared_postcode', 422);
        }

        $verification = $this->service->submitDeclaration($tenantId, $userId, [
            'declared_municipality' => $municipality,
            'declared_postcode' => $postcode,
            'declared_address' => $address !== '' ? $address : null,
            'evidence_note' => $evidenceNote !== '' ? $evidenceNote : null,
        ]);

        return $this->respondWithData([
            'status' => 'pending',
            'badge' => [
                'key' => 'verified_residency',
                'label' => __('api.residency_badge_label'),
                'verified' => false,
                'status' => 'pending',
            ],
            'verification' => $verification,
        ]);
    }

    /**
     * GET /api/v2/admin/residency-verifications
     */
    public function adminList(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $guard = $this->guardAvailability();
        if ($guard !== null) {
            return $guard;
        }

        $status = $this->query('status');
        $status = is_string($status) ? trim($status) : 'pending';
        if ($status === '') {
            $status = 'pending';
        }

        if (! in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.residency_invalid_status'), 'status', 422);
        }

        return $this->respondWithData([
            'items' => $this->service->listForAdmin($tenantId, $status),
        ]);
    }

    /**
     * POST /api/v2/admin/residency-verifications/{id}/attest
     */
    public function adminAttest(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $guard = $this->guardAvailability();
        if ($guard !== null) {
            return $guard;
        }

        $decision = trim((string) ($this->input('decision') ?? ''));
        $reason = trim((string) ($this->input('reason') ?? ''));

        if (! in_array($decision, ['approved', 'rejected'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.residency_invalid_decision'), 'decision', 422);
        }

        if ($decision === 'rejected' && $reason === '') {
            return $this->respondWithError('VALIDATION_ERROR', __('api.missing_required_field', ['field' => 'reason']), 'reason', 422);
        }

        $verification = $this->service->attest(
            $tenantId,
            $id,
            $adminId,
            $decision,
            $reason !== '' ? $reason : null
        );

        if ($verification === []) {
            return $this->respondNotFound(__('api.residency_verification_not_found'));
        }

        return $this->respondWithData([
            'status' => $verification['status'],
            'badge' => [
                'key' => 'verified_residency',
                'label' => __('api.residency_badge_label'),
                'verified' => $verification['status'] === 'approved',
                'status' => $verification['status'],
            ],
            'verification' => $verification,
        ]);
    }

    private function guardAvailability(): ?JsonResponse
    {
        if (! TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        if (! $this->service->isAvailable()) {
            return $this->respondWithError('FEATURE_UNAVAILABLE', __('api.service_unavailable'), null, 503);
        }

        return null;
    }
}
