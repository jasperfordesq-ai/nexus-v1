<?php

namespace Nexus\Controllers;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\ReviewService;
use Nexus\Services\FederationGateway;
use Nexus\Models\User;

/**
 * FederationReviewController
 *
 * Handles reviews for federated (cross-tenant) transactions.
 * Provides UI for leaving reviews, viewing pending reviews,
 * and managing review visibility.
 */
class FederationReviewController
{
    /**
     * Show the review form for a federation transaction
     */
    public function show($transactionId)
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];

        // Check if user can review this transaction
        $canReview = ReviewService::canReviewTransaction($userId, (int)$transactionId);

        if (!$canReview['can_review']) {
            View::render('federation/review-error', [
                'pageTitle' => 'Cannot Review',
                'error' => $canReview['reason']
            ]);
            return;
        }

        // Get the other party's details
        $receiverId = $canReview['receiver_id'];
        $receiver = User::findById($receiverId);
        $transaction = $canReview['transaction'];

        // Get receiver's timebank name
        $tenantResult = Database::query(
            "SELECT name FROM tenants WHERE id = ?",
            [$receiver['tenant_id'] ?? 0]
        )->fetch(\PDO::FETCH_ASSOC);

        View::render('federation/review-form', [
            'pageTitle' => 'Leave a Review',
            'transaction' => $transaction,
            'receiver' => $receiver,
            'receiverTimebank' => $tenantResult['name'] ?? 'Unknown Timebank',
            'transactionId' => $transactionId
        ]);
    }

    /**
     * Store a new review
     */
    public function store($transactionId)
    {
        if (!isset($_SESSION['user_id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
            return;
        }

        // Verify CSRF token
        if (!$this->isAjax()) {
            \Nexus\Core\Csrf::verifyOrDie();
        }

        $userId = $_SESSION['user_id'];
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        // Use ReviewService to create the review
        $canReview = ReviewService::canReviewTransaction($userId, (int)$transactionId);

        if (!$canReview['can_review']) {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'error' => $canReview['reason']]);
            } else {
                $_SESSION['flash_error'] = $canReview['reason'];
                header('Location: ' . TenantContext::getBasePath() . '/federation/transactions');
                exit;
            }
            return;
        }

        $result = ReviewService::createReview(
            $userId,
            $canReview['receiver_id'],
            $rating,
            $comment ?: null,
            null,
            (int)$transactionId
        );

        if ($result['success']) {
            // Send notification to the reviewed user
            $this->sendReviewNotification($canReview['receiver_id'], $userId, $rating, $comment);

            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Review submitted successfully',
                    'review_id' => $result['review_id']
                ]);
            } else {
                $_SESSION['flash_success'] = 'Your review has been submitted. Thank you for your feedback!';
                header('Location: ' . TenantContext::getBasePath() . '/federation/transactions?success=review_posted');
                exit;
            }
        } else {
            if ($this->isAjax()) {
                $this->jsonResponse(['success' => false, 'error' => $result['error']]);
            } else {
                $_SESSION['flash_error'] = $result['error'];
                header('Location: ' . TenantContext::getBasePath() . '/federation/review/' . $transactionId);
                exit;
            }
        }
    }

    /**
     * Return review form as modal content (AJAX)
     */
    public function modal($transactionId)
    {
        if (!isset($_SESSION['user_id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
            return;
        }

        $userId = $_SESSION['user_id'];
        $canReview = ReviewService::canReviewTransaction($userId, (int)$transactionId);

        if (!$canReview['can_review']) {
            $this->jsonResponse(['success' => false, 'error' => $canReview['reason']]);
            return;
        }

        $receiverId = $canReview['receiver_id'];
        $receiver = User::findById($receiverId);
        $transaction = $canReview['transaction'];

        // Get receiver's timebank name
        $tenantResult = Database::query(
            "SELECT name FROM tenants WHERE id = ?",
            [$receiver['tenant_id'] ?? 0]
        )->fetch(\PDO::FETCH_ASSOC);

        // Generate modal HTML
        $html = $this->renderModalHtml($transaction, $receiver, $tenantResult['name'] ?? 'Unknown', $transactionId);

        $this->jsonResponse([
            'success' => true,
            'html' => $html
        ]);
    }

    /**
     * List pending reviews for current user
     */
    public function pending()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . TenantContext::getBasePath() . '/login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $pendingReviews = ReviewService::getPendingReviews($userId);

        View::render('federation/reviews-pending', [
            'pageTitle' => 'Pending Reviews',
            'pendingReviews' => $pendingReviews
        ]);
    }

    /**
     * Get reviews for a specific user (JSON response)
     */
    public function userReviews($userId)
    {
        $viewerTenantId = null;
        if (isset($_SESSION['user_id'])) {
            $viewer = Database::query(
                "SELECT tenant_id FROM users WHERE id = ?",
                [$_SESSION['user_id']]
            )->fetch(\PDO::FETCH_ASSOC);
            $viewerTenantId = $viewer['tenant_id'] ?? null;
        }

        $limit = (int)($_GET['limit'] ?? 10);
        $offset = (int)($_GET['offset'] ?? 0);

        $result = ReviewService::getReviewsForUser((int)$userId, $viewerTenantId, $limit, $offset);

        $this->jsonResponse([
            'success' => true,
            'reviews' => $result['reviews'],
            'stats' => $result['stats']
        ]);
    }

    /**
     * Send notification to the reviewed user
     */
    private function sendReviewNotification(int $receiverId, int $reviewerId, int $rating, ?string $comment): void
    {
        try {
            $reviewer = User::findById($reviewerId);
            $reviewerName = $reviewer['first_name'] ?? $reviewer['name'] ?? 'Someone';

            $starText = $rating === 1 ? 'star' : 'stars';
            $content = "You received a {$rating}-{$starText} review from {$reviewerName} for a federated exchange.";

            $html = "<h2>New Review Received</h2>";
            $html .= "<p><strong>Rating:</strong> " . str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) . " ({$rating}/5)</p>";
            if ($comment) {
                $html .= "<p><strong>Comment:</strong> \"" . htmlspecialchars($comment) . "\"</p>";
            }
            $html .= "<p><strong>From:</strong> {$reviewerName} (via Federation)</p>";

            \Nexus\Services\NotificationDispatcher::dispatch(
                $receiverId,
                'federation',
                0,
                'federation_review',
                $content,
                TenantContext::getBasePath() . '/federation/member/' . $reviewerId,
                $html
            );
        } catch (\Throwable $e) {
            error_log("FederationReviewController: Failed to send review notification: " . $e->getMessage());
        }
    }

    /**
     * Render modal HTML for review form
     */
    private function renderModalHtml(array $transaction, array $receiver, string $timebankName, int $transactionId): string
    {
        $receiverName = htmlspecialchars($receiver['first_name'] ?? $receiver['name'] ?? 'Member');
        $amount = number_format((float)($transaction['amount'] ?? 0), 2);
        $description = htmlspecialchars($transaction['description'] ?? 'Time exchange');
        $csrfToken = \Nexus\Core\Csrf::token();
        $basePath = TenantContext::getBasePath();

        return <<<HTML
<div class="review-modal-content">
    <div class="review-header">
        <h3>Leave a Review for {$receiverName}</h3>
        <p class="text-muted">{$timebankName}</p>
    </div>

    <div class="transaction-summary">
        <div class="amount">{$amount} hours</div>
        <div class="description">{$description}</div>
    </div>

    <form id="review-form" method="POST" action="{$basePath}/federation/review/{$transactionId}">
        <input type="hidden" name="_csrf_token" value="{$csrfToken}">

        <div class="form-group">
            <label>Rating</label>
            <div class="star-rating" id="star-rating">
                <button type="button" class="star" data-rating="1">★</button>
                <button type="button" class="star" data-rating="2">★</button>
                <button type="button" class="star" data-rating="3">★</button>
                <button type="button" class="star" data-rating="4">★</button>
                <button type="button" class="star" data-rating="5">★</button>
            </div>
            <input type="hidden" name="rating" id="rating-input" value="0" required>
        </div>

        <div class="form-group">
            <label for="comment">Comment (optional)</label>
            <textarea name="comment" id="comment" class="form-control" rows="4"
                placeholder="Share your experience with this exchange..."></textarea>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="submit-review" disabled>Submit Review</button>
        </div>
    </form>
</div>

<style>
.review-modal-content { padding: 1rem; }
.review-header { margin-bottom: 1rem; text-align: center; }
.review-header h3 { margin: 0 0 0.25rem; }
.transaction-summary {
    background: var(--bg-secondary, #f5f5f5);
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    text-align: center;
}
.transaction-summary .amount { font-size: 1.5rem; font-weight: 600; color: var(--primary, #4CAF50); }
.transaction-summary .description { color: var(--text-muted, #666); margin-top: 0.25rem; }
.star-rating { display: flex; gap: 0.5rem; justify-content: center; margin: 0.5rem 0; }
.star-rating .star {
    font-size: 2rem;
    background: none;
    border: none;
    color: #ddd;
    cursor: pointer;
    transition: color 0.2s, transform 0.2s;
    padding: 0;
}
.star-rating .star:hover,
.star-rating .star.active { color: #ffc107; transform: scale(1.1); }
.star-rating .star.hovered { color: #ffc107; }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
.form-actions { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; }
</style>

<script>
(function() {
    const stars = document.querySelectorAll('#star-rating .star');
    const ratingInput = document.getElementById('rating-input');
    const submitBtn = document.getElementById('submit-review');

    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = this.dataset.rating;
            ratingInput.value = rating;
            submitBtn.disabled = false;

            stars.forEach(s => {
                s.classList.toggle('active', s.dataset.rating <= rating);
            });
        });

        star.addEventListener('mouseenter', function() {
            const rating = this.dataset.rating;
            stars.forEach(s => {
                s.classList.toggle('hovered', s.dataset.rating <= rating);
            });
        });

        star.addEventListener('mouseleave', function() {
            stars.forEach(s => s.classList.remove('hovered'));
        });
    });
})();
</script>
HTML;
    }

    /**
     * Check if request is AJAX
     */
    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Send JSON response
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
