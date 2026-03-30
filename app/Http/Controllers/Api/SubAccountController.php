<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\SubAccountService;

/**
 * SubAccountController -- Parent/child sub-account management.
 *
 * Converted from legacy delegation to direct static service calls.
 */
class SubAccountController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly SubAccountService $subAccountService,
    ) {}

    /** GET /api/v2/users/me/sub-accounts */
    public function getChildAccounts(): JsonResponse
    {
        $userId = $this->requireAuth();

        $children = $this->subAccountService->getChildAccounts($userId);

        foreach ($children as &$child) {
            if (is_string($child['permissions'] ?? null)) {
                $child['permissions'] = json_decode($child['permissions'], true);
            }
        }

        return $this->respondWithData($children);
    }

    /** GET /api/v2/users/me/parent-accounts */
    public function getParentAccounts(): JsonResponse
    {
        $userId = $this->requireAuth();

        $parents = $this->subAccountService->getParentAccounts($userId);

        foreach ($parents as &$parent) {
            if (is_string($parent['permissions'] ?? null)) {
                $parent['permissions'] = json_decode($parent['permissions'], true);
            }
        }

        return $this->respondWithData($parents);
    }

    /** POST /api/v2/users/me/sub-accounts */
    public function requestRelationship(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('sub_account_request', 5, 60);

        $data = $this->getAllInput();
        $childUserId = (int) ($data['child_user_id'] ?? 0);
        $relationshipType = $data['relationship_type'] ?? 'family';
        $permissions = $data['permissions'] ?? [];

        if ($childUserId <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.missing_required_field', ['field' => 'child_user_id']), 'child_user_id', 400);
        }

        $relationshipId = $this->subAccountService->requestRelationship($userId, $childUserId, $relationshipType, $permissions);

        if ($relationshipId === null) {
            return $this->respondWithErrors($this->subAccountService->getErrors(), 422);
        }

        $children = $this->subAccountService->getChildAccounts($userId);

        return $this->respondWithData($children, null, 201);
    }

    /** PUT /api/v2/users/me/sub-accounts/{id}/approve */
    public function approveRelationship(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $success = $this->subAccountService->approveRelationship($userId, $id);

        if (!$success) {
            return $this->respondWithErrors($this->subAccountService->getErrors(), 422);
        }

        $parents = $this->subAccountService->getParentAccounts($userId);

        return $this->respondWithData($parents);
    }

    /** PUT /api/v2/users/me/sub-accounts/{id}/permissions */
    public function updatePermissions($id): JsonResponse
    {
        $userId = $this->requireAuth();

        $data = $this->getAllInput();
        $permissions = $data['permissions'] ?? [];

        if (empty($permissions)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.missing_required_field', ['field' => 'permissions']), 'permissions', 400);
        }

        $success = $this->subAccountService->updatePermissions($userId, (int) $id, $permissions);

        if (!$success) {
            return $this->respondWithErrors($this->subAccountService->getErrors(), 422);
        }

        $children = $this->subAccountService->getChildAccounts($userId);

        return $this->respondWithData($children);
    }

    /** DELETE /api/v2/users/me/sub-accounts/{id} */
    public function revokeRelationship(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $this->subAccountService->revokeRelationship($userId, $id);

        return $this->respondWithData(['message' => 'Relationship revoked']);
    }

    /** GET /api/v2/users/me/sub-accounts/{childId}/activity */
    public function getChildActivity($childId): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('sub_account_activity', 10, 60);

        $activity = $this->subAccountService->getChildActivitySummary($userId, (int) $childId);

        if ($activity === null) {
            $errors = $this->subAccountService->getErrors();
            $status = 403;
            if (!empty($errors) && ($errors[0]['code'] ?? '') === 'FORBIDDEN') {
                $status = 403;
            }
            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithData($activity);
    }
}
