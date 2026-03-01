<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\TenantContext;
use Nexus\Services\CommunityFundService;
use Nexus\Services\TransactionCategoryService;
use Nexus\Services\ExchangeRatingService;
use Nexus\Services\CreditDonationService;
use Nexus\Services\StartingBalanceService;
use Nexus\Services\TransactionExportService;

/**
 * WalletFeaturesApiController
 *
 * API endpoints for wallet feature extensions:
 * - Community fund (W1)
 * - Transaction categories (W8)
 * - Exchange ratings (W10)
 * - Credit donations (W6)
 * - Starting balances (W7)
 * - Transaction statements (W5)
 *
 * Endpoints:
 * GET    /api/v2/wallet/community-fund              - Get community fund balance
 * GET    /api/v2/wallet/community-fund/transactions  - Get fund transaction history
 * POST   /api/v2/wallet/community-fund/deposit       - Admin deposit
 * POST   /api/v2/wallet/community-fund/withdraw      - Admin withdraw to member
 * POST   /api/v2/wallet/community-fund/donate         - Member donate to fund
 *
 * GET    /api/v2/wallet/categories                   - List transaction categories
 * POST   /api/v2/wallet/categories                   - Create category (admin)
 * PUT    /api/v2/wallet/categories/{id}              - Update category (admin)
 * DELETE /api/v2/wallet/categories/{id}              - Delete category (admin)
 *
 * POST   /api/v2/exchanges/{id}/rate                 - Rate an exchange
 * GET    /api/v2/exchanges/{id}/ratings              - Get exchange ratings
 * GET    /api/v2/users/{id}/rating                   - Get user average rating
 *
 * POST   /api/v2/wallet/donate                       - Donate credits
 * GET    /api/v2/wallet/donations                    - Get donation history
 *
 * GET    /api/v2/wallet/starting-balance             - Get starting balance config
 * PUT    /api/v2/wallet/starting-balance             - Set starting balance (admin)
 *
 * GET    /api/v2/wallet/statement                    - Export personal statement
 */
class WalletFeaturesApiController extends BaseApiController
{
    // ============================================
    // COMMUNITY FUND (W1)
    // ============================================

    /**
     * GET /api/v2/wallet/community-fund
     * Get community fund balance and stats
     */
    public function communityFundBalance(): void
    {
        $this->requireAuth();

        if (!TenantContext::hasFeature('wallet')) {
            $this->error('Wallet is not enabled', 400);
            return;
        }

        $data = CommunityFundService::getBalance();

        $this->jsonResponse(['data' => $data]);
    }

    /**
     * GET /api/v2/wallet/community-fund/transactions
     * Get community fund transaction history
     */
    public function communityFundTransactions(): void
    {
        $this->requireAuth();

        $limit = $this->queryInt('limit', 20, 1, 100);
        $offset = $this->queryInt('offset', 0, 0);

        $result = CommunityFundService::getTransactions($limit, $offset);

        $this->jsonResponse([
            'data' => $result['items'],
            'meta' => ['total' => $result['total']],
        ]);
    }

    /**
     * POST /api/v2/wallet/community-fund/deposit
     * Admin deposits credits into community fund
     * Body: { amount, description? }
     */
    public function communityFundDeposit(): void
    {
        $userId = $this->requireAuth();
        $this->requireAdmin();

        $data = $this->getAllInput();

        if (empty($data['amount']) || (float) $data['amount'] <= 0) {
            $this->error('Amount must be greater than 0', 400);
            return;
        }

        $result = CommunityFundService::adminDeposit(
            $userId,
            (float) $data['amount'],
            $data['description'] ?? ''
        );

        if (!$result['success']) {
            $this->error($result['error'], 400);
            return;
        }

        $this->jsonResponse([
            'data' => ['balance' => $result['balance']],
            'message' => 'Deposit successful',
        ]);
    }

    /**
     * POST /api/v2/wallet/community-fund/withdraw
     * Admin withdraws credits from fund and grants to member
     * Body: { recipient_id, amount, description? }
     */
    public function communityFundWithdraw(): void
    {
        $userId = $this->requireAuth();
        $this->requireAdmin();

        $data = $this->getAllInput();

        if (empty($data['recipient_id'])) {
            $this->error('recipient_id is required', 400);
            return;
        }

        if (empty($data['amount']) || (float) $data['amount'] <= 0) {
            $this->error('Amount must be greater than 0', 400);
            return;
        }

        $result = CommunityFundService::adminWithdraw(
            $userId,
            (int) $data['recipient_id'],
            (float) $data['amount'],
            $data['description'] ?? ''
        );

        if (!$result['success']) {
            $this->error($result['error'], 400);
            return;
        }

        $this->jsonResponse([
            'data' => ['balance' => $result['balance']],
            'message' => 'Withdrawal successful',
        ]);
    }

