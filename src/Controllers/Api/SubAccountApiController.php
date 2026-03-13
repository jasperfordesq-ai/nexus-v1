<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\SubAccountService;

/**
 * SubAccountApiController - API for sub-account/family account management
 *
 * Endpoints:
 * - GET    /api/v2/users/me/sub-accounts             - Get child accounts
 * - GET    /api/v2/users/me/parent-accounts           - Get parent accounts
 * - POST   /api/v2/users/me/sub-accounts              - Request relationship
 * - PUT    /api/v2/users/me/sub-accounts/{id}/approve  - Approve relationship
 * - PUT    /api/v2/users/me/sub-accounts/{id}/permissions - Update permissions
 * - DELETE /api/v2/users/me/sub-accounts/{id}          - Revoke relationship
 * - GET    /api/v2/users/me/sub-accounts/{childId}/activity - Get child activity
 */
class SubAccountApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/users/me/sub-accounts
     */
    public function getChildAccounts(): void
    {
        $userId = $this->getUserId();
        $children = SubAccountService::getChildAccounts($userId);

        // Decode permissions JSON for each
        foreach ($children as &$child) {
            if (is_string($child['permissions'] ?? null)) {
                $child['permissions'] = json_decode($child['permissions'], true);
            }
        }

        $this->respondWithData($children);
    }

    /**
     * GET /api/v2/users/me/parent-accounts
     */
    public function getParentAccounts(): void
    {
        $userId = $this->getUserId();
        $parents = SubAccountService::getParentAccounts($userId);

        foreach ($parents as &$parent) {
            if (is_string($parent['permissions'] ?? null)) {
                $parent['permissions'] = json_decode($parent['permissions'], true);
            }
        }

        $this->respondWithData($parents);
    }

    /**
     * POST /api/v2/users/me/sub-accounts
     *
     * Request Body:
     * {
     *   "child_user_id": 42,
     *   "relationship_type": "family",   // family, guardian, carer, organization
     *   "permissions": {
     *     "can_view_activity": true,
     *     "can_manage_listings": false,
     *     "can_transact": false
     *   }
     * }
     */
    public function requestRelationship(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('sub_account_request', 5, 60);

        $data = $this->getAllInput();
        $childUserId = (int)($data['child_user_id'] ?? 0);
        $relationshipType = $data['relationship_type'] ?? 'family';
        $permissions = $data['permissions'] ?? [];

        if ($childUserId <= 0) {
            $this->respondWithError('VALIDATION_ERROR', 'child_user_id is required', 'child_user_id', 400);
            return;
        }

        $relationshipId = SubAccountService::requestRelationship($userId, $childUserId, $relationshipType, $permissions);

        if ($relationshipId === null) {
            $this->respondWithErrors(SubAccountService::getErrors(), 422);
        }

        $children = SubAccountService::getChildAccounts($userId);
        $this->respondWithData($children, null, 201);
    }

    /**
     * PUT /api/v2/users/me/sub-accounts/{id}/approve
     * Approve a relationship request (as the child user)
     */
    public function approveRelationship(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();

        $success = SubAccountService::approveRelationship($userId, $id);

        if (!$success) {
            $this->respondWithErrors(SubAccountService::getErrors(), 422);
        }

        $parents = SubAccountService::getParentAccounts($userId);
        $this->respondWithData($parents);
    }

    /**
     * PUT /api/v2/users/me/sub-accounts/{id}/permissions
     * Update permissions for a relationship (as the parent)
     */
    public function updatePermissions(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();

        $data = $this->getAllInput();
        $permissions = $data['permissions'] ?? [];

        if (empty($permissions)) {
            $this->respondWithError('VALIDATION_ERROR', 'permissions object is required', 'permissions', 400);
            return;
        }

        $success = SubAccountService::updatePermissions($userId, $id, $permissions);

        if (!$success) {
            $this->respondWithErrors(SubAccountService::getErrors(), 422);
        }

        $children = SubAccountService::getChildAccounts($userId);
        $this->respondWithData($children);
    }

    /**
     * DELETE /api/v2/users/me/sub-accounts/{id}
     * Revoke a relationship (either parent or child can do this)
     */
    public function revokeRelationship(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();

        SubAccountService::revokeRelationship($userId, $id);
        $this->respondWithData(['message' => 'Relationship revoked']);
    }

    /**
     * GET /api/v2/users/me/sub-accounts/{childId}/activity
     * Get activity dashboard for a child account
     */
    public function getChildActivity(int $childId): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('sub_account_activity', 10, 60);

        $activity = SubAccountService::getChildActivitySummary($userId, $childId);

        if ($activity === null) {
            $errors = SubAccountService::getErrors();
            $status = 403;
            if (!empty($errors) && $errors[0]['code'] === 'FORBIDDEN') {
                $status = 403;
            }
            $this->respondWithErrors($errors, $status);
        }

        $this->respondWithData($activity);
    }
}
