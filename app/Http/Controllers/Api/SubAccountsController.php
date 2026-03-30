<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\SubAccountService;
use Illuminate\Http\JsonResponse;

/**
 * SubAccountsController — Family/child sub-account management.
 */
class SubAccountsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly SubAccountService $subAccountService,
    ) {}

    /**
     * GET /api/v2/sub-accounts
     *
     * Get child/sub-accounts for the authenticated parent user.
     */
    public function getChildren(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $children = $this->subAccountService->getChildren($userId, $tenantId);

        return $this->respondWithData($children);
    }

    /**
     * POST /api/v2/sub-accounts
     *
     * Request creation of a sub-account (child/dependent).
     * Body: name, date_of_birth, relationship (child|dependent).
     */
    public function requestSubAccount(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $this->rateLimit('sub_account_request', 3, 300);

        $data = $this->getAllInput();

        $result = $this->subAccountService->requestSubAccount($userId, $tenantId, $data);

        if (isset($result['error'])) {
            return $this->respondWithError('SUB_ACCOUNT_FAILED', $result['error'], null, 422);
        }

        return $this->respondWithData($result, null, 201);
    }

    /**
     * POST /api/v2/sub-accounts/{id}/approve
     *
     * Approve a pending sub-account request (admin only).
     */
    public function approve(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $result = $this->subAccountService->approve($id, $tenantId);

        if ($result === null) {
            return $this->respondWithError('NOT_FOUND', __('api.sub_account_request_not_found'), null, 404);
        }

        return $this->respondWithData($result);
    }
}
