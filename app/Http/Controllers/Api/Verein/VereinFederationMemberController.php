<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api\Verein;

use App\Core\TenantContext;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\Verein\VereinFederationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * AG55 — Member-facing endpoints for cross-Verein invitations + joint calendar.
 */
class VereinFederationMemberController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(private readonly VereinFederationService $service)
    {
    }

    /**
     * GET /v2/vereine/{organizationId}/cross-invitations
     * Lists invitations involving the auth'd user that relate to this Verein.
     */
    public function listForVerein(int $organizationId): JsonResponse
    {
        if ($guard = $this->guardCaringCommunity()) return $guard;
        $userId = $this->requireAuth();
        $tenantId = TenantContext::getId();

        $rows = DB::table('verein_cross_invitations')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($organizationId) {
                $q->where('source_organization_id', $organizationId)
                    ->orWhere('target_organization_id', $organizationId);
            })
            ->where(function ($q) use ($userId) {
                $q->where('inviter_user_id', $userId)->orWhere('invitee_user_id', $userId);
            })
            ->orderByDesc('sent_at')
            ->get();

        return $this->respondWithData($rows);
    }

    /**
     * POST /v2/vereine/{organizationId}/cross-invitations
     */
    public function create(Request $request, int $organizationId): JsonResponse
    {
        if ($guard = $this->guardCaringCommunity()) return $guard;
        $inviterId = $this->requireAuth();

        $targetOrgId = (int) $request->input('target_organization_id');
        $inviteeUserId = (int) $request->input('invitee_user_id');
        $message = $request->input('message');

        if (!$targetOrgId || !$inviteeUserId) {
            return $this->respondWithError('VALIDATION_ERROR', __('verein_federation.target_and_invitee_required'), null, 422);
        }

        try {
            $invite = $this->service->sendCrossInvitation(
                $organizationId,
                $targetOrgId,
                $inviterId,
                $inviteeUserId,
                is_string($message) ? $message : null
            );
            return $this->respondWithData($invite, null, 201);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    /**
     * GET /v2/me/verein-invitations
     */
    public function listMine(): JsonResponse
    {
        if ($guard = $this->guardCaringCommunity()) return $guard;
        $userId = $this->requireAuth();
        return $this->respondWithData($this->service->listInvitationsForUser($userId));
    }

    /**
     * GET /v2/vereine/cross-invite-targets/{userId}
     *
     * Returns inviteable federated Vereine for the profile being viewed.
     */
    public function crossInviteTargets(int $userId): JsonResponse
    {
        if ($guard = $this->guardCaringCommunity()) return $guard;
        $viewerUserId = $this->requireAuth();

        return $this->respondWithData($this->service->getCrossInviteTargets($viewerUserId, $userId));
    }

    /**
     * POST /v2/me/verein-invitations/{id}/respond
     */
    public function respond(Request $request, int $id): JsonResponse
    {
        if ($guard = $this->guardCaringCommunity()) return $guard;
        $userId = $this->requireAuth();
        $action = (string) $request->input('action', '');

        try {
            $invite = $this->service->respondToInvitation($id, $userId, $action);
            return $this->respondWithData($invite);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    /**
     * GET /v2/municipality/{municipalityCode}/events-calendar  (public, throttled)
     */
    public function municipalityCalendar(Request $request, string $municipalityCode): JsonResponse
    {
        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }

        $tenantId = TenantContext::getId();
        $period = (string) $request->query('period', 'month');
        return $this->respondWithData($this->service->getMunicipalityCalendar($tenantId, $municipalityCode, $period));
    }

    private function guardCaringCommunity(): ?JsonResponse
    {
        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }
        return null;
    }
}
