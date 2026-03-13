<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\EndorsementService;

/**
 * EndorsementApiController - API for skill endorsements
 *
 * Endpoints:
 * - POST   /api/v2/members/{id}/endorse      - Endorse a member's skill
 * - DELETE /api/v2/members/{id}/endorse       - Remove endorsement
 * - GET    /api/v2/members/{id}/endorsements  - Get endorsements for a member
 * - GET    /api/v2/members/top-endorsed       - Get top endorsed members
 */
class EndorsementApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * POST /api/v2/members/{id}/endorse
     *
     * Request Body:
     * {
     *   "skill_name": "Web Development",
     *   "skill_id": 42,        // optional
     *   "comment": "Great work" // optional
     * }
     */
    public function endorse(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('endorse', 20, 60);

        $data = $this->getAllInput();
        $skillName = $data['skill_name'] ?? '';
        $skillId = !empty($data['skill_id']) ? (int)$data['skill_id'] : null;
        $comment = $data['comment'] ?? null;

        $endorsementId = EndorsementService::endorse($userId, $id, $skillName, $skillId, $comment);

        if ($endorsementId === null) {
            $errors = EndorsementService::getErrors();
            $status = 422;
            if (!empty($errors) && $errors[0]['code'] === 'ALREADY_ENDORSED') {
                $status = 409;
            }
            $this->respondWithErrors($errors, $status);
        }

        $this->respondWithData([
            'endorsement_id' => $endorsementId,
            'message' => 'Endorsement added',
        ], null, 201);
    }

    /**
     * DELETE /api/v2/members/{id}/endorse
     *
     * Request Body or Query:
     * { "skill_name": "Web Development" }
     */
    public function removeEndorsement(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();

        $skillName = $this->input('skill_name') ?? $this->query('skill_name', '');

        if (empty($skillName)) {
            $this->respondWithError('VALIDATION_ERROR', 'skill_name is required', 'skill_name', 400);
            return;
        }

        EndorsementService::removeEndorsement($userId, $id, $skillName);
        $this->respondWithData(['message' => 'Endorsement removed']);
    }

    /**
     * GET /api/v2/members/{id}/endorsements
     *
     * Query: ?skill_name=Web+Development (optional, for detailed view)
     */
    public function getEndorsements(int $id): void
    {
        $this->rateLimit('endorsements_view', 30, 60);

        $skillName = $this->query('skill_name');
        $viewerId = $this->getOptionalUserId();

        if ($skillName) {
            // Detailed endorsements for one skill
            $endorsements = EndorsementService::getSkillEndorsements($id, $skillName);
            $data = [
                'skill_name' => $skillName,
                'endorsements' => $endorsements,
                'count' => count($endorsements),
            ];

            if ($viewerId) {
                $data['has_endorsed'] = EndorsementService::hasEndorsed($viewerId, $id, $skillName);
            }

            $this->respondWithData($data);
        }

        // All endorsements grouped by skill
        $endorsements = EndorsementService::getEndorsementsForUser($id);
        $stats = EndorsementService::getStats($id);

        $this->respondWithData([
            'endorsements' => $endorsements,
            'stats' => $stats,
        ]);
    }

    /**
     * GET /api/v2/members/top-endorsed
     */
    public function getTopEndorsed(): void
    {
        $this->rateLimit('top_endorsed', 10, 60);

        $limit = $this->queryInt('limit', 10, 1, 50);
        $members = EndorsementService::getTopEndorsedMembers($limit);

        $this->respondWithData($members);
    }
}
