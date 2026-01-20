<?php
/**
 * Federation Review Form - Glassmorphism 2025
 * Allows users to leave reviews after completing a federated transaction
 */

$pageTitle = $pageTitle ?? 'Leave a Review';
$hideHero = true;

Nexus\Core\SEO::setTitle('Leave a Review - Federated Transaction');
Nexus\Core\SEO::setDescription('Share your experience with a federated exchange.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$receiver = $receiver ?? [];
$transaction = $transaction ?? [];
$receiverTimebank = $receiverTimebank ?? 'Unknown Timebank';
$transactionId = $transactionId ?? 0;

$receiverName = htmlspecialchars($receiver['first_name'] ?? $receiver['name'] ?? 'Member');
$receiverAvatar = $receiver['avatar_url'] ?? null;
$fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($receiverName) . '&background=00796B&color=fff&size=200';
$amount = number_format((float)($transaction['amount'] ?? 0), 2);
$description = htmlspecialchars($transaction['description'] ?? 'Time exchange');
$completedAt = $transaction['completed_at'] ?? $transaction['created_at'] ?? null;
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="federation-review-wrapper">

        <!-- Back Button -->
        <a href="<?= $basePath ?>/federation/transactions" class="back-link" aria-label="Return to transactions">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Back to Transactions
        </a>

        <!-- Review Card -->
        <article class="review-card" aria-labelledby="review-heading">
            <!-- Header -->
            <header class="review-header">
                <h2 id="review-heading">
                    <i class="fa-solid fa-star" aria-hidden="true"></i>
                    Leave a Review
                </h2>
            </header>

            <!-- Receiver Info -->
            <section class="receiver-info" aria-label="Review recipient">
                <div class="avatar-wrapper" aria-hidden="true">
                    <?php if ($receiverAvatar): ?>
                        <img src="<?= htmlspecialchars($receiverAvatar) ?>"
                             onerror="this.src='<?= $fallbackAvatar ?>'"
                             alt=""
                             class="avatar-lg">
                    <?php else: ?>
                        <div class="avatar-lg">
                            <span><?= strtoupper(substr($receiverName, 0, 1)) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <h4><?= $receiverName ?></h4>
                <div class="federation-badge">
                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                    <?= htmlspecialchars($receiverTimebank) ?>
                </div>
            </section>

            <!-- Transaction Summary -->
            <section class="transaction-summary" aria-label="Transaction details">
                <div class="row">
                    <div class="col-6">
                        <div class="text-muted small">Amount</div>
                        <div class="fw-bold text-primary"><?= $amount ?> hrs</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Completed</div>
                        <div class="fw-bold">
                            <?php if ($completedAt): ?>
                                <time datetime="<?= date('c', strtotime($completedAt)) ?>"><?= date('M j, Y', strtotime($completedAt)) ?></time>
                            <?php else: ?>
                                Recently
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php if ($description): ?>
                    <div class="border-top">
                        <div class="description-label">Description</div>
                        <div class="description-text"><?= $description ?></div>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Review Form -->
            <section class="review-form-section" aria-labelledby="review-heading">
                <form method="POST" action="<?= $basePath ?>/federation/review/<?= $transactionId ?>" id="review-form">
                    <?= \Nexus\Core\Csrf::input() ?>

                    <!-- Star Rating -->
                    <div class="form-group">
                        <label class="form-label" id="rating-label">
                            <i class="fa-solid fa-star-half-stroke star-icon" aria-hidden="true"></i>
                            How was your experience?
                        </label>
                        <div class="star-rating" id="star-rating" role="radiogroup" aria-labelledby="rating-label">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <button type="button"
                                        class="star-btn"
                                        data-rating="<?= $i ?>"
                                        role="radio"
                                        aria-checked="false"
                                        aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">
                                    <i class="far fa-star" aria-hidden="true"></i>
                                </button>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="rating-input" value="0" required>
                        <div class="rating-text" id="rating-text" aria-live="polite">Click to rate</div>
                    </div>

                    <!-- Comment -->
                    <div class="form-group">
                        <label for="comment" class="form-label">
                            <i class="fa-solid fa-comment-dots comment-icon" aria-hidden="true"></i>
                            Share your experience (optional)
                        </label>
                        <textarea name="comment"
                                  id="comment"
                                  class="form-control"
                                  rows="5"
                                  placeholder="How was working with <?= $receiverName ?>? Was the exchange smooth? Would you recommend them to others?"
                                  maxlength="2000"
                                  aria-describedby="char-count-text"></textarea>
                        <div class="form-text text-end" id="char-count-text">
                            <span id="char-count">0</span>/2000 characters
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="d-grid">
                        <button type="submit" class="btn-primary" id="submit-btn" disabled aria-disabled="true">
                            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                            Submit Review
                        </button>
                    </div>
                </form>
            </section>
        </article>

        <!-- Guidelines -->
        <aside class="review-guidelines" role="note">
            <p>
                <i class="fa-solid fa-shield-heart" aria-hidden="true"></i>
                Reviews help build trust across timebanks. Please be honest and constructive in your feedback.
            </p>
        </aside>
    </div>
</div>

<script>
// Review form functionality
(function() {
    'use strict';

    const starRating = document.getElementById('star-rating');
    const ratingInput = document.getElementById('rating-input');
    const ratingText = document.getElementById('rating-text');
    const submitBtn = document.getElementById('submit-btn');
    const commentInput = document.getElementById('comment');
    const charCount = document.getElementById('char-count');

    const ratingLabels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

    if (starRating) {
        const stars = starRating.querySelectorAll('.star-btn');

        stars.forEach((star, index) => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.dataset.rating);
                ratingInput.value = rating;

                // Update stars
                stars.forEach((s, i) => {
                    const icon = s.querySelector('i');
                    s.setAttribute('aria-checked', i < rating ? 'true' : 'false');
                    icon.className = i < rating ? 'fa-solid fa-star' : 'far fa-star';
                });

                // Update text
                ratingText.textContent = ratingLabels[rating];

                // Enable submit
                submitBtn.disabled = false;
                submitBtn.setAttribute('aria-disabled', 'false');
            });

            // Hover effect
            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.dataset.rating);
                stars.forEach((s, i) => {
                    const icon = s.querySelector('i');
                    icon.className = i < rating ? 'fa-solid fa-star' : 'far fa-star';
                });
            });
        });

        starRating.addEventListener('mouseleave', function() {
            const currentRating = parseInt(ratingInput.value);
            stars.forEach((s, i) => {
                const icon = s.querySelector('i');
                icon.className = i < currentRating ? 'fa-solid fa-star' : 'far fa-star';
            });
        });
    }

    // Character counter
    if (commentInput && charCount) {
        commentInput.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });
    }

    // Offline indicator
    const banner = document.getElementById('offlineBanner');
    if (banner) {
        window.addEventListener('online', () => banner.classList.remove('visible'));
        window.addEventListener('offline', () => banner.classList.add('visible'));
        if (!navigator.onLine) banner.classList.add('visible');
    }
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
