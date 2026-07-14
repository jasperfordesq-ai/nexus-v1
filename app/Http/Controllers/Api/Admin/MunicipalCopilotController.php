<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\CaringCommunity\MunicipalCommunicationCopilotService;
use Illuminate\Http\JsonResponse;

/**
 * AG89 — Municipal Communication & Moderation Copilot.
 *
 * Endpoints for the draft → AI review → auditable proposal → human-accept
 * flow. Distinct from AG14 announcements (raw publish) and AG61 KI-Agenten
 * (autonomous agent runs). Accepting a proposal publishes it in the same
 * flow: the polished text is broadcast via the existing announcement surface
 * (EmergencyAlertService::createAndBroadcast, severity "info") and the
 * proposal is stamped published with the source_announcement_id. Re-accepting
 * is idempotent and never double-publishes; if the broadcast fails the
 * proposal stays "accepted" so the admin can retry from the UI.
 *
 * Tenant-scoped via TenantContext::getId(). Feature gate `caring_community`
 * enforced inline as defence in depth on top of the route-level admin
 * middleware.
 */
class MunicipalCopilotController extends BaseApiController
{
    protected bool $isV2Api = true;

    private const MAX_DRAFT_CHARS = 4000;
    private const MAX_REASON_CHARS = 600;

    public function __construct(
        private readonly MunicipalCommunicationCopilotService $service,
    ) {
    }

    /** GET /v2/admin/caring-community/copilot/proposals */
    public function index(): JsonResponse
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $limit = $this->queryInt('limit', 20, 1, MunicipalCommunicationCopilotService::MAX_PROPOSALS) ?? 20;

        $items = $this->service->listProposals(TenantContext::getId(), $limit);

        return $this->respondWithData([
            'items'  => $items,
            'limit'  => $limit,
        ]);
    }

    /** POST /v2/admin/caring-community/copilot/proposals */
    public function generate(): JsonResponse
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $adminUserId = $this->requireAdmin();

        $draft = trim((string) $this->input('draft', ''));
        if ($draft === '') {
            return $this->respondWithError(
                'VALIDATION_REQUIRED',
                __('api.missing_required_field', ['field' => 'draft']),
                'draft',
                422,
            );
        }
        if (mb_strlen($draft) > self::MAX_DRAFT_CHARS) {
            return $this->respondWithError(
                'VALIDATION_LENGTH',
                __('api.field_max_characters', ['field' => 'draft', 'max' => self::MAX_DRAFT_CHARS]),
                'draft',
                422,
            );
        }

        $audienceHintRaw = $this->input('audience_hint');
        $audienceHint = is_string($audienceHintRaw) && trim($audienceHintRaw) !== ''
            ? mb_substr(trim($audienceHintRaw), 0, 120)
            : null;

        $subRegionIdRaw = $this->input('sub_region_id');
        $subRegionId = is_string($subRegionIdRaw) && trim($subRegionIdRaw) !== ''
            ? mb_substr(trim($subRegionIdRaw), 0, 64)
            : null;

        $proposal = $this->service->generateProposal(
            TenantContext::getId(),
            $adminUserId,
            $draft,
            $audienceHint,
            $subRegionId,
        );

        return $this->respondWithData(['proposal' => $proposal], null, 201);
    }

    /** POST /v2/admin/caring-community/copilot/proposals/{proposalId}/accept */
    public function accept(string $proposalId): JsonResponse
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $adminUserId = $this->requireAdmin();

        $editedFields = [];
        $editedPolished = $this->input('edited_polished_text');
        if (is_string($editedPolished) && $editedPolished !== '') {
            if (mb_strlen($editedPolished) > self::MAX_DRAFT_CHARS) {
                return $this->respondWithError(
                    'VALIDATION_LENGTH',
                    __('api.field_max_characters', [
                        'field' => 'edited_polished_text',
                        'max' => self::MAX_DRAFT_CHARS,
                    ]),
                    'edited_polished_text',
                    422,
                );
            }
            $editedFields['edited_polished_text'] = $editedPolished;
        }
        $editedAudience = $this->input('edited_audience');
        if (is_string($editedAudience) && trim($editedAudience) !== '') {
            $editedFields['edited_audience'] = mb_substr(trim($editedAudience), 0, 120);
        }

        $proposal = $this->service->acceptAndPublish(
            TenantContext::getId(),
            $proposalId,
            $editedFields !== [] ? $editedFields : null,
            $adminUserId,
        );

        if ($proposal === null) {
            return $this->respondNotFound(__('api.proposal_not_found'));
        }

        return $this->respondWithData([
            'proposal'  => $proposal,
            'published' => ($proposal['status'] ?? '') === 'published',
        ]);
    }

    /** POST /v2/admin/caring-community/copilot/proposals/{proposalId}/reject */
    public function reject(string $proposalId): JsonResponse
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $adminUserId = $this->requireAdmin();

        $reason = trim((string) $this->input('reason', ''));
        if ($reason === '') {
            return $this->respondWithError(
                'VALIDATION_REQUIRED',
                __('api.missing_required_field', ['field' => 'reason']),
                'reason',
                422,
            );
        }
        if (mb_strlen($reason) > self::MAX_REASON_CHARS) {
            return $this->respondWithError(
                'VALIDATION_LENGTH',
                __('api.field_max_characters', ['field' => 'reason', 'max' => self::MAX_REASON_CHARS]),
                'reason',
                422,
            );
        }

        $proposal = $this->service->rejectProposal(
            TenantContext::getId(),
            $proposalId,
            $reason,
            $adminUserId,
        );

        if ($proposal === null) {
            return $this->respondNotFound(__('api.proposal_not_found'));
        }

        return $this->respondWithData(['proposal' => $proposal]);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function guard(): ?JsonResponse
    {
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondForbidden(__('api.caring_community_feature_disabled'));
        }

        return null;
    }
}

/*
 * Routes to register in routes/api.php:
 *   GET    /v2/admin/caring-community/copilot/proposals                       => index
 *   POST   /v2/admin/caring-community/copilot/proposals                       => generate
 *   POST   /v2/admin/caring-community/copilot/proposals/{proposalId}/accept   => accept
 *   POST   /v2/admin/caring-community/copilot/proposals/{proposalId}/reject   => reject
 */
