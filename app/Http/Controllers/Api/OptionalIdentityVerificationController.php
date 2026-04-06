<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Core\TenantContext;
use App\Services\Identity\IdentityProviderRegistry;
use App\Services\Identity\IdentityVerificationSessionService;
use App\Services\Identity\IdentityVerificationEventService;
use App\Services\Identity\TenantProviderCredentialService;
use App\Services\MemberVerificationBadgeService;

/**
 * OptionalIdentityVerificationController — Voluntary ID verification for active users.
 *
 * Unlike the registration-time verification flow (RegistrationPolicyController),
 * this controller allows already-active members to optionally verify their identity
 * to earn an "ID Verified" badge on their profile. Uses Stripe Identity directly,
 * bypassing registration policy requirements.
 */
class OptionalIdentityVerificationController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly MemberVerificationBadgeService $badgeService,
    ) {}

    /**
     * GET /api/v2/identity/status
     *
     * Check current user's identity verification status and badge.
     */
    public function getStatus(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        // Check if user already has id_verified badge
        $badges = $this->badgeService->getUserBadges($userId);
        $hasIdBadge = collect($badges)->contains(fn($b) => $b['badge_type'] === 'id_verified');

        // Check for any active verification session
        $latestSession = IdentityVerificationSessionService::getLatestForUser($tenantId, $userId);

        return $this->respondWithData([
            'has_id_verified_badge' => $hasIdBadge,
            'verification_status' => $latestSession ? $latestSession['status'] : null,
            'latest_session' => $latestSession ? [
                'id' => $latestSession['id'],
                'status' => $latestSession['status'],
                'provider' => $latestSession['provider_slug'] ?? null,
                'created_at' => $latestSession['created_at'],
                'failure_reason' => $latestSession['failure_reason'] ?? null,
            ] : null,
        ]);
    }

    /**
     * POST /api/v2/identity/start
     *
     * Start an optional identity verification session using Stripe Identity.
     */
    public function startVerification(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        // Rate limit: 5 starts per user per hour
        $this->rateLimit("optional_verify_{$userId}", 5, 3600);

        // Check if already verified
        $badges = $this->badgeService->getUserBadges($userId);
        $hasIdBadge = collect($badges)->contains(fn($b) => $b['badge_type'] === 'id_verified');
        if ($hasIdBadge) {
            return $this->respondWithData([
                'already_verified' => true,
                'message' => 'Your identity is already verified.',
            ]);
        }

        // Use Stripe Identity provider
        $providerSlug = 'stripe_identity';
        if (!IdentityProviderRegistry::has($providerSlug)) {
            return $this->respondWithError(
                'SERVICE_UNAVAILABLE',
                'Identity verification is not currently available.',
                null,
                503
            );
        }

        $provider = IdentityProviderRegistry::get($providerSlug);
        if (!$provider->isAvailable($tenantId)) {
            return $this->respondWithError(
                'SERVICE_UNAVAILABLE',
                'Identity verification is not currently available. Please try again later.',
                null,
                503
            );
        }

        // Load credentials (tenant-specific or global)
        $providerConfig = TenantProviderCredentialService::get($tenantId, $providerSlug) ?? [];

        try {
            // Create session with document + selfie verification
            $providerData = $provider->createSession(
                $userId,
                $tenantId,
                'document_selfie',
                $providerConfig
            );

            // Persist session
            $sessionId = IdentityVerificationSessionService::create(
                $tenantId,
                $userId,
                $providerSlug,
                'document_selfie',
                $providerData
            );

            // Log
            IdentityVerificationEventService::log(
                $tenantId,
                $userId,
                IdentityVerificationEventService::EVENT_VERIFICATION_CREATED,
                $sessionId,
                null,
                IdentityVerificationEventService::ACTOR_USER,
                ['provider' => $providerSlug, 'level' => 'document_selfie', 'flow' => 'optional']
            );

            return $this->respondWithData([
                'session_id' => $sessionId,
                'redirect_url' => $providerData['redirect_url'] ?? null,
                'client_token' => $providerData['client_token'] ?? null,
                'provider' => $providerSlug,
                'expires_at' => $providerData['expires_at'] ?? null,
                'status' => 'created',
            ]);
        } catch (\Throwable $e) {
            Log::error('Optional identity verification failed to start', [
                'user' => $userId,
                'tenant' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return $this->respondWithError(
                'SERVER_INTERNAL_ERROR',
                'Unable to start identity verification. Please try again later.',
                null,
                503
            );
        }
    }

    /**
     * Called by the webhook handler when a verification session passes.
     * Automatically grants the id_verified badge.
     *
     * @param int $userId
     * @param int $tenantId
     */
    public static function grantIdVerifiedBadge(int $userId, int $tenantId): void
    {
        // Use a system admin ID (0) for auto-granted badges
        // The badge service requires an adminId, but we use the user's own ID
        // since this is a self-service verification
        $badgeService = app(MemberVerificationBadgeService::class);

        // Set tenant context if not already set
        if (TenantContext::getId() !== $tenantId) {
            TenantContext::set($tenantId);
        }

        $badgeService->grantBadge(
            $userId,
            'id_verified',
            $userId, // verified_by = self (Stripe verified them)
            'Automatically granted via Stripe Identity verification',
            null // no expiry
        );

        Log::info("ID Verified badge granted to user {$userId} in tenant {$tenantId}");
    }
}
