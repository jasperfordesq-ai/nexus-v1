<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\UserService;
use Illuminate\Http\JsonResponse;

/**
 * AdminUsersController — Admin user management (list, view, update, approve).
 *
 * All methods require admin authentication.
 */
class AdminUsersController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly UserService $userService,
    ) {}

    /**
     * GET /api/v2/admin/users
     *
     * List all users with filtering and pagination.
     * Query params: q, status, role, page, per_page, sort_by, sort_dir.
     */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $filters = [
            'search' => $this->query('q'),
            'status' => $this->query('status'),
            'role' => $this->query('role'),
            'sort_by' => $this->query('sort_by', 'created_at'),
            'sort_dir' => $this->query('sort_dir', 'desc'),
        ];

        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);

        $result = $this->userService->getAllForAdmin($tenantId, $filters, $page, $perPage);

        return $this->respondWithPaginatedCollection(
            $result['items'],
            $result['total'],
            $page,
            $perPage
        );
    }

    /** GET /api/v2/admin/users/{id} */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $user = $this->userService->getByIdForAdmin($id, $tenantId);

        if ($user === null) {
            return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }

        return $this->respondWithData($user);
    }

    /**
     * PUT /api/v2/admin/users/{id}
     *
     * Update a user's profile or role (admin only).
     * Body: name, email, role, status, notes.
     */
    public function update(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $data = $this->getAllInput();

        $user = $this->userService->updateForAdmin($id, $tenantId, $data);

        if ($user === null) {
            return $this->respondWithError('NOT_FOUND', 'User not found', null, 404);
        }

        return $this->respondWithData($user);
    }

    /**
     * POST /api/v2/admin/users/{id}/approve
     *
     * Approve a pending user registration (admin only).
     */
    public function approve(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $result = $this->userService->approveUser($id, $tenantId);

        if ($result === null) {
            return $this->respondWithError('NOT_FOUND', 'User not found or already approved', null, 404);
        }

        return $this->respondWithData($result);
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


    public function store(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'store');
    }


    public function import(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'import');
    }


    public function importTemplate(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'importTemplate');
    }


    public function destroy($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'destroy', [$id]);
    }


    public function suspend($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'suspend', [$id]);
    }


    public function ban($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'ban', [$id]);
    }


    public function reactivate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'reactivate', [$id]);
    }


    public function reset2fa($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'reset2fa', [$id]);
    }


    public function addBadge($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'addBadge', [$id]);
    }


    public function removeBadge($id, $badgeId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'removeBadge', [$id, $badgeId]);
    }


    public function impersonate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'impersonate', [$id]);
    }


    public function setSuperAdmin($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'setSuperAdmin', [$id]);
    }


    public function setGlobalSuperAdmin($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'setGlobalSuperAdmin', [$id]);
    }


    public function recheckBadges($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'recheckBadges', [$id]);
    }


    public function getConsents($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'getConsents', [$id]);
    }


    public function setPassword($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'setPassword', [$id]);
    }


    public function sendPasswordReset($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'sendPasswordReset', [$id]);
    }


    public function sendWelcomeEmail($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminUsersApiController::class, 'sendWelcomeEmail', [$id]);
    }

}
