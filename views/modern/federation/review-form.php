<?php
/**
 * Federation Review Form
 * Allows users to leave reviews after completing a federated transaction
 */

$receiver = $receiver ?? [];
$transaction = $transaction ?? [];
$receiverTimebank = $receiverTimebank ?? 'Unknown Timebank';
$transactionId = $transactionId ?? 0;

$receiverName = htmlspecialchars($receiver['first_name'] ?? $receiver['name'] ?? 'Member');
$receiverAvatar = $receiver['avatar_url'] ?? null;
$amount = number_format((float)($transaction['amount'] ?? 0), 2);
$description = htmlspecialchars($transaction['description'] ?? 'Time exchange');
$completedAt = $transaction['completed_at'] ?? $transaction['created_at'] ?? null;
?>

<div class="federation-review-page">
    <div class="container py-4">
        <!-- Back Button -->
        <div class="mb-4">
            <a href="<?= $basePath ?>/federation/transactions" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Transactions
            </a>
        </div>

        <!-- Review Card -->
        <div class="card review-card mx-auto" style="max-width: 600px;">
            <div class="card-header text-center">
                <h2 class="mb-0">Leave a Review</h2>
            </div>

            <div class="card-body">
                <!-- Receiver Info -->
                <div class="receiver-info text-center mb-4">
                    <div class="avatar-wrapper mx-auto mb-3">
                        <?php if ($receiverAvatar): ?>
                            <img src="<?= htmlspecialchars($receiverAvatar) ?>" alt="<?= $receiverName ?>" class="avatar-lg rounded-circle">
                        <?php else: ?>
                            <div class="avatar-lg rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto">
                                <span class="fs-2"><?= strtoupper(substr($receiverName, 0, 1)) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <h4 class="mb-1"><?= $receiverName ?></h4>
                    <p class="text-muted mb-0">
                        <i class="fas fa-building me-1"></i><?= htmlspecialchars($receiverTimebank) ?>
                    </p>
                </div>

                <!-- Transaction Summary -->
                <div class="transaction-summary bg-light rounded p-3 mb-4">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="text-muted small">Amount</div>
                            <div class="fw-bold text-primary fs-4"><?= $amount ?> hrs</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Completed</div>
                            <div class="fw-bold">
                                <?php if ($completedAt): ?>
                                    <?= date('j M Y', strtotime($completedAt)) ?>
                                <?php else: ?>
                                    Recently
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($description): ?>
                        <div class="mt-3 pt-3 border-top">
                            <div class="text-muted small mb-1">Description</div>
                            <div><?= $description ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Review Form -->
                <form method="POST" action="<?= $basePath ?>/federation/review/<?= $transactionId ?>" id="review-form">
                    <?= \Nexus\Core\Csrf::input() ?>

                    <!-- Star Rating -->
                    <div class="form-group mb-4">
                        <label class="form-label fw-bold">How was your experience?</label>
                        <div class="star-rating d-flex justify-content-center gap-2 my-3" id="star-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <button type="button" class="star-btn" data-rating="<?= $i ?>" aria-label="<?= $i ?> star">
                                    <i class="far fa-star"></i>
                                </button>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="rating-input" value="0" required>
                        <div class="rating-text text-center text-muted" id="rating-text">Click to rate</div>
                    </div>

                    <!-- Comment -->
                    <div class="form-group mb-4">
                        <label for="comment" class="form-label fw-bold">Share your experience (optional)</label>
                        <textarea name="comment" id="comment" class="form-control" rows="4"
                            placeholder="How was working with <?= $receiverName ?>? Was the exchange smooth? Would you recommend them to others?"
                            maxlength="2000"></textarea>
                        <div class="form-text text-end"><span id="char-count">0</span>/2000</div>
                    </div>

                    <!-- Submit -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg" id="submit-btn" disabled>
                            <i class="fas fa-paper-plane me-2"></i>Submit Review
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Guidelines -->
        <div class="text-center mt-4 text-muted small" style="max-width: 500px; margin: 0 auto;">
            <p><i class="fas fa-info-circle me-1"></i>Reviews help build trust across timebanks. Please be honest and constructive in your feedback.</p>
        </div>
    </div>
