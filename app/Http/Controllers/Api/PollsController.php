<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\PollService;

/**
 * PollsController -- Community polls with voting support.
 */
class PollsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly PollService $pollService,
    ) {}

    /** GET /api/v2/polls */
    public function index(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);

        $result = $this->pollService->getActive($tenantId, $page, $perPage);

        return $this->respondWithPaginatedCollection(
            $result['items'], $result['total'], $page, $perPage
        );
    }

    /** GET /api/v2/polls/{id} */
    public function show(int $id): JsonResponse
    {
        $poll = $this->pollService->getById($id, $this->getTenantId());

        if ($poll === null) {
            return $this->respondWithError('NOT_FOUND', 'Poll not found', null, 404);
        }

        return $this->respondWithData($poll);
    }

    /** POST /api/v2/polls */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('poll_create', 5, 60);

        $data = $this->getAllInput();
        $poll = $this->pollService->create($userId, $this->getTenantId(), $data);

        return $this->respondWithData($poll, null, 201);
    }

    /** POST /api/v2/polls/{id}/vote */
    public function vote(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('poll_vote', 30, 60);

        $optionId = $this->requireInput('option_id');
        $result = $this->pollService->castVote($id, $userId, $this->getTenantId(), (int) $optionId);

        if ($result === null) {
            return $this->respondWithError('NOT_FOUND', 'Poll not found', null, 404);
        }

        return $this->respondWithData($result);
    }
}
