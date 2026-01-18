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

<style>
.federation-review-error {
    min-height: calc(100vh - 200px);
    display: flex;
    align-items: center;
    background: var(--bg-secondary, #f8f9fa);
}

.error-card {
    background: white;
    padding: 3rem;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

[data-theme="dark"] .federation-review-error {
    background: var(--bg-primary, #1a1a1a);
}

[data-theme="dark"] .error-card {
    background: var(--bg-secondary, #2d2d2d);
}
</style>