</div>

<style>
.federation-review-page {
    min-height: calc(100vh - 200px);
    background: var(--bg-secondary, #f8f9fa);
}

.review-card {
    border: none;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    border-radius: 16px;
    overflow: hidden;
}

.review-card .card-header {
    background: var(--primary, #4CAF50);
    color: white;
    padding: 1.5rem;
}

.review-card .card-body {
    padding: 2rem;
}

.avatar-lg {
    width: 80px;
    height: 80px;
    object-fit: cover;
}

.star-rating .star-btn {
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    font-size: 2.5rem;
    color: #ddd;
    transition: all 0.2s ease;
}

.star-rating .star-btn:hover,
.star-rating .star-btn.hovered {
    color: #ffc107;
    transform: scale(1.15);
}

.star-rating .star-btn.active {
    color: #ffc107;
}

.star-rating .star-btn.active i {
    font-weight: 900;
}

.star-rating .star-btn i {
    transition: font-weight 0.2s;
}

.rating-text {
    font-size: 0.9rem;
    min-height: 1.5em;
}

.transaction-summary {
    background: var(--bg-secondary, #f8f9fa);
}

/* Dark mode support */
[data-theme="dark"] .federation-review-page {
    background: var(--bg-primary, #1a1a1a);
}

[data-theme="dark"] .review-card {
    background: var(--bg-secondary, #2d2d2d);
}

[data-theme="dark"] .transaction-summary {
    background: var(--bg-tertiary, #3d3d3d);
}

[data-theme="dark"] .star-rating .star-btn {
    color: #555;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('#star-rating .star-btn');
    const ratingInput = document.getElementById('rating-input');
    const ratingText = document.getElementById('rating-text');
    const submitBtn = document.getElementById('submit-btn');
    const commentTextarea = document.getElementById('comment');
    const charCount = document.getElementById('char-count');

    const ratingLabels = {
        1: 'Poor - Had significant issues',
        2: 'Fair - Below expectations',
        3: 'Good - Met expectations',
        4: 'Very Good - Above expectations',
        5: 'Excellent - Outstanding experience!'
    };

    // Star rating interaction
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.dataset.rating);
            ratingInput.value = rating;
            submitBtn.disabled = false;

            stars.forEach(s => {
                const starRating = parseInt(s.dataset.rating);
                s.classList.toggle('active', starRating <= rating);
                s.querySelector('i').classList.toggle('fas', starRating <= rating);
                s.querySelector('i').classList.toggle('far', starRating > rating);
            });

            ratingText.textContent = ratingLabels[rating];
            ratingText.classList.remove('text-muted');
            ratingText.classList.add('text-primary', 'fw-bold');
        });

        star.addEventListener('mouseenter', function() {
            const rating = parseInt(this.dataset.rating);
            stars.forEach(s => {
                s.classList.toggle('hovered', parseInt(s.dataset.rating) <= rating);
            });
            ratingText.textContent = ratingLabels[rating];
        });

        star.addEventListener('mouseleave', function() {
            stars.forEach(s => s.classList.remove('hovered'));
            const currentRating = parseInt(ratingInput.value);
            if (currentRating > 0) {
                ratingText.textContent = ratingLabels[currentRating];
            } else {
                ratingText.textContent = 'Click to rate';
            }
        });
    });

    // Character counter
    commentTextarea.addEventListener('input', function() {
        charCount.textContent = this.value.length;
    });

    // Form submission
    document.getElementById('review-form').addEventListener('submit', function(e) {
        if (parseInt(ratingInput.value) < 1) {
            e.preventDefault();
            alert('Please select a rating before submitting.');
            return false;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
    });
});
</script>
