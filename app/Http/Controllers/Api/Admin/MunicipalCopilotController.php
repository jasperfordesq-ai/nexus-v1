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
 * (autonomous agent runs). Acceptance here only marks the proposal accepted;
 * the actual announcement publish must still be done via the existing
 * announcement surface (see TODO in accept()).
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
            return $this->respondWithError('VALIDATION_REQUIRED', 'Draft text is required.', 'draft', 422);
        }
        if (mb_strlen($draft) > self::MAX_DRAFT_CHARS) {
            return $this->respondWithError(
                'VALIDATION_LENGTH',
                'Draft must be ' . self::MAX_DRAFT_CHARS . ' characters or fewer.',
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
                    'Edited polished text must be ' . self::MAX_DRAFT_CHARS . ' characters or fewer.',
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

        $proposal = $this->service->acceptProposal(
            TenantContext::getId(),
            $proposalId,
            $editedFields !== [] ? $editedFields : null,
            $adminUserId,
        );

        if ($proposal === null) {
            return $this->respondNotFound('Proposal not found.');
        }

        // TODO(AG89): wire acceptance into the existing announcement publish
        // path. The intended integration point is
        // App\Http\Controllers\Api\EmergencyAlertController::store — once an
        // admin accepts, we should POST the polished_text + audience to the
        // existing emergency-alert / announcement endpoint, then call
        // $this->service->markPublished(tenantId, proposalId, $newAnnouncementId)
        // to stamp the audit trail. For this MVP the page surfaces a "Now
        // publish via the announcements admin" link instead.

        return $this->respondWithData(['proposal' => $proposal]);
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
            return $this->respondWithError('VALIDATION_REQUIRED', 'Rejection reason is required.', 'reason', 422);
        }
        if (mb_strlen($reason) > self::MAX_REASON_CHARS) {
            return $this->respondWithError(
                'VALIDATION_LENGTH',
                'Reason must be ' . self::MAX_REASON_CHARS . ' characters or fewer.',
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
            return $this->respondNotFound('Proposal not found.');
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
            return $this->respondForbidden('Caring Community feature is not enabled for this tenant.');
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
