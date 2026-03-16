<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\IdeationChallengeService;
use Illuminate\Http\JsonResponse;

/**
 * IdeationChallengesController — Community ideation challenges and idea submissions.
 */
class IdeationChallengesController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly IdeationChallengeService $challengeService,
    ) {}

    /** GET /api/v2/ideation-challenges */
    public function index(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);

        $result = $this->challengeService->getAll($tenantId, $page, $perPage);

        return $this->respondWithPaginatedCollection(
            $result['items'],
            $result['total'],
            $page,
            $perPage
        );
    }

    /** GET /api/v2/ideation-challenges/{id} */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $challenge = $this->challengeService->getById($id, $tenantId);

        if ($challenge === null) {
            return $this->respondWithError('NOT_FOUND', 'Challenge not found', null, 404);
        }

        return $this->respondWithData($challenge);
    }

    /** POST /api/v2/ideation-challenges */
    public function store(): JsonResponse
    {
        $userId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $this->rateLimit('ideation_create', 5, 60);

        $data = $this->getAllInput();

        $challenge = $this->challengeService->create($userId, $tenantId, $data);

        return $this->respondWithData($challenge, null, 201);
    }

    /** POST /api/v2/ideation-challenges/{id}/ideas */
    public function submitIdea(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $this->rateLimit('ideation_submit', 10, 60);

        $data = $this->getAllInput();

        $idea = $this->challengeService->submitIdea($id, $userId, $tenantId, $data);

        if ($idea === null) {
            return $this->respondWithError('NOT_FOUND', 'Challenge not found or closed', null, 404);
        }

        return $this->respondWithData($idea, null, 201);
    }

    /** POST /api/v2/ideation-challenges/{id}/vote */
    public function vote(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $this->rateLimit('ideation_vote', 30, 60);

        $ideaId = $this->requireInput('idea_id');

        $result = $this->challengeService->vote($id, (int) $ideaId, $userId, $tenantId);

        if ($result === null) {
            return $this->respondWithError('VOTE_FAILED', 'Unable to vote', null, 404);
        }

        return $this->respondWithData($result);
    }
}
