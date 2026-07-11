<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Exceptions\SafeguardingPolicyException;
use App\I18n\LocaleContext;
use App\Models\Notification;
use App\Services\MemberVettingAttestationService;
use App\Services\SafeguardingJurisdictionService;
use App\Services\SafeguardingPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Metadata-only broker safeguarding confirmations.
 *
 * There is intentionally no generic record create/edit, arbitrary status,
 * evidence field, certificate reference/date, notes, upload, bulk-confirm, or
 * delete endpoint in this controller.
 */
class AdminVettingController extends BaseApiController
{
    protected bool $isV2Api = true;

    private const PROHIBITED_INPUT_FIELDS = [
        'document', 'file', 'document_url', 'reference_number', 'certificate_number',
        'issue_date', 'expiry_date', 'renewal_date', 'notes', 'result', 'status',
        'scheme_code', 'attestation_code', 'vetting_type', 'purpose_code',
        'scope_type', 'scope_identifier', 'policy_version', 'confirmed_at',
        'works_with_children', 'works_with_vulnerable_adults', 'requires_enhanced_check',
    ];

    public function __construct(
        private readonly MemberVettingAttestationService $attestations,
        private readonly SafeguardingJurisdictionService $jurisdictions,
    ) {}

    /** GET /v2/admin/vetting */
    public function list(): JsonResponse
    {
        $this->requireVettingDecisionMaker();
        $tenantId = TenantContext::getId();

        $result = $this->attestations->listMembers($tenantId, [
            'status' => $this->input('status', 'all'),
            'search' => $this->input('search', ''),
            'page' => $this->inputInt('page', 1, 1),
            'per_page' => $this->inputInt('per_page', 25, 1, 100),
        ]);

        return $this->respondWithData($result['data'], [
            'pagination' => $result['pagination'],
        ]);
    }

    /** GET /v2/admin/vetting/stats */
    public function stats(): JsonResponse
    {
        $this->requireVettingDecisionMaker();

        return $this->respondWithData($this->attestations->stats(TenantContext::getId()));
    }

    /** GET /v2/admin/vetting/{id} */
    public function show(int $id): JsonResponse
    {
        $this->requireVettingDecisionMaker();
        $record = $this->attestations->getById($id, TenantContext::getId());

        if ($record === null) {
            return $this->respondWithError('NOT_FOUND', __('api.vetting_confirmation_not_found'), null, 404);
        }

        return $this->respondWithData($record);
    }

    /** GET /v2/admin/vetting/user/{userId} */
    public function getUserRecords(int $userId): JsonResponse
    {
        $this->requireVettingDecisionMaker();

        try {
            return $this->respondWithData(
                $this->attestations->getUserRecords($userId, TenantContext::getId())
            );
        } catch (SafeguardingPolicyException $e) {
            return $this->policyError($e);
        }
    }

    /** GET /v2/admin/vetting/policy */
    public function policy(): JsonResponse
    {
        $this->requireVettingDecisionMaker();
        $tenantId = TenantContext::getId();

        return $this->respondWithData([
            'policy' => $this->jurisdictions->getPolicy($tenantId),
            'jurisdictions' => $this->jurisdictions->availableJurisdictions(),
            'revocation_reason_codes' => MemberVettingAttestationService::REVOCATION_REASON_CODES,
            'review_resolution_codes' => MemberVettingAttestationService::REVIEW_RESOLUTION_CODES,
        ]);
    }

    /** PUT /v2/admin/vetting/policy */
    public function updatePolicy(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        if (($error = $this->rejectProhibitedInput(['jurisdiction'])) !== null) {
            return $error;
        }
        $tenantId = TenantContext::getId();
        $jurisdiction = trim((string) $this->input('jurisdiction', ''));

        if ($jurisdiction === '') {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                __('api.safeguarding_jurisdiction_required'),
                'jurisdiction',
                422,
            );
        }

