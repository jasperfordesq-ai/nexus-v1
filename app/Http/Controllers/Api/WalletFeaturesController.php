<?php
// Copyright © 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * WalletFeaturesController -- Extended wallet: community fund, categories, ratings, donations, statements.
 *
 * Delegates to legacy controller during migration.
 */
class WalletFeaturesController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

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

    /** GET /api/v2/wallet/community-fund/balance */
    public function communityFundBalance(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletFeaturesApiController::class, 'communityFundBalance');
    }

    /** GET /api/v2/wallet/community-fund/transactions */
    public function communityFundTransactions(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletFeaturesApiController::class, 'communityFundTransactions');
    }

    /** POST /api/v2/wallet/community-fund/deposit */
    public function communityFundDeposit(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletFeaturesApiController::class, 'communityFundDeposit');
    }

    /** POST /api/v2/wallet/community-fund/withdraw */
    public function communityFundWithdraw(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletFeaturesApiController::class, 'communityFundWithdraw');
    }

    /** POST /api/v2/wallet/community-fund/donate */
    public function communityFundDonate(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletFeaturesApiController::class, 'communityFundDonate');
    }

    /** GET /api/v2/wallet/categories */
    public function listCategories(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletFeaturesApiController::class, 'listCategories');
    }

    /** POST /api/v2/wallet/categories */
    public function createCategory(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletFeaturesApiController::class, 'createCategory');
    }

    /** PUT /api/v2/wallet/categories/{id} */
    public function updateCategory(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletFeaturesApiController::class, 'updateCategory', [$id]);
    }

    /** DELETE /api/v2/wallet/categories/{id} */
    public function deleteCategory(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletFeaturesApiController::class, 'deleteCategory', [$id]);
    }

    /** POST /api/v2/wallet/exchanges/{eid}/rate */
    public function rateExchange(int $eid): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletFeaturesApiController::class, 'rateExchange', [$eid]);
    }

    /** GET /api/v2/wallet/exchanges/{eid}/ratings */
    public function exchangeRatings(int $eid): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletFeaturesApiController::class, 'exchangeRatings', [$eid]);
    }

    /** GET /api/v2/wallet/users/{userId}/rating */
    public function userRating(int $userId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletFeaturesApiController::class, 'userRating', [$userId]);
    }

    /** POST /api/v2/wallet/donate */
    public function donate(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletFeaturesApiController::class, 'donate');
    }

    /** GET /api/v2/wallet/donation-history */
    public function donationHistory(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletFeaturesApiController::class, 'donationHistory');
    }

    /** GET /api/v2/wallet/starting-balance */
    public function getStartingBalance(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletFeaturesApiController::class, 'getStartingBalance');
    }

    /** POST /api/v2/wallet/starting-balance */
    public function setStartingBalance(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletFeaturesApiController::class, 'setStartingBalance');
    }

    /** GET /api/v2/wallet/statement */
    public function statement(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WalletFeaturesApiController::class, 'statement');
    }
}
