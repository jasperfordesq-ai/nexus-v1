<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
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
            ->join('tenant_safeguarding_options as o', function ($join) {
                $join->on('o.id', '=', 'p.option_id')->where('o.is_active', 1);
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
                'o.option_key',
                'o.label',
                'o.description',
                'o.triggers',
            ])
            ->orderBy('o.sort_order')
            ->get();

        $preferences = $rows->map(function ($row) {
            $triggers = is_string($row->triggers)
                ? (json_decode($row->triggers, true) ?: [])
                : (array) ($row->triggers ?? []);

            return [
                'preference_id' => (int) $row->preference_id,
                'option_id' => (int) $row->option_id,
                'option_key' => $row->option_key,
                'label' => $row->label,
                'description' => $row->description,
                'selected_value' => $row->selected_value,
                'consent_given_at' => $row->consent_given_at,
                'created_at' => $row->created_at,
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
}