        try {
            $previousPolicy = $this->jurisdictions->getPolicy($tenantId);
            $result = DB::transaction(function () use ($tenantId, $jurisdiction, $adminId, $previousPolicy): array {
                $policy = $this->jurisdictions->configure($tenantId, $jurisdiction, $adminId);
                $transition = is_string($policy['preset'] ?? null) && $policy['preset'] !== ''
                    ? SafeguardingPreferenceService::replaceCountryPreset(
                        $tenantId,
                        $policy['preset'],
                        $previousPolicy['jurisdiction'] !== $policy['jurisdiction'],
                    )
                    : SafeguardingPreferenceService::preservePresetProtectionsForUnavailablePolicy(
                        $tenantId,
                        $adminId,
                    );

                return ['policy' => $policy, 'transition' => $transition];
            });
            // `configure()` resolves its response inside the transaction. Reload
            // after commit so no uncommitted policy value can remain cached.
            $this->jurisdictions->forget($tenantId);
            $policy = $this->jurisdictions->getPolicy($tenantId);

            return $this->respondWithData([
                'policy' => $policy,
                'preference_transition' => $result['transition'],
                'message' => __('api.safeguarding_jurisdiction_updated'),
            ]);
        } catch (SafeguardingPolicyException $e) {
            $this->jurisdictions->forget($tenantId);
            return $this->policyError($e);
        } catch (Throwable $e) {
            $this->jurisdictions->forget($tenantId);
            throw $e;
        }
    }

    /** POST /v2/admin/vetting/policy/rotate */
    public function rotatePolicy(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        if (($error = $this->rejectProhibitedInput(['acknowledgement', 'reason_code'])) !== null) {
            return $error;
        }
        $tenantId = TenantContext::getId();
        if (! $this->inputBool('acknowledgement', false)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                __('api.safeguarding_policy_rotation_acknowledgement_required'),
                'acknowledgement',
                422,
            );
        }
        $reasonCode = trim((string) $this->input('reason_code', 'policy_changed'));
        if (! in_array($reasonCode, ['policy_changed', 'scheduled_review', 'incident_response'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_reason_code'), 'reason_code', 422);
        }

        try {
            $rotation = $this->jurisdictions->rotatePolicyVersion($tenantId, $adminId, $reasonCode);
            $policy = $rotation['policy'];
            $affectedMembers = $rotation['affected_member_ids'];

            foreach ($affectedMembers as $memberId) {
                $member = DB::table('users')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $memberId)
                    ->first(['preferred_language']);
                try {
                    LocaleContext::withLocale($member, function () use ($memberId, $tenantId): void {
                        Notification::createNotification(
                            $memberId,
                            __('safeguarding.review.attestation_policy_rotated_member'),
                            '/settings',
                            'safeguarding_vetting_review',
                            true,
                            $tenantId,
                        );
                    });
                } catch (Throwable $e) {
                    Log::warning('Safeguarding policy rotation member notification failed', [
                        'tenant_id' => $tenantId,
                        'member_id' => $memberId,
                        'exception' => $e::class,
                    ]);
                }
            }

            return $this->respondWithData([
                'policy' => $policy,
                'reason_code' => $reasonCode,
                'affected_member_count' => count($affectedMembers),
                'message' => __('api.safeguarding_policy_rotated'),
            ]);
        } catch (SafeguardingPolicyException $e) {
            return $this->policyError($e);
        }
    }

    /** POST /v2/admin/vetting/user/{userId}/confirm */
    public function confirm(int $userId): JsonResponse
    {
        $actorId = $this->requireVettingDecisionMaker();
        if (($error = $this->rejectProhibitedInput(['acknowledgement', 'review_request_id'])) !== null) {
            return $error;
        }
        if (! $this->inputBool('acknowledgement', false)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                __('api.vetting_confirmation_acknowledgement_required'),
                'acknowledgement',
                422,
            );
        }

        try {
            $record = $this->attestations->confirmForCurrentPolicy(
                TenantContext::getId(),
                $userId,
                $actorId,
                $this->optionalPositiveInt('review_request_id'),
            );
            $this->notifyMemberStatusUpdated($userId);

            return $this->respondWithData($record, null, 201);
        } catch (SafeguardingPolicyException $e) {
            return $this->policyError($e);
        }
    }

    /** POST /v2/admin/vetting/user/{userId}/revoke */
    public function revoke(int $userId): JsonResponse
    {
        $actorId = $this->requireVettingDecisionMaker();
        if (($error = $this->rejectProhibitedInput(['reason_code', 'review_request_id'])) !== null) {
            return $error;
        }

        $reasonCode = trim((string) $this->input('reason_code', 'community_decision_withdrawn'));

        try {
            $record = $this->attestations->revokeForCurrentPolicy(
                TenantContext::getId(),
                $userId,
                $actorId,
                $reasonCode,
                $this->optionalPositiveInt('review_request_id'),
            );
            $this->notifyMemberStatusUpdated($userId);

            return $this->respondWithData($record);
        } catch (SafeguardingPolicyException $e) {
            return $this->policyError($e);
        }
    }

    /** POST /v2/admin/vetting/reviews/{reviewId}/resolve */
    public function resolveReview(int $reviewId): JsonResponse
    {
        $actorId = $this->requireVettingDecisionMaker();
        if (($error = $this->rejectProhibitedInput(['resolution_code'])) !== null) {
            return $error;
        }

        try {
            $record = $this->attestations->resolveReview(
                TenantContext::getId(),
                $reviewId,
                $actorId,
                trim((string) $this->input('resolution_code', '')),
            );

            return $this->respondWithData($record);
        } catch (SafeguardingPolicyException $e) {
            return $this->policyError($e);
        }
    }

    /** @param list<string> $allowedFields */
    private function rejectProhibitedInput(array $allowedFields): ?JsonResponse
    {
        if (request()->allFiles() !== []) {
            return $this->respondWithError(
                'VETTING_EVIDENCE_PROHIBITED',
                __('api.vetting_evidence_prohibited'),
                'file',
                422,
            );
        }

        $inputKeys = array_keys($this->getAllInput());
        $prohibited = array_intersect($inputKeys, self::PROHIBITED_INPUT_FIELDS);
        $unknown = array_diff($inputKeys, $allowedFields);
        if ($prohibited !== [] || $unknown !== []) {
            return $this->respondWithError(
                'VETTING_EVIDENCE_PROHIBITED',
                __('api.vetting_evidence_prohibited'),
                (string) (array_values(array_merge($prohibited, $unknown))[0] ?? 'request'),
                422,
            );
        }

        return null;
    }

    private function optionalPositiveInt(string $key): ?int
    {
        $value = $this->input($key);
        if ($value === null || $value === '') {
            return null;
        }

        $parsed = (int) $value;

        return $parsed > 0 ? $parsed : null;
    }

    private function policyError(SafeguardingPolicyException $e): JsonResponse
    {
        $status = match ($e->reasonCode) {
            'MEMBER_NOT_FOUND', 'VETTING_CONFIRMATION_NOT_FOUND', 'VETTING_REVIEW_REQUEST_NOT_FOUND' => 404,
            'VETTING_SELF_CONFIRMATION_FORBIDDEN', 'VETTING_DECISION_ACTOR_NOT_FOUND' => 403,
            'SAFEGUARDING_POLICY_UNAVAILABLE', 'SAFEGUARDING_JURISDICTION_REQUIRED' => 409,
            default => 422,
        };

        $key = 'api.' . strtolower($e->reasonCode);
        $message = __($key);
        if ($message === $key) {
            $message = __('api.vetting_decision_failed');
        }

        return $this->respondWithError($e->reasonCode, $message, null, $status);
    }

    private function notifyMemberStatusUpdated(int $memberId): void
    {
        $member = DB::table('users')
            ->where('id', $memberId)
            ->where('tenant_id', TenantContext::getId())
            ->select(['id', 'preferred_language'])
            ->first();
        if ($member === null) {
            return;
        }

        try {
            LocaleContext::withLocale($member->preferred_language ?? null, static function () use ($memberId): void {
                Notification::createNotification(
                    $memberId,
                    __('svc_notifications.vetting_status_updated'),
                    '/settings?safeguarding=1',
                    'safeguarding_status_updated',
                    false,
                    TenantContext::getId(),
                );
            });
        } catch (Throwable $e) {
            Log::warning('Safeguarding attestation member notification failed', [
                'tenant_id' => TenantContext::getId(),
                'member_id' => $memberId,
                'exception' => $e::class,
            ]);
        }
    }
}
