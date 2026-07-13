<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Exceptions\SafeguardingPolicyException;
use App\I18n\LocaleContext;
use App\Models\Notification;
use App\Models\TenantSafeguardingOption;
use App\Models\UserSafeguardingPreference;
use App\Services\MemberVettingAttestationService;
use App\Services\SafeguardingPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Member-facing safeguarding endpoints (Tier 3b).
 *
 * Adults who self-identify as requiring safeguarding protections retain the
 * right to view and revoke those preferences without admin involvement —
 * Safeguarding Ireland adult-autonomy principle. Admins cannot block a member
 * self-revoking their own flags.
 */
class SafeguardingMemberController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly MemberVettingAttestationService $attestations,
    ) {}

    /**
     * GET /v2/safeguarding/my-preferences
     *
     * Returns the signed-in member's active safeguarding preferences.
     * Also dismisses any pending annual-review reminder (sets review_confirmed_at).
     */
    public function myPreferences(Request $request): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        // Record that the member viewed their preferences — this counts as
        // "confirming" the annual review for any pending-review rows.
        try {
            DB::table('user_safeguarding_preferences')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->whereNull('revoked_at')
                ->whereNotNull('review_reminder_sent_at')
                ->whereNull('review_confirmed_at')
                ->update(['review_confirmed_at' => now()]);
        } catch (\Throwable $e) {
            // Non-fatal — preference fetch continues.
            Log::warning('SafeguardingMemberController: review confirmation stamp failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        $rows = DB::table('user_safeguarding_preferences as p')
            ->join('tenant_safeguarding_options as o', function ($join) use ($tenantId) {
                $join->on('o.id', '=', 'p.option_id')->where('o.tenant_id', $tenantId)->where('o.is_active', 1);
            })
            ->where('p.tenant_id', $tenantId)
            ->where('p.user_id', $userId)
            ->whereNull('p.revoked_at')
            ->select([
                'p.id as preference_id',
                'p.option_id',
                'p.selected_value',
                'p.consent_given_at',
                'p.created_at',
                'p.review_reminder_sent_at',
                'p.review_confirmed_at',
                'p.policy_review_required_at',
                'p.policy_review_reason_code',
                'o.option_key',
                'o.option_type',
                'o.preset_source',
                'o.label',
                'o.description',
                'o.triggers',
            ])
            ->orderBy('o.sort_order')
            ->get();

        $rows = $rows->filter(static fn ($row): bool => UserSafeguardingPreference::isEffectivelySelected(
                $row->option_type ?? null,
                $row->selected_value ?? null,
            ))
            ->values();

        $preferences = $rows->map(function ($row) {
            $triggers = is_string($row->triggers)
                ? (json_decode($row->triggers, true) ?: [])
                : (array) ($row->triggers ?? []);

            return [
                'preference_id' => (int) $row->preference_id,
                'option_id' => (int) $row->option_id,
                'option_key' => $row->option_key,
                'label' => TenantSafeguardingOption::localizeOptionText(
                    $row->preset_source,
                    $row->option_key,
                    'label',
                    $row->label,
                ),
                'description' => TenantSafeguardingOption::localizeOptionText(
                    $row->preset_source,
                    $row->option_key,
                    'description',
                    $row->description,
                ),
                'selected_value' => $row->selected_value,
                'consent_given_at' => $row->consent_given_at,
                'created_at' => $row->created_at,
                'policy_review_required' => $row->policy_review_required_at !== null,
                'policy_review_reason_code' => $row->policy_review_reason_code,
                'activations' => [
                    'requires_broker_approval' => (bool) ($triggers['requires_broker_approval'] ?? false),
                    'restricts_messaging' => (bool) ($triggers['restricts_messaging'] ?? false),
                    'restricts_matching' => (bool) ($triggers['restricts_matching'] ?? false),
                    'requires_vetted_interaction' => (bool) ($triggers['requires_vetted_interaction'] ?? false),
                    'vetting_type_required' => $triggers['vetting_type_required'] ?? null,
                ],
            ];
        })->all();

        return $this->respondWithData([
            'preferences' => $preferences,
            'count' => count($preferences),
        ]);
    }

    /**
     * POST /v2/safeguarding/revoke
     *
     * Member-initiated revocation of a single preference. Accepts:
     *   { option_id: int }  — the option the member wants to revoke
     *
     * Returns 200 with the updated preference list on success, 404 if the
     * preference does not exist (or is already revoked) for this member.
     */
    public function revoke(Request $request): JsonResponse
    {
        $userId = $this->requireAuth();

        $validated = $request->validate([
            'option_id' => 'required|integer|min:1',
        ]);

        $optionId = (int) $validated['option_id'];

        $revoked = SafeguardingPreferenceService::revokePreference($userId, $optionId);

        if (!$revoked) {
            return $this->respondWithError(
                'NOT_FOUND',
                __('safeguarding.errors.revoke_failed'),
                'option_id',
                404
            );
        }

        return $this->respondWithData([
            'revoked' => true,
            'option_id' => $optionId,
        ]);
    }

    /** POST /v2/safeguarding/confirm-policy-review */
    public function confirmPolicyReview(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();
        $updated = DB::table('user_safeguarding_preferences')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->whereNotNull('policy_review_required_at')
            ->update([
                'policy_review_required_at' => null,
                'policy_review_reason_code' => null,
                'consent_given_at' => now(),
            ]);

        return $this->respondWithData([
            'confirmed' => true,
            'updated_count' => $updated,
            'message' => __('api.safeguarding_policy_review_confirmed'),
        ]);
    }

    /** GET /v2/safeguarding/my-vetting-status */
    public function myVettingStatus(): JsonResponse
    {
        $userId = $this->requireAuth();

        return $this->respondWithData(
            $this->attestations->getMemberStatus(TenantContext::getId(), $userId)
        );
    }

    /**
     * POST /v2/safeguarding/vetting-review-request
     *
     * Empty-body workflow by design: no certificate, attachment, reference,
     * date, result, notes, or other evidence is accepted.
     */
    public function requestVettingReview(): JsonResponse
    {
        $userId = $this->requireAuth();
        if (request()->allFiles() !== [] || $this->getAllInput() !== []) {
            return $this->respondWithError(
                'VETTING_EVIDENCE_PROHIBITED',
                __('api.vetting_review_request_must_be_empty'),
                'request',
                422,
            );
        }

        try {
            $request = $this->attestations->requestReview(TenantContext::getId(), $userId);
            $this->notifyDecisionMakersOfReviewRequest();

            return $this->respondWithData($request, null, 201);
        } catch (SafeguardingPolicyException $e) {
            $status = in_array($e->reasonCode, ['SAFEGUARDING_JURISDICTION_REQUIRED', 'SAFEGUARDING_POLICY_UNAVAILABLE'], true)
                ? 409
                : 422;
            $key = 'api.' . strtolower($e->reasonCode);
            $message = __($key);

            return $this->respondWithError(
                $e->reasonCode,
                $message === $key ? __('api.vetting_review_request_failed') : $message,
                null,
                $status,
            );
        }
    }

    private function notifyDecisionMakersOfReviewRequest(): void
    {
        $tenantId = TenantContext::getId();
        $recipients = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->where(function ($query): void {
                $query->where('role', 'broker')
                    ->orWhereIn('role', ['admin', 'tenant_admin', 'super_admin', 'god'])
                    ->orWhere('is_admin', 1)
                    ->orWhere('is_super_admin', 1)
                    ->orWhere('is_tenant_super_admin', 1)
                    ->orWhere('is_god', 1);
            })
            ->whereNotIn('status', ['deleted', 'deactivated', 'suspended', 'banned'])
            ->select(['id', 'preferred_language'])
            ->get();

        foreach ($recipients as $recipient) {
            LocaleContext::withLocale($recipient->preferred_language ?? null, static function () use ($recipient, $tenantId): void {
                Notification::createNotification(
                    (int) $recipient->id,
                    __('svc_notifications.vetting_review_requested'),
                    '/broker/vetting?status=review_requested',
                    'safeguarding_review_requested',
                    false,
                    $tenantId,
                );
            });
        }
    }
}