    /**
     * POST /api/v2/wallet/community-fund/donate
     * Member donates credits to community fund
     * Body: { amount, message? }
     */
    public function communityFundDonate(): void
    {
        $userId = $this->requireAuth();

        $data = $this->getAllInput();

        if (empty($data['amount']) || (float) $data['amount'] <= 0) {
            $this->error('Amount must be greater than 0', 400);
            return;
        }

        $result = CreditDonationService::donateToCommunityFund(
            $userId,
            (float) $data['amount'],
            $data['message'] ?? ''
        );

        if (!$result['success']) {
            $this->error($result['error'], 400);
            return;
        }

        $this->jsonResponse([
            'message' => 'Donation successful. Thank you!',
        ]);
    }

    // ============================================
    // TRANSACTION CATEGORIES (W8)
    // ============================================

    /**
     * GET /api/v2/wallet/categories
     * List all active transaction categories
     */
    public function listCategories(): void
    {
        $this->requireAuth();

        $categories = TransactionCategoryService::getAll();

        $this->jsonResponse(['data' => $categories]);
    }

    /**
     * POST /api/v2/wallet/categories
     * Create a new transaction category (admin only)
     * Body: { name, description?, icon?, color?, sort_order? }
     */
    public function createCategory(): void
    {
        $this->requireAuth();
        $this->requireAdmin();

        $data = $this->getAllInput();

        if (empty($data['name'])) {
            $this->error('Name is required', 400);
            return;
        }

        $id = TransactionCategoryService::create($data);

        if (!$id) {
            $this->error('Failed to create category', 500);
            return;
        }

        $category = TransactionCategoryService::getById($id);

        $this->jsonResponse(['data' => $category], 201);
    }

    /**
     * PUT /api/v2/wallet/categories/{id}
     * Update a transaction category (admin only)
     */
    public function updateCategory(int $id): void
    {
        $this->requireAuth();
        $this->requireAdmin();

        $data = $this->getAllInput();

        $success = TransactionCategoryService::update($id, $data);

        if (!$success) {
            $this->error('Failed to update category', 400);
            return;
        }

        $category = TransactionCategoryService::getById($id);

        $this->jsonResponse(['data' => $category]);
    }

    /**
     * DELETE /api/v2/wallet/categories/{id}
     * Delete a transaction category (admin only, non-system only)
     */
    public function deleteCategory(int $id): void
    {
        $this->requireAuth();
        $this->requireAdmin();

        $success = TransactionCategoryService::delete($id);

        if (!$success) {
            $this->error('Cannot delete this category (may be a system category)', 400);
            return;
        }

        $this->jsonResponse(['message' => 'Category deleted']);
    }

    // ============================================
    // EXCHANGE RATINGS (W10)
    // ============================================

    /**
     * POST /api/v2/exchanges/{id}/rate
     * Submit a rating for a completed exchange
     * Body: { rating (1-5), comment? }
     */
    public function rateExchange(int $exchangeId): void
    {
        $userId = $this->requireAuth();

        $data = $this->getAllInput();

        if (empty($data['rating'])) {
            $this->error('Rating is required (1-5)', 400);
            return;
        }

        $result = ExchangeRatingService::submitRating(
            $exchangeId,
            $userId,
            (int) $data['rating'],
            $data['comment'] ?? null
        );

        if (!$result['success']) {
            $this->error($result['error'], 400);
            return;
        }

        $ratings = ExchangeRatingService::getRatingsForExchange($exchangeId);

        $this->jsonResponse([
            'data' => $ratings,
            'message' => 'Rating submitted',
        ], 201);
    }

    /**
     * GET /api/v2/exchanges/{id}/ratings
     * Get ratings for an exchange
     */
    public function exchangeRatings(int $exchangeId): void
    {
        $this->requireAuth();

        $ratings = ExchangeRatingService::getRatingsForExchange($exchangeId);
        $hasRated = ExchangeRatingService::hasRated($exchangeId, $this->getUserId());

        $this->jsonResponse([
            'data' => [
                'ratings' => $ratings,
                'has_rated' => $hasRated,
            ],
        ]);
    }

