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


    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }


    public function stats(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UsersApiController::class, 'stats');
    }


    public function getPreferences(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UsersApiController::class, 'getPreferences');
    }


    public function updatePreferences(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UsersApiController::class, 'updatePreferences');
    }


    public function updateTheme(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UsersApiController::class, 'updateTheme');
    }


    public function updateLanguage(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UsersApiController::class, 'updateLanguage');
    }


    public function updateAvatar(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UsersApiController::class, 'updateAvatar');
    }


    public function updatePassword(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UsersApiController::class, 'updatePassword');
    }


    public function deleteAccount(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UsersApiController::class, 'deleteAccount');
    }


    public function myListings(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UsersApiController::class, 'myListings');
    }


    public function notificationPreferences(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UsersApiController::class, 'notificationPreferences');
    }


    public function updateNotificationPreferences(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UsersApiController::class, 'updateNotificationPreferences');
    }


    public function getConsent(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UsersApiController::class, 'getConsent');
    }


    public function updateConsent(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UsersApiController::class, 'updateConsent');
    }


    public function createGdprRequest(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UsersApiController::class, 'createGdprRequest');
    }


    public function sessions(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UsersApiController::class, 'sessions');
    }


    public function listings($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UsersApiController::class, 'listings', [$id]);
    }


    public function nearby(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\UsersApiController::class, 'nearby');
    }


    public function updateSettings(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\UserPreferenceController::class, 'updateSettings');
    }

}
