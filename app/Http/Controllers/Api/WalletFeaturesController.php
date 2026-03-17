<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Nexus\Core\TenantContext;
use Nexus\Services\CommunityFundService;
use Nexus\Services\TransactionCategoryService;
use Nexus\Services\ExchangeRatingService;
use Nexus\Services\CreditDonationService;
use Nexus\Services\StartingBalanceService;
use Nexus\Services\TransactionExportService;

/**
 * WalletFeaturesController -- Extended wallet: community fund, categories,
 * ratings, donations, statements.
 *
 * Fully migrated from ob_start() delegation to direct service calls.
 */
class WalletFeaturesController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    // ============================================
    // COMMUNITY FUND (W1)
    // ============================================

    /** GET /api/v2/wallet/community-fund/balance */
    public function communityFundBalance(): JsonResponse
    {
        $this->requireAuth();

        if (!TenantContext::hasFeature('wallet')) {
            return $this->respondWithData(['balance' => 0, 'enabled' => false]);
        }

        $data = CommunityFundService::getBalance();

        return $this->respondWithData($data);
    }

    /** GET /api/v2/wallet/community-fund/transactions */
    public function communityFundTransactions(): JsonResponse
    {
        $this->requireAuth();

        if (!TenantContext::hasFeature('wallet')) {
            return $this->respondWithData(['balance' => 0, 'enabled' => false]);
        }

        $limit = $this->queryInt('limit', 20, 1, 100);
        $offset = $this->queryInt('offset', 0, 0);

        $result = CommunityFundService::getTransactions($limit, $offset);

        return $this->respondWithData($result['items'], ['total' => $result['total']]);
    }

    /** POST /api/v2/wallet/community-fund/deposit */
    public function communityFundDeposit(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->requireAdmin();

        if (!TenantContext::hasFeature('wallet')) {
            return $this->respondWithData(['balance' => 0, 'enabled' => false]);
        }

        $data = $this->getAllInput();

        if (empty($data['amount']) || (float) $data['amount'] <= 0) {
            return $this->error('Amount must be greater than 0', 400);
        }

        $result = CommunityFundService::adminDeposit(
            $userId,
            (float) $data['amount'],
            $data['description'] ?? ''
        );

        if (!$result['success']) {
            return $this->error($result['error'], 400);
        }

        return $this->respondWithData(
            ['balance' => $result['balance']],
            null,
            200
        );
    }

    /** POST /api/v2/wallet/community-fund/withdraw */
    public function communityFundWithdraw(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->requireAdmin();

        if (!TenantContext::hasFeature('wallet')) {
            return $this->respondWithData(['balance' => 0, 'enabled' => false]);
        }

        $data = $this->getAllInput();

        if (empty($data['recipient_id'])) {
            return $this->error('recipient_id is required', 400);
        }

        if (empty($data['amount']) || (float) $data['amount'] <= 0) {
            return $this->error('Amount must be greater than 0', 400);
        }

        $result = CommunityFundService::adminWithdraw(
            $userId,
            (int) $data['recipient_id'],
            (float) $data['amount'],
            $data['description'] ?? ''
        );

        if (!$result['success']) {
            return $this->error($result['error'], 400);
        }

        return $this->respondWithData(['balance' => $result['balance']]);
    }

    /** POST /api/v2/wallet/community-fund/donate */
    public function communityFundDonate(): JsonResponse
    {
        $userId = $this->requireAuth();

        if (!TenantContext::hasFeature('wallet')) {
            return $this->respondWithData(['balance' => 0, 'enabled' => false]);
        }

        $data = $this->getAllInput();

        if (empty($data['amount']) || (float) $data['amount'] <= 0) {
            return $this->error('Amount must be greater than 0', 400);
        }

        $result = CreditDonationService::donateToCommunityFund(
            $userId,
            (float) $data['amount'],
            $data['message'] ?? ''
        );

        if (!$result['success']) {
            return $this->error($result['error'], 400);
        }

        return $this->respondWithData(['message' => 'Donation successful. Thank you!']);
    }

    // ============================================
    // TRANSACTION CATEGORIES (W8)
    // ============================================

    /** GET /api/v2/wallet/categories */
    public function listCategories(): JsonResponse
    {
        $this->requireAuth();

        if (!TenantContext::hasFeature('wallet')) {
            return $this->respondWithData(['balance' => 0, 'enabled' => false]);
        }

        $categories = TransactionCategoryService::getAll();

        return $this->respondWithData($categories);
    }

    /** POST /api/v2/wallet/categories */
    public function createCategory(): JsonResponse
    {
        $this->requireAuth();
        $this->requireAdmin();

        $data = $this->getAllInput();

        if (empty($data['name'])) {
            return $this->error('Name is required', 400);
        }

        $id = TransactionCategoryService::create($data);

        if (!$id) {
            return $this->error('Failed to create category', 500);
        }

        $category = TransactionCategoryService::getById($id);

        return $this->respondWithData($category, null, 201);
    }

    /** PUT /api/v2/wallet/categories/{id} */
    public function updateCategory(int $id): JsonResponse
    {
        $this->requireAuth();
        $this->requireAdmin();

        $data = $this->getAllInput();

        $success = TransactionCategoryService::update($id, $data);

        if (!$success) {
            return $this->error('Failed to update category', 400);
        }

        $category = TransactionCategoryService::getById($id);

        return $this->respondWithData($category);
    }

    /** DELETE /api/v2/wallet/categories/{id} */
    public function deleteCategory(int $id): JsonResponse
    {
        $this->requireAuth();
        $this->requireAdmin();

        $success = TransactionCategoryService::delete($id);

        if (!$success) {
            return $this->error('Cannot delete this category (may be a system category)', 400);
        }

        return $this->respondWithData(['message' => 'Category deleted']);
    }

    // ============================================
    // EXCHANGE RATINGS (W10)
    // ============================================

    /** POST /api/v2/wallet/exchanges/{eid}/rate */
    public function rateExchange(int $eid): JsonResponse
    {
        $userId = $this->requireAuth();

        $data = $this->getAllInput();

        if (empty($data['rating'])) {
            return $this->error('Rating is required (1-5)', 400);
        }

        $result = ExchangeRatingService::submitRating(
            $eid,
            $userId,
            (int) $data['rating'],
            $data['comment'] ?? null
        );

        if (!$result['success']) {
            return $this->error($result['error'], 400);
        }

        $ratings = ExchangeRatingService::getRatingsForExchange($eid);

        return $this->respondWithData($ratings, null, 201);
    }

    /** GET /api/v2/wallet/exchanges/{eid}/ratings */
    public function exchangeRatings(int $eid): JsonResponse
    {
        $this->requireAuth();

        $ratings = ExchangeRatingService::getRatingsForExchange($eid);
        $hasRated = ExchangeRatingService::hasRated($eid, $this->getUserId());

        return $this->respondWithData([
            'ratings' => $ratings,
            'has_rated' => $hasRated,
        ]);
    }

    /** GET /api/v2/wallet/users/{userId}/rating */
    public function userRating(int $userId): JsonResponse
    {
        $this->requireAuth();

        $rating = ExchangeRatingService::getUserRating($userId);

        return $this->respondWithData($rating);
    }

    // ============================================
    // CREDIT DONATIONS (W6)
    // ============================================

    /** POST /api/v2/wallet/donate */
    public function donate(): JsonResponse
    {
        $userId = $this->requireAuth();

        $data = $this->getAllInput();

        if (empty($data['amount']) || (float) $data['amount'] <= 0) {
            return $this->error('Amount must be greater than 0', 400);
        }

        $recipientType = $data['recipient_type'] ?? 'community_fund';

        if ($recipientType === 'user') {
            if (empty($data['recipient_id'])) {
                return $this->error('recipient_id is required when donating to a user', 400);
            }

            $result = CreditDonationService::donateToMember(
                $userId,
                (int) $data['recipient_id'],
                (float) $data['amount'],
                $data['message'] ?? ''
            );
        } else {
            $result = CreditDonationService::donateToCommunityFund(
                $userId,
                (float) $data['amount'],
                $data['message'] ?? ''
            );
        }

        if (!$result['success']) {
            return $this->error($result['error'], 400);
        }

        return $this->respondWithData(['message' => 'Donation successful. Thank you!'], null, 201);
    }

    /** GET /api/v2/wallet/donation-history */
    public function donationHistory(): JsonResponse
    {
        $userId = $this->requireAuth();

        $limit = $this->queryInt('limit', 20, 1, 100);
        $offset = $this->queryInt('offset', 0, 0);

        $result = CreditDonationService::getDonationHistory($userId, $limit, $offset);

        return $this->respondWithData($result['items'], ['total' => $result['total']]);
    }

    // ============================================
    // STARTING BALANCES (W7)
    // ============================================

    /** GET /api/v2/wallet/starting-balance */
    public function getStartingBalance(): JsonResponse
    {
        $this->requireAuth();

        $amount = StartingBalanceService::getStartingBalance();

        return $this->respondWithData(['starting_balance' => $amount]);
    }

    /** POST /api/v2/wallet/starting-balance */
    public function setStartingBalance(): JsonResponse
    {
        $this->requireAuth();
        $this->requireAdmin();

        $data = $this->getAllInput();

        if (!isset($data['amount'])) {
            return $this->error('Amount is required', 400);
        }

        $amount = max(0, (float) $data['amount']);

        try {
            StartingBalanceService::setStartingBalance($amount);
        } catch (\Exception $e) {
            return $this->error('Failed to update starting balance', 500);
        }

        return $this->respondWithData([
            'starting_balance' => $amount,
            'message' => 'Starting balance updated',
        ]);
    }

    // ============================================
    // TRANSACTION STATEMENTS (W5)
    // ============================================

    /** GET /api/v2/wallet/statement */
    public function statement(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('wallet_statement', 10, 60);

        $filters = [
            'startDate' => $this->query('start_date'),
            'endDate' => $this->query('end_date'),
            'type' => $this->query('type'),
        ];

        $filters = array_filter($filters, function ($v) {
            return $v !== null && $v !== '';
        });

        $result = TransactionExportService::exportPersonalStatementCSV($userId, $filters);

        if (!$result['success']) {
            return $this->error($result['message'] ?? 'Export failed', 400);
        }

        // Send CSV download directly (bypasses JSON response)
        TransactionExportService::sendCSVDownload($result['csv'], $result['filename']);

        // This line won't be reached in production (sendCSVDownload exits),
        // but provides a fallback for testing environments
        return $this->respondWithData(['message' => 'Statement exported']);
    }
}