    /**
     * GET /api/v2/users/{id}/rating
     * Get average rating for a user
     */
    public function userRating(int $userId): void
    {
        $this->requireAuth();

        $rating = ExchangeRatingService::getUserRating($userId);

        $this->jsonResponse(['data' => $rating]);
    }

    // ============================================
    // CREDIT DONATIONS (W6)
    // ============================================

    /**
     * POST /api/v2/wallet/donate
     * Donate credits to another member or community fund
     * Body: { recipient_type ('user' | 'community_fund'), recipient_id? (required if type=user), amount, message? }
     */
    public function donate(): void
    {
        $userId = $this->requireAuth();

        $data = $this->getAllInput();

        if (empty($data['amount']) || (float) $data['amount'] <= 0) {
            $this->error('Amount must be greater than 0', 400);
            return;
        }

        $recipientType = $data['recipient_type'] ?? 'community_fund';

        if ($recipientType === 'user') {
            if (empty($data['recipient_id'])) {
                $this->error('recipient_id is required when donating to a user', 400);
                return;
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
            $this->error($result['error'], 400);
            return;
        }

        $this->jsonResponse([
            'message' => 'Donation successful. Thank you!',
        ], 201);
    }

    /**
     * GET /api/v2/wallet/donations
     * Get donation history for the current user
     */
    public function donationHistory(): void
    {
        $userId = $this->requireAuth();

        $limit = $this->queryInt('limit', 20, 1, 100);
        $offset = $this->queryInt('offset', 0, 0);

        $result = CreditDonationService::getDonationHistory($userId, $limit, $offset);

        $this->jsonResponse([
            'data' => $result['items'],
            'meta' => ['total' => $result['total']],
        ]);
    }

    // ============================================
    // STARTING BALANCES (W7)
    // ============================================

    /**
     * GET /api/v2/wallet/starting-balance
     * Get starting balance configuration
     */
    public function getStartingBalance(): void
    {
        $this->requireAuth();

        $amount = StartingBalanceService::getStartingBalance();

        $this->jsonResponse([
            'data' => ['starting_balance' => $amount],
        ]);
    }

    /**
     * PUT /api/v2/wallet/starting-balance
     * Set starting balance (admin only)
     * Body: { amount }
     */
    public function setStartingBalance(): void
    {
        $this->requireAuth();
        $this->requireAdmin();

        $data = $this->getAllInput();

        if (!isset($data['amount'])) {
            $this->error('Amount is required', 400);
            return;
        }

        $amount = max(0, (float) $data['amount']);
        $success = StartingBalanceService::setStartingBalance($amount);

        if (!$success) {
            $this->error('Failed to update starting balance', 500);
            return;
        }

        $this->jsonResponse([
            'data' => ['starting_balance' => $amount],
            'message' => 'Starting balance updated',
        ]);
    }

    // ============================================
    // TRANSACTION STATEMENTS (W5)
    // ============================================

    /**
     * GET /api/v2/wallet/statement
     * Export personal transaction statement as CSV
     * Query params: start_date, end_date, type ('all', 'sent', 'received')
     */
    public function statement(): void
    {
        $userId = $this->requireAuth();
        $this->rateLimit('wallet_statement', 10, 60);

        $filters = [
            'startDate' => $this->query('start_date'),
            'endDate' => $this->query('end_date'),
            'type' => $this->query('type'),
        ];

        // Remove null filters
        $filters = array_filter($filters, function ($v) {
            return $v !== null && $v !== '';
        });

        $result = TransactionExportService::exportPersonalStatementCSV($userId, $filters);

        if (!$result['success']) {
            $this->error($result['message'] ?? 'Export failed', 400);
            return;
        }

        TransactionExportService::sendCSVDownload($result['csv'], $result['filename']);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Require admin role
     */
    private function requireAdmin(): void
    {
        $userId = $this->getUserId();

        try {
            if (!TenantContext::isTokenUserSuperAdmin() && !TenantContext::isTokenUserAdmin()) {
                $this->error('Admin access required', 403);
            }
        } catch (\Exception $e) {
            // Fallback: check user role directly
            $stmt = \Nexus\Core\Database::query(
                "SELECT role FROM users WHERE id = ? AND tenant_id = ?",
                [$userId, TenantContext::getId()]
            );
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user || !in_array($user['role'], ['admin', 'super_admin', 'tenant_admin', 'tenant_super_admin'], true)) {
                $this->error('Admin access required', 403);
            }
        }
    }
}
