<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Nexus\Services\EndorsementService;

/**
 * EndorsementController -- Member endorsement management.
 *
 * Native implementation using legacy EndorsementService static methods.
 * The endorse() method is kept as delegation because it sends email via Mailer.
 *
 * Endpoints:
 *   POST   /api/v2/members/{id}/endorse      endorse()
 *   DELETE /api/v2/members/{id}/endorse       removeEndorsement()
 *   GET    /api/v2/members/{id}/endorsements  getEndorsements()
 *   GET    /api/v2/members/top-endorsed       getTopEndorsed()
 */
class EndorsementController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * POST /api/v2/members/{id}/endorse
     *
     * Endorse a member's skill. Kept as delegation because
     * EndorsementService::endorse() sends email via Mailer/EmailTemplate.
     */
    public function endorse(int $id): JsonResponse
    {
        $this->requireAuth();

        return $this->delegate(\Nexus\Controllers\Api\EndorsementApiController::class, 'endorse', [$id]);
    }

    /**
     * DELETE /api/v2/members/{id}/endorse
     *
     * Remove an endorsement.
     * Body or Query: { "skill_name": "Web Development" }
     */
    public function removeEndorsement(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $skillName = $this->input('skill_name') ?? $this->query('skill_name', '');

        if (empty($skillName)) {
            return $this->respondWithError('VALIDATION_ERROR', 'skill_name is required', 'skill_name', 400);
        }

        EndorsementService::removeEndorsement($userId, $id, $skillName);

        return $this->respondWithData(['message' => 'Endorsement removed']);
    }

    /**
     * GET /api/v2/members/{id}/endorsements
     *
     * Get endorsements for a member.
     * Query: ?skill_name=Web+Development (optional, for detailed view)
     */
    public function getEndorsements(int $id): JsonResponse
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

            return $this->respondWithData($data);
        }

        // All endorsements grouped by skill
        $endorsements = EndorsementService::getEndorsementsForUser($id);
        $stats = EndorsementService::getStats($id);

        return $this->respondWithData([
            'endorsements' => $endorsements,
            'stats' => $stats,
        ]);
    }

    /**
     * GET /api/v2/members/top-endorsed
     *
     * Get top endorsed members in the tenant.
     * Query: ?limit=10 (default 10, max 50)
     */
    public function getTopEndorsed(): JsonResponse
    {
        $this->rateLimit('top_endorsed', 10, 60);

        $limit = $this->queryInt('limit', 10, 1, 50);
        $members = EndorsementService::getTopEndorsedMembers($limit);

        return $this->respondWithData($members);
    }

    /**
     * Delegate to legacy controller via output buffering.
     * Kept only for endorse() which sends email.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        try {
            $controller->$method(...$params);
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError('INTERNAL_ERROR', $e->getMessage(), null, 500);
        }
        $output = ob_get_clean();
        $data = json_decode($output, true);

        if ($data === null) {
            return $this->respondWithData([]);
        }

        return response()->json($data);
    }
}
