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
use App\Services\Identity\IdentityVerificationPaymentService;
use App\Services\Identity\TenantProviderCredentialService;
use App\Services\MemberVerificationBadgeService;

/**
 * OptionalIdentityVerificationController — Voluntary ID verification for active users.
 *
 * Flow: DOB collection → Payment (€5, configurable) → Stripe Identity → Badge granted.
 * Payment is one-time per tenant — retries after failure skip payment.
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
     * Returns verification status, badge state, DOB status, and fee info.
     */
    public function getStatus(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        // Check if user already has id_verified badge
        $badges = $this->badgeService->getUserBadges($userId);
        $hasIdBadge = collect($badges)->contains(fn($b) => $b['badge_type'] === 'id_verified');

        // Check user's DOB
        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->first(['date_of_birth']);
        $hasDob = !empty($user->date_of_birth);

        // Fee info
        $feeCents = IdentityVerificationPaymentService::getFeeCents($tenantId);
        $paymentCompleted = ($feeCents > 0)
            ? IdentityVerificationPaymentService::hasCompletedPayment($tenantId, $userId)
            : true; // No fee = considered paid

        // Check for any active verification session
        $latestSession = IdentityVerificationSessionService::getLatestForUser($tenantId, $userId);

        // If there's an active session, poll Stripe directly (webhook may be delayed)
        if ($latestSession && in_array($latestSession['status'], ['created', 'started', 'processing'], true)) {
            $providerSessionId = $latestSession['provider_session_id'] ?? null;
            if ($providerSessionId) {
                try {
                    $provider = IdentityProviderRegistry::get('stripe_identity');
                    $stripeStatus = $provider->getSessionStatus($providerSessionId);
                    $mappedStatus = $stripeStatus['status'] ?? null;

                    if ($mappedStatus === 'passed' && $latestSession['status'] !== 'passed') {
                        IdentityVerificationSessionService::updateStatus(
                            (int) $latestSession['id'], 'passed', null, null, null
                        );
                        $latestSession['status'] = 'passed';

                        if (!$hasIdBadge) {
                            self::grantIdVerifiedBadge($userId, $tenantId);
                            $hasIdBadge = true;
                        }
                    } elseif ($mappedStatus === 'failed') {
                        IdentityVerificationSessionService::updateStatus(
                            (int) $latestSession['id'], 'failed', null, null,
                            $stripeStatus['failure_reason'] ?? 'Verification failed'
                        );
                        $latestSession['status'] = 'failed';
                        $latestSession['failure_reason'] = $stripeStatus['failure_reason'] ?? 'Verification failed';
                    }
                } catch (\Throwable $e) {
                    Log::warning('Stripe Identity status check failed', ['error' => $e->getMessage()]);
                }
            }
        }

        return $this->respondWithData([
            'has_id_verified_badge' => $hasIdBadge,
            'user_has_dob' => $hasDob,
            'fee_cents' => $feeCents,
            'fee_currency' => 'eur',
            'payment_completed' => $paymentCompleted,
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
     * POST /api/v2/identity/save-dob
     *
     * Save user's date of birth (required before identity verification).
     */
    public function saveDob(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $input = $this->getAllInput();
        $dobRaw = $input['date_of_birth'] ?? '';

        if (empty($dobRaw)) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'Date of birth is required.', 'date_of_birth', 422);
        }

        $dob = date('Y-m-d', strtotime($dobRaw));
        if (!$dob || $dob === '1970-01-01') {
            return $this->respondWithError('VALIDATION_INVALID_FORMAT', 'Please enter a valid date of birth.', 'date_of_birth', 422);
        }

        // Must be in the past
        if (strtotime($dob) >= time()) {
            return $this->respondWithError('VALIDATION_INVALID_FORMAT', 'Date of birth must be in the past.', 'date_of_birth', 422);
        }

        // Must be at least 16 years old
        $age = (int) date_diff(date_create($dob), date_create('today'))->y;
        if ($age < 16) {
            return $this->respondWithError('VALIDATION_INVALID_FORMAT', 'You must be at least 16 years old.', 'date_of_birth', 422);
        }

        DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->update(['date_of_birth' => $dob]);

        return $this->respondWithData([
            'success' => true,
            'date_of_birth' => $dob,
        ]);
    }

    /**
     * POST /api/v2/identity/create-payment
     *
     * Create a Stripe PaymentIntent for the identity verification fee.
     */
    public function createPaymentIntent(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $feeCents = IdentityVerificationPaymentService::getFeeCents($tenantId);

        if ($feeCents <= 0) {
            return $this->respondWithData(['payment_required' => false, 'message' => 'No fee required.']);
        }

        // Check if already paid
        if (IdentityVerificationPaymentService::hasCompletedPayment($tenantId, $userId)) {
            return $this->respondWithData(['payment_required' => false, 'already_paid' => true]);
        }

        try {
            $result = IdentityVerificationPaymentService::createPaymentIntent($userId, $tenantId, $feeCents);

            // Create a placeholder session to track the payment
            $sessionId = IdentityVerificationSessionService::create(
                $tenantId,
                $userId,
                'stripe_identity',
                'document_selfie',
                ['provider_session_id' => null, 'redirect_url' => null, 'client_token' => null]
            );

            // Link payment to session
            IdentityVerificationSessionService::updatePaymentStatus($sessionId, 'pending', $result['payment_intent_id']);

            // Store fee amount
            DB::statement(
                "UPDATE identity_verification_sessions SET verification_fee_amount = ? WHERE id = ?",
                [$feeCents, $sessionId]
            );

            return $this->respondWithData([
                'client_secret' => $result['client_secret'],
                'fee_cents' => $feeCents,
                'fee_currency' => 'eur',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create verification payment', ['user' => $userId, 'error' => $e->getMessage()]);
            return $this->respondWithError('SERVER_INTERNAL_ERROR', 'Unable to process payment. Please try again.', null, 503);
        }
    }

    /**
     * POST /api/v2/identity/start
     *
     * Start Stripe Identity verification. Requires DOB and payment (if fee > 0).
     */
    public function startVerification(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $this->rateLimit("optional_verify_{$userId}", 20, 3600);

        // Check if already verified
        $badges = $this->badgeService->getUserBadges($userId);
        $hasIdBadge = collect($badges)->contains(fn($b) => $b['badge_type'] === 'id_verified');
        if ($hasIdBadge) {
            return $this->respondWithData(['already_verified' => true]);
        }

        // Get user with DOB
        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->first(['id', 'first_name', 'last_name', 'date_of_birth']);

        if (!$user) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'User not found.', null, 404);
        }

        // Prerequisite: DOB must exist
        if (empty($user->date_of_birth)) {
            return $this->respondWithError('DOB_REQUIRED', 'Please provide your date of birth first.', null, 422);
        }

        // Prerequisite: Payment must be completed (if fee > 0)
        $feeCents = IdentityVerificationPaymentService::getFeeCents($tenantId);
        if ($feeCents > 0 && !IdentityVerificationPaymentService::hasCompletedPayment($tenantId, $userId)) {
            return $this->respondWithError('PAYMENT_REQUIRED', 'Please complete payment first.', null, 422);
        }

        // Use Stripe Identity provider
        $providerSlug = 'stripe_identity';
        if (!IdentityProviderRegistry::has($providerSlug)) {
            return $this->respondWithError('SERVICE_UNAVAILABLE', 'Identity verification is not currently available.', null, 503);
        }

        $provider = IdentityProviderRegistry::get($providerSlug);
        if (!$provider->isAvailable($tenantId)) {
            return $this->respondWithError('SERVICE_UNAVAILABLE', 'Identity verification is not currently available.', null, 503);
        }

        // Load credentials + pass user details for name/DOB matching
        $providerConfig = TenantProviderCredentialService::get($tenantId, $providerSlug) ?? [];

        $dob = $user->date_of_birth;
        $providerConfig['provided_details'] = [
            'name' => [
                'first_name' => $user->first_name ?? '',
                'last_name' => $user->last_name ?? '',
            ],
            'dob' => [
                'day' => (int) date('d', strtotime($dob)),
                'month' => (int) date('m', strtotime($dob)),
                'year' => (int) date('Y', strtotime($dob)),
            ],
        ];

        try {
            $providerData = $provider->createSession($userId, $tenantId, 'document_selfie', $providerConfig);

            $sessionId = IdentityVerificationSessionService::create(
                $tenantId, $userId, $providerSlug, 'document_selfie', $providerData
            );

            // If there was a payment, mark the session as paid
            if ($feeCents > 0) {
                // Find the payment session and copy its payment status
                $paymentSession = DB::selectOne(
                    "SELECT stripe_payment_intent_id FROM identity_verification_sessions
                     WHERE tenant_id = ? AND user_id = ? AND payment_status = 'completed'
                     ORDER BY id DESC LIMIT 1",
                    [$tenantId, $userId]
                );
                if ($paymentSession) {
                    IdentityVerificationSessionService::updatePaymentStatus(
                        $sessionId, 'completed', $paymentSession->stripe_payment_intent_id
                    );
                    DB::statement(
                        "UPDATE identity_verification_sessions SET verification_fee_amount = ? WHERE id = ?",
                        [$feeCents, $sessionId]
                    );
                }
            }

            IdentityVerificationEventService::log(
                $tenantId, $userId,
                IdentityVerificationEventService::EVENT_VERIFICATION_CREATED,
                $sessionId, null,
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
            Log::error('Identity verification failed to start', ['user' => $userId, 'error' => $e->getMessage()]);
            return $this->respondWithError('SERVER_INTERNAL_ERROR', 'Unable to start verification.', null, 503);
        }
    }

    /**
     * Auto-grant the id_verified badge when verification passes.
     */
    public static function grantIdVerifiedBadge(int $userId, int $tenantId): void
    {
        $badgeService = app(MemberVerificationBadgeService::class);

        if (TenantContext::getId() !== $tenantId) {
            TenantContext::set($tenantId);
        }

        $badgeService->grantBadge(
            $userId, 'id_verified', $userId,
            'Automatically granted via Stripe Identity verification',
            null
        );

        Log::info("ID Verified badge granted to user {$userId} in tenant {$tenantId}");
    }
}
