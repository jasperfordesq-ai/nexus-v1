<?php
/**
 * Pending Reviews List
 * Shows transactions that the user hasn't reviewed yet
 */

$pendingReviews = $pendingReviews ?? [];
?>

<div class="federation-pending-reviews">
    <div class="container py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">Pending Reviews</h1>
                <p class="text-muted mb-0">Leave feedback for your federated exchanges</p>
            </div>
            <a href="<?= $basePath ?>/federation/transactions" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>

        <?php if (empty($pendingReviews)): ?>
            <!-- Empty State -->
            <div class="empty-state text-center py-5">
                <div class="empty-icon mb-4">
                    <i class="fas fa-check-circle text-success" style="font-size: 4rem; opacity: 0.5;"></i>
                </div>
                <h4>All caught up!</h4>
                <p class="text-muted mb-4">You've reviewed all your completed federated exchanges.</p>
                <a href="<?= $basePath ?>/federation/transactions" class="btn btn-primary">
                    View Transactions
                </a>
            </div>
        <?php else: ?>
            <!-- Pending Reviews List -->
            <div class="pending-list">
                <?php foreach ($pendingReviews as $review): ?>
                    <?php
                    $otherPartyName = htmlspecialchars($review['other_party_name'] ?? 'Member');
                    $timebank = htmlspecialchars($review['other_party_timebank'] ?? 'Unknown Timebank');
                    $amount = number_format((float)($review['amount'] ?? 0), 2);
                    $description = htmlspecialchars($review['description'] ?? '');
                    $completedAt = $review['completed_at'] ?? null;
                    $direction = $review['direction'] ?? 'sent';
                    $transactionId = $review['id'] ?? 0;
                    ?>
                    <div class="pending-card card mb-3">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <div class="avatar-circle bg-<?= $direction === 'sent' ? 'danger' : 'success' ?> text-white">
                                        <i class="fas fa-<?= $direction === 'sent' ? 'arrow-up' : 'arrow-down' ?>"></i>
                                    </div>
                                </div>
                                <div class="col">
                                    <h5 class="mb-1"><?= $otherPartyName ?></h5>
                                    <p class="text-muted mb-0 small">
                                        <i class="fas fa-building me-1"></i><?= $timebank ?>
                                    </p>
                                </div>
                                <div class="col-auto text-end">
                                    <div class="fw-bold text-<?= $direction === 'sent' ? 'danger' : 'success' ?>">
                                        <?= $direction === 'sent' ? '-' : '+' ?><?= $amount ?> hrs
                                    </div>
                                    <?php if ($completedAt): ?>
                                        <div class="text-muted small">
                                            <?= date('j M Y', strtotime($completedAt)) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12 col-md-auto mt-3 mt-md-0">
                                    <a href="<?= $basePath ?>/federation/review/<?= $transactionId ?>"
                                       class="btn btn-primary w-100">
                                        <i class="fas fa-star me-2"></i>Leave Review
                                    </a>
                                </div>
                            </div>
                            <?php if ($description): ?>
                                <div class="mt-2 pt-2 border-top">
                                    <small class="text-muted"><?= $description ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Info Box -->
            <div class="alert alert-info mt-4">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Why leave reviews?</strong>
                Reviews help build trust across timebanks. Your feedback helps other members make informed decisions
                and rewards great community members with recognition.
            </div>
        <?php endif; ?>
    </div>
</div>

