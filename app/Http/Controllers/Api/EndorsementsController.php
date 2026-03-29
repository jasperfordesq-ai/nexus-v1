<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Models\Notification;
use App\Models\User;
use App\Services\EndorsementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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

        // Notify the endorsed user (skip if endorsing yourself, which the service already blocks)
        try {
            if ($userId !== $id) {
                $endorser = User::find($userId, ['id', 'first_name', 'last_name']);
                $endorserName = $endorser
                    ? trim(($endorser->first_name ?? '') . ' ' . ($endorser->last_name ?? ''))
                    : 'Someone';
                if (empty($endorserName)) {
                    $endorserName = 'Someone';
                }

                // Look up the skill name from the endorsement record
                $skillName = DB::table('skill_endorsements')
                    ->where('endorser_id', $userId)
                    ->where('endorsed_id', $id)
                    ->where('tenant_id', $tenantId)
                    ->orderByDesc('created_at')
                    ->value('skill_name') ?? 'a skill';

                Notification::createNotification(
                    $id,
                    "{$endorserName} endorsed your {$skillName} skill",
                    "/members/{$id}",
                    'endorsement'
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('Endorsement notification failed', [
                'endorser' => $userId,
                'endorsed' => $id,
                'error' => $e->getMessage(),
            ]);
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

        // Look up skill name before deletion (needed for notification)
        $skillName = null;
        try {
            $skillName = DB::table('skill_endorsements')
                ->where('endorser_id', $userId)
                ->where('endorsed_id', $id)
                ->where('skill_id', $skillId)
                ->where('tenant_id', $tenantId)
                ->value('skill_name');
        } catch (\Throwable $e) {
            // Non-critical — notification will use fallback
        }

        $removed = $this->endorsementService->removeEndorsement($userId, $id, $skillId, $tenantId);

        if (!$removed) {
            return $this->respondWithError('NOT_FOUND', 'Endorsement not found', null, 404);
        }

        // Notify the endorsed user about the withdrawn endorsement
        try {
            if ($userId !== $id) {
                $endorser = User::find($userId, ['id', 'first_name', 'last_name']);
                $endorserName = $endorser
                    ? trim(($endorser->first_name ?? '') . ' ' . ($endorser->last_name ?? ''))
                    : 'Someone';
                if (empty($endorserName)) {
                    $endorserName = 'Someone';
                }

                $skillLabel = $skillName ?? 'a skill';

                Notification::createNotification(
                    $id,
                    "{$endorserName} withdrew their endorsement of your {$skillLabel} skill",
                    "/members/{$id}",
                    'endorsement'
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('Endorsement removal notification failed', [
                'endorser' => $userId,
                'endorsed' => $id,
                'error' => $e->getMessage(),
            ]);
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
