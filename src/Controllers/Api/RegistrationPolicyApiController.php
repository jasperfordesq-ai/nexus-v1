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
            return;
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

        // Add availability status and credential info per provider for this tenant
        $configured = \Nexus\Services\Identity\TenantProviderCredentialService::listConfigured($tenantId);
        foreach ($providers as &$provider) {
            try {
                $instance = IdentityProviderRegistry::get($provider['slug']);
                $provider['available'] = $instance->isAvailable($tenantId);
            } catch (\Throwable $e) {
                $provider['available'] = false;
            }
            $provider['has_credentials'] = isset($configured[$provider['slug']]);
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

    /**
     * GET /api/v2/admin/identity/audit-log
     *
     * Returns verification audit events for the current tenant.
     * Query params: ?event_type=verification_passed&limit=50&offset=0
     */
    public function getAuditLog(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $limit = min($this->inputInt('limit', 50), 100);
        $offset = max($this->inputInt('offset', 0), 0);
        $eventType = $this->input('event_type', null);

        $result = \Nexus\Services\Identity\IdentityVerificationEventService::getForTenant(
            $tenantId,
            $limit,
            $offset,
            $eventType
        );

        $this->respondWithData($result);
    }

    // ─── User-Facing Endpoints ───────────────────────────────────────────

    /**
     * GET /api/v2/auth/verification-status
     *
     * Returns the current user's registration/verification status.
     */
    public function getVerificationStatus(): void
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

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
        // Rate limit: 5 verification starts per user per hour
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        if (\Nexus\Services\RateLimitService::check("verify:start:$userId", 5, 3600)) {
            header('Retry-After: 3600');
            $this->respondWithError(ApiErrorCodes::RATE_LIMIT_EXCEEDED, 'Too many verification attempts. Please try again later.', null, 429);
            return;
        }
        \Nexus\Services\RateLimitService::increment("verify:start:$userId", 3600);

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
            return;
        }
    }

    // ─── Admin Review Endpoints ──────────────────────────────────────────

    /**
     * POST /api/v2/admin/identity/sessions/{id}/approve
     *
     * Approve a pending verification session. Activates the user.
     */
    public function adminApproveVerification(): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $sessionId = (int) $this->getRouteParam('id');

        if (!$sessionId) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Session ID required', null, 400);
            return;
        }

        try {
            $result = RegistrationOrchestrationService::adminReview(
                $sessionId,
                $adminId,
                'approve'
            );
            $this->respondWithData($result);
        } catch (\InvalidArgumentException $e) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_INVALID_VALUE,
                $e->getMessage(),
                null,
                422
            );
            return;
        }
    }

    /**
     * POST /api/v2/admin/identity/sessions/{id}/reject
     *
     * Reject a pending verification session. Marks verification as failed.
     */
    public function adminRejectVerification(): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $sessionId = (int) $this->getRouteParam('id');

        if (!$sessionId) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Session ID required', null, 400);
            return;
        }

        try {
            $result = RegistrationOrchestrationService::adminReview(
                $sessionId,
                $adminId,
                'reject'
            );
            $this->respondWithData($result);
        } catch (\InvalidArgumentException $e) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_INVALID_VALUE,
                $e->getMessage(),
                null,
                422
            );
            return;
        }
    }

    // ─── Provider Credential Endpoints ──────────────────────────────────────

    /**
     * GET /api/v2/admin/identity/provider-credentials
     *
     * Returns which providers have tenant-specific credentials configured.
     */
    public function listProviderCredentials(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $configured = \Nexus\Services\Identity\TenantProviderCredentialService::listConfigured($tenantId);
        $allProviders = IdentityProviderRegistry::all();

        $result = [];
        foreach ($allProviders as $slug => $provider) {
            if ($slug === 'mock') continue; // Mock never needs credentials
            $result[] = [
                'provider_slug' => $slug,
                'provider_name' => $provider->getName(),
                'has_credentials' => isset($configured[$slug]),
                'required_fields' => \Nexus\Services\Identity\TenantProviderCredentialService::getRequiredFields($slug),
            ];
        }

        $this->respondWithData($result);
    }

    /**
     * PUT /api/v2/admin/identity/provider-credentials/{slug}
     *
     * Save API credentials for a specific provider.
     * Body: { "api_key": "sk_live_...", "webhook_secret": "whsec_..." }
     */
    public function saveProviderCredentials(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $slug = $this->getRouteParam('slug');

        if (!$slug || !IdentityProviderRegistry::has($slug)) {
            $this->respondWithError(\Nexus\Core\ApiErrorCodes::RESOURCE_NOT_FOUND, 'Unknown provider', null, 404);
            return;
        }

        $input = $this->getAllInput();
        $credentials = [];

        // Only accept known credential fields
        $allowedFields = ['api_key', 'webhook_secret'];
        foreach ($allowedFields as $field) {
            if (isset($input[$field]) && is_string($input[$field]) && $input[$field] !== '') {
                $credentials[$field] = $input[$field];
            }
        }

        if (empty($credentials)) {
            $this->respondWithError(\Nexus\Core\ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'At least one credential field is required', null, 422);
            return;
        }

        $saved = \Nexus\Services\Identity\TenantProviderCredentialService::save($tenantId, $slug, $credentials);
        $this->respondWithData(['saved' => $saved, 'provider_slug' => $slug]);
    }

    /**
     * DELETE /api/v2/admin/identity/provider-credentials/{slug}
     *
     * Remove stored credentials for a specific provider.
     */
    public function deleteProviderCredentials(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $slug = $this->getRouteParam('slug');

        if (!$slug || !IdentityProviderRegistry::has($slug)) {
            $this->respondWithError(\Nexus\Core\ApiErrorCodes::RESOURCE_NOT_FOUND, 'Unknown provider', null, 404);
            return;
        }

        $deleted = \Nexus\Services\Identity\TenantProviderCredentialService::delete($tenantId, $slug);
        $this->respondWithData(['deleted' => $deleted, 'provider_slug' => $slug]);
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
        $adminId = $this->requireAuth();
        $input = $this->getAllInput();

        $count = max(1, min((int) ($input['count'] ?? 1), 100));
        $maxUses = isset($input['max_uses']) ? max(1, (int) $input['max_uses']) : 1;
        $expiresAt = $input['expires_at'] ?? null;
        $note = isset($input['note']) ? substr(trim($input['note']), 0, 255) : null;

        if ($expiresAt && !strtotime($expiresAt)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_INVALID_FORMAT, 'Invalid expires_at date', null, 422);
            return;
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
            return;
        }

        $success = InviteCodeService::deactivate($tenantId, $codeId);
        if (!$success) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Invite code not found', null, 404);
            return;
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
        // Rate limit: 10 invite code checks per IP per minute (anti-brute-force)
        $ip = \Nexus\Core\ClientIp::get();
        if (\Nexus\Services\RateLimitService::check("invite:validate:$ip", 10, 60)) {
            header('Retry-After: 60');
            $this->respondWithError(ApiErrorCodes::RATE_LIMIT_EXCEEDED, 'Too many attempts. Please try again later.', null, 429);
            return;
        }
        \Nexus\Services\RateLimitService::increment("invite:validate:$ip", 60);

        $input = $this->getAllInput();
        $code = $input['code'] ?? '';

        if (!$code || strlen($code) < 4) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'Invite code required', null, 400);
            return;
        }

        $tenantId = TenantContext::getId();
        $result = InviteCodeService::validate($tenantId, $code);
        $this->respondWithData(['valid' => $result['valid'], 'reason' => $result['reason'] ?? null]);
    }

    /**
     * GET /api/v2/auth/registration-info
     *
     * Public endpoint — returns the tenant's registration mode so the
     * registration form can conditionally show invite code fields.
     */
    public function getRegistrationInfo(): void
    {
        // Rate limit: 30 per IP per minute (lightweight but protective)
        $ip = \Nexus\Core\ClientIp::get();
        if (\Nexus\Services\RateLimitService::check("reg:info:$ip", 30, 60)) {
            header('Retry-After: 60');
            $this->respondWithError(ApiErrorCodes::RATE_LIMIT_EXCEEDED, 'Too many requests.', null, 429);
            return;
        }
        \Nexus\Services\RateLimitService::increment("reg:info:$ip", 60);

        $tenantId = TenantContext::getId();
        $policy = RegistrationPolicyService::getEffectivePolicy($tenantId);

        $this->respondWithData([
            'registration_mode' => $policy['registration_mode'],
            'requires_invite_code' => $policy['registration_mode'] === 'invite_only',
            'requires_verification' => in_array($policy['registration_mode'], ['verified_identity', 'government_id'], true),
            'is_waitlist' => $policy['registration_mode'] === 'waitlist',
        ]);
    }
}
