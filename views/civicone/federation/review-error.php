<?php
/**
 * Review Error Page
 * Shown when a user cannot leave a review for a transaction
 */

$error = $error ?? 'Unable to submit review';
?>

<div class="federation-review-error">
    <div class="container py-5">
        <div class="error-card mx-auto text-center" style="max-width: 500px;">
            <div class="error-icon mb-4">
                <i class="fas fa-exclamation-circle text-warning" style="font-size: 4rem;"></i>
            </div>
            <h2 class="mb-3">Cannot Submit Review</h2>
            <p class="text-muted mb-4"><?= htmlspecialchars($error) ?></p>

            <div class="d-grid gap-2">
                <a href="<?= $basePath ?>/federation/transactions" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Transactions
                </a>
                <a href="<?= $basePath ?>/federation/reviews/pending" class="btn btn-outline-secondary">
                    View Pending Reviews
                </a>
            </div>
        </div>
    </div>
</div>
