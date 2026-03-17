<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Nexus\Services\SubAccountService;

/**
 * SubAccountController -- Parent/child sub-account management.
 *
 * Converted from legacy delegation to direct static service calls.
 */
class SubAccountController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** GET /api/v2/users/me/sub-accounts */
    public function getChildAccounts(): JsonResponse
    {
        $userId = $this->requireAuth();

        $children = SubAccountService::getChildAccounts($userId);

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

        $parents = SubAccountService::getParentAccounts($userId);

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
            return $this->respondWithError('VALIDATION_ERROR', 'child_user_id is required', 'child_user_id', 400);
        }

        $relationshipId = SubAccountService::requestRelationship($userId, $childUserId, $relationshipType, $permissions);

        if ($relationshipId === null) {
            return $this->respondWithErrors(SubAccountService::getErrors(), 422);
        }

        $children = SubAccountService::getChildAccounts($userId);

        return $this->respondWithData($children, null, 201);
    }

    /** PUT /api/v2/users/me/sub-accounts/{id}/approve */
    public function approveRelationship(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $success = SubAccountService::approveRelationship($userId, $id);

        if (!$success) {
            return $this->respondWithErrors(SubAccountService::getErrors(), 422);
        }

        $parents = SubAccountService::getParentAccounts($userId);

        return $this->respondWithData($parents);
    }

    /** PUT /api/v2/users/me/sub-accounts/{id}/permissions */
    public function updatePermissions($id): JsonResponse
    {
        $userId = $this->requireAuth();

        $data = $this->getAllInput();
        $permissions = $data['permissions'] ?? [];

        if (empty($permissions)) {
            return $this->respondWithError('VALIDATION_ERROR', 'permissions object is required', 'permissions', 400);
        }

        $success = SubAccountService::updatePermissions($userId, (int) $id, $permissions);

        if (!$success) {
            return $this->respondWithErrors(SubAccountService::getErrors(), 422);
        }

        $children = SubAccountService::getChildAccounts($userId);

        return $this->respondWithData($children);
    }

    /** DELETE /api/v2/users/me/sub-accounts/{id} */
    public function revokeRelationship(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        SubAccountService::revokeRelationship($userId, $id);

        return $this->respondWithData(['message' => 'Relationship revoked']);
    }

    /** GET /api/v2/users/me/sub-accounts/{childId}/activity */
    public function getChildActivity($childId): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('sub_account_activity', 10, 60);

        $activity = SubAccountService::getChildActivitySummary($userId, (int) $childId);

        if ($activity === null) {
            $errors = SubAccountService::getErrors();
            $status = 403;
            if (!empty($errors) && ($errors[0]['code'] ?? '') === 'FORBIDDEN') {
                $status = 403;
            }
            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithData($activity);
    }
}
