<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\UserService;

/**
 * UsersController -- User profiles, current user, and member search.
 */
class UsersController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly UserService $userService,
    ) {}

    /** GET /api/v2/users/me */
    public function me(): JsonResponse
    {
        $userId = $this->requireAuth();
        $profile = $this->userService->getProfile($userId, $this->getTenantId());
        
        if ($profile === null) {
            return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }
        
        return $this->respondWithData($profile);
    }

    /** PUT /api/v2/users/me */
    public function update(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('user_update', 10, 60);
        
        $data = $this->getAllInput();
        $user = $this->userService->updateProfile($userId, $this->getTenantId(), $data);
        
        return $this->respondWithData($user);
    }

    /** GET /api/v2/users/{id} */
    public function show(int $id): JsonResponse
    {
        $viewerId = $this->getOptionalUserId();
        $user = $this->userService->getPublicProfile($id, $this->getTenantId(), $viewerId);
        
        if ($user === null) {
            return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }
        
        return $this->respondWithData($user);
    }

    /** GET /api/v2/users/search?q= */
    public function search(): JsonResponse
    {
        $q = $this->query('q', '');
        $limit = $this->queryInt('limit', 20, 1, 100);
        
        $results = $this->userService->search($q, $this->getTenantId(), $limit);
        
        return $this->respondWithData($results);
    }

}
