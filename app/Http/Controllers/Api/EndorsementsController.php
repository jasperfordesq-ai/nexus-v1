<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\EndorsementService;
use Illuminate\Http\JsonResponse;

/**
 * EndorsementsController — Skill endorsements between members.
 */
class EndorsementsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly EndorsementService $endorsementService,
    ) {}

    /**
     * POST /api/v2/members/{id}/endorse
     *
     * Endorse a member for a specific skill.
     * Body: skill_id (required), comment (optional).
     */
    public function endorse(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $this->rateLimit('endorse', 20, 60);

        $skillId = (int) $this->requireInput('skill_id');
        $comment = $this->input('comment');

        $result = $this->endorsementService->endorse($userId, $id, $skillId, $tenantId, $comment);

        if ($result === null) {
            return $this->respondWithError('ENDORSEMENT_FAILED', 'Cannot endorse this member', null, 422);
        }

        return $this->respondWithData($result, null, 201);
    }

    /**
     * DELETE /api/v2/members/{id}/endorse
     *
     * Remove an endorsement from a member.
     * Body: skill_id (required).
     */
    public function removeEndorsement(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $skillId = (int) $this->requireInput('skill_id');

        $removed = $this->endorsementService->removeEndorsement($userId, $id, $skillId, $tenantId);

        if (!$removed) {
            return $this->respondWithError('NOT_FOUND', 'Endorsement not found', null, 404);
        }

        return $this->noContent();
    }

    /**
     * GET /api/v2/members/{id}/endorsements
     *
     * Get all endorsements for a member.
     */
    public function getEndorsements(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $endorsements = $this->endorsementService->getForMember($id, $tenantId);

        return $this->respondWithData($endorsements);
    }
}
