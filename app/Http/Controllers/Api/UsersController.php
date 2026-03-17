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

    /** GET /api/v2/users/me — authenticated user's full profile */
    public function me(): JsonResponse
    {
        $userId = $this->requireAuth();
        $profile = $this->userService->getMe($userId);

        if ($profile === null) {
            return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }

        return $this->respondWithData($profile);
    }

    /** PUT /api/v2/users/me — update own profile */
    public function update(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('user_update', 10, 60);

        $data = $this->getAllInput();
        $this->userService->update($userId, $data);

        // Return updated full profile (matches legacy behaviour)
        $profile = $this->userService->getMe($userId);

        return $this->respondWithData($profile);
    }

    /** GET /api/v2/users/{id} — public profile of another user */
    public function show(int $id): JsonResponse
    {
        $viewerId = $this->getOptionalUserId();
        $profile = $this->userService->getPublicProfile($id, $viewerId);

        if ($profile === null) {
            return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }

        return $this->respondWithData($profile);
    }

    /** GET /api/v2/users/search?q= */
    public function search(): JsonResponse
    {
        $q = $this->query('q', '');
        $limit = $this->queryInt('limit', 20, 1, 100);

        $results = $this->userService->search($q, $limit);

        return $this->respondWithData($results);
    }

    /** GET /api/v2/me/stats — sidebar widget stats */
    public function stats(): JsonResponse
    {
        $userId = $this->requireAuth();
        $stats = $this->userService->getProfileStats($userId);

        return $this->respondWithData($stats);
    }

    // ================================================================
    // Delegated methods — complex features that still use legacy services
    // ================================================================

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
