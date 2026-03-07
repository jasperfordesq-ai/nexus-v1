<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\ApiErrorCodes;
use Nexus\Core\TenantContext;
use Nexus\Services\Identity\RegistrationPolicyService;
use Nexus\Services\Identity\IdentityProviderRegistry;
use Nexus\Services\Identity\IdentityVerificationSessionService;
use Nexus\Services\Identity\RegistrationOrchestrationService;
use Nexus\Services\Identity\InviteCodeService;

/**
 * RegistrationPolicyApiController — Admin endpoints for managing registration policies
 * and user-facing endpoints for verification status/initiation.
 *
 * Admin Endpoints:
 * - GET  /api/v2/admin/config/registration-policy     — Get current policy
 * - PUT  /api/v2/admin/config/registration-policy     — Update policy
 * - GET  /api/v2/admin/identity/providers              — List available providers
 * - GET  /api/v2/admin/identity/sessions               — List verification sessions
 *
 * User Endpoints:
 * - GET  /api/v2/auth/verification-status              — Get current verification status
 * - POST /api/v2/auth/start-verification               — Initiate verification
 */
class RegistrationPolicyApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    // ─── Admin Endpoints ─────────────────────────────────────────────────

    /**
     * GET /api/v2/admin/config/registration-policy
     */
    public function getPolicy(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $policy = RegistrationPolicyService::getEffectivePolicy($tenantId);
        $this->respondWithData($policy);
    }

    /**
     * PUT /api/v2/admin/config/registration-policy
     *
     * Body: {
     *   "registration_mode": "verified_identity",
     *   "verification_provider": "stripe_identity",
     *   "verification_level": "document_selfie",
     *   "post_verification": "activate",
     *   "fallback_mode": "admin_review",
     *   "require_email_verify": true
     * }
     */
    public function updatePolicy(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        try {
            $policy = RegistrationPolicyService::upsertPolicy($tenantId, $input);
            $this->respondWithData($policy);
        } catch (\InvalidArgumentException $e) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_INVALID_VALUE,
                $e->getMessage(),
                null,
                422
            );
        }
    }

    /**
     * GET /api/v2/admin/identity/providers
     *
     * Returns list of registered identity verification providers with their capabilities.
     */
    public function listProviders(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $providers = IdentityProviderRegistry::listForAdmin();

        // Add availability status per provider for this tenant
        foreach ($providers as &$provider) {
            try {
                $instance = IdentityProviderRegistry::get($provider['slug']);
                $provider['available'] = $instance->isAvailable($tenantId);
            } catch (\Throwable $e) {
                $provider['available'] = false;
            }
        }

        $this->respondWithData($providers);
    }

    /**
     * GET /api/v2/admin/identity/sessions
     *
     * Returns verification sessions for the current tenant.
     * Query params: ?status=pending&limit=50&offset=0
     */
    public function listSessions(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $limit = min($this->inputInt('limit', 50), 100);
        $offset = max($this->inputInt('offset', 0), 0);

        $sessions = IdentityVerificationSessionService::getPendingForTenant($tenantId, $limit, $offset);

        $this->respondWithData($sessions);
    }

    // ─── User-Facing Endpoints ───────────────────────────────────────────

    /**
     * GET /api/v2/auth/verification-status
     *
     * Returns the current user's registration/verification status.
     */
    public function getVerificationStatus(): void
    {
        $user = $this->requireAuth();
        $tenantId = (int) $user['tenant_id'];
        $userId = (int) $user['id'];

        $status = RegistrationOrchestrationService::getRegistrationStatus($userId, $tenantId);
        $this->respondWithData($status);
    }

    /**
     * POST /api/v2/auth/start-verification
     *
     * Initiates identity verification for the authenticated user.
     * Returns redirect URL or client token for the provider's flow.
     */
    public function startVerification(): void
    {
        $user = $this->requireAuth();
        $tenantId = (int) $user['tenant_id'];
        $userId = (int) $user['id'];

        try {
            $result = RegistrationOrchestrationService::initiateVerification($userId, $tenantId);
            $this->respondWithData($result);
        } catch (\RuntimeException $e) {
            $this->respondWithError(
                ApiErrorCodes::SERVER_INTERNAL_ERROR,
                $e->getMessage(),
                null,
                503
            );
        }
    }

    // ─── Invite Code Endpoints ────────────────────────────────────────────

    /**
     * GET /api/v2/admin/invite-codes
     *
     * List invite codes for the current tenant.
     */
    public function listInviteCodes(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $limit = min($this->inputInt('limit', 50), 100);
        $offset = max($this->inputInt('offset', 0), 0);

        $result = InviteCodeService::listForTenant($tenantId, $limit, $offset);
        $this->respondWithData($result);
    }

    /**
     * POST /api/v2/admin/invite-codes
     *
     * Generate one or more invite codes.
     * Body: { "count": 5, "max_uses": 1, "expires_at": "2026-04-01", "note": "March batch" }
     */
    public function generateInviteCodes(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $adminId = (int) $this->requireAuth()['id'];
        $input = $this->getAllInput();

        $count = max(1, min((int) ($input['count'] ?? 1), 100));
        $maxUses = isset($input['max_uses']) ? max(1, (int) $input['max_uses']) : 1;
        $expiresAt = $input['expires_at'] ?? null;
        $note = isset($input['note']) ? substr(trim($input['note']), 0, 255) : null;

        if ($expiresAt && !strtotime($expiresAt)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_INVALID_FORMAT, 'Invalid expires_at date', null, 422);
        }

        $codes = InviteCodeService::generate($tenantId, $adminId, $count, $maxUses, $expiresAt, $note);
        $this->respondWithData(['codes' => $codes, 'count' => count($codes)]);
    }

    /**
     * DELETE /api/v2/admin/invite-codes/{id}
     *
     * Deactivate an invite code.
     */
    public function deactivateInviteCode(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $codeId = (int) $this->getRouteParam('id');

        if (!$codeId) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Code ID required', null, 400);
        }

        $success = InviteCodeService::deactivate($tenantId, $codeId);
        if (!$success) {
            $this->respondWithError(ApiErrorCodes::NOT_FOUND, 'Invite code not found', null, 404);
        }

        $this->respondWithData(['deactivated' => true]);
    }

    /**
     * POST /api/v2/auth/validate-invite
     *
     * Public endpoint to validate an invite code before registration.
     * Body: { "code": "ABCD1234" }
     */
    public function validateInviteCode(): void
    {
        $input = $this->getAllInput();
        $code = $input['code'] ?? '';

        if (!$code || strlen($code) < 4) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Invite code required', null, 400);
        }

        $tenantId = TenantContext::getId();
        $result = InviteCodeService::validate($tenantId, $code);
        $this->respondWithData(['valid' => $result['valid'], 'reason' => $result['reason'] ?? null]);
    }
}
