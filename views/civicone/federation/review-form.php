<?php
/**
 * Federation Review Form
 * CivicOne Theme - WCAG 2.1 AA Compliant
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
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <!-- Back Button -->
    <a href="<?= $basePath ?>/federation/transactions" class="civic-fed-back-link" aria-label="Return to transactions">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back to Transactions
    </a>

    <!-- Review Card -->
    <article class="civic-fed-review-card" aria-labelledby="review-heading">
        <!-- Header -->
        <header class="civic-fed-review-header">
            <h1 id="review-heading">
                <i class="fa-solid fa-star" aria-hidden="true"></i>
                Leave a Review
            </h1>
        </header>

        <!-- Receiver Info -->
        <section class="civic-fed-review-recipient" aria-label="Review recipient">
            <div class="civic-fed-avatar civic-fed-avatar--large" aria-hidden="true">
                <?php if ($receiverAvatar): ?>
                    <img src="<?= htmlspecialchars($receiverAvatar) ?>"
                         onerror="this.src='<?= $fallbackAvatar ?>'"
                         alt="">
                <?php else: ?>
                    <span><?= strtoupper(substr($receiverName, 0, 1)) ?></span>
                <?php endif; ?>
            </div>
            <h3><?= $receiverName ?></h3>
            <div class="civic-fed-badge civic-fed-badge--partner">
                <i class="fa-solid fa-building" aria-hidden="true"></i>
                <?= htmlspecialchars($receiverTimebank) ?>
            </div>
        </section>

        <!-- Transaction Summary -->
        <section class="civic-fed-review-summary" aria-label="Transaction details">
            <div class="civic-fed-review-summary-row">
                <div class="civic-fed-review-summary-item">
                    <div class="civic-fed-review-summary-label">Amount</div>
                    <div class="civic-fed-review-summary-value"><?= $amount ?> hrs</div>
                </div>
                <div class="civic-fed-review-summary-item">
                    <div class="civic-fed-review-summary-label">Completed</div>
                    <div class="civic-fed-review-summary-value">
                        <?php if ($completedAt): ?>
                            <time datetime="<?= date('c', strtotime($completedAt)) ?>"><?= date('M j, Y', strtotime($completedAt)) ?></time>
                        <?php else: ?>
                            Recently
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if ($description): ?>
                <div class="civic-fed-review-summary-desc">
                    <div class="civic-fed-review-summary-label">Description</div>
                    <div class="civic-fed-review-summary-text"><?= $description ?></div>
                </div>
            <?php endif; ?>
        </section>

        <!-- Review Form -->
        <form method="POST" action="<?= $basePath ?>/federation/review/<?= $transactionId ?>" class="civic-fed-form" id="review-form">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- Star Rating -->
            <div class="civic-fed-form-group">
                <label class="civic-fed-label" id="rating-label">
                    <i class="fa-solid fa-star-half-stroke" aria-hidden="true"></i>
                    How was your experience?
                </label>
                <div class="civic-fed-star-rating" id="star-rating" role="radiogroup" aria-labelledby="rating-label">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="button"
                                class="civic-fed-star-btn"
                                data-rating="<?= $i ?>"
                                role="radio"
                                aria-checked="false"
                                aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">
                            <i class="far fa-star" aria-hidden="true"></i>
                        </button>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" id="rating-input" value="0" required>
                <div class="civic-fed-rating-text" id="rating-text" aria-live="polite">Click to rate</div>
            </div>

            <!-- Comment -->
            <div class="civic-fed-form-group">
                <label for="comment" class="civic-fed-label">
                    <i class="fa-solid fa-comment-dots" aria-hidden="true"></i>
                    Share your experience (optional)
                </label>
                <textarea name="comment"
                          id="comment"
                          class="civic-fed-textarea"
                          rows="5"
                          placeholder="How was working with <?= $receiverName ?>? Was the exchange smooth? Would you recommend them to others?"
                          maxlength="2000"
                          aria-describedby="char-count-text"></textarea>
                <div class="civic-fed-char-count" id="char-count-text">
                    <span id="char-count">0</span>/2000 characters
                </div>
            </div>

            <!-- Submit -->
            <button type="submit" class="civic-fed-btn civic-fed-btn--primary civic-fed-btn--full" id="submit-btn" disabled aria-disabled="true">
                <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                Submit Review
            </button>
        </form>

        <!-- Guidelines -->
        <aside class="civic-fed-notice" role="note">
            <i class="fa-solid fa-shield-heart" aria-hidden="true"></i>
            <div>
                Reviews help build trust across timebanks. Please be honest and constructive in your feedback.
            </div>
        </aside>
    </article>
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
        const stars = starRating.querySelectorAll('.civic-fed-star-btn');

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
        window.addEventListener('online', () => banner.classList.remove('civic-fed-offline-banner--visible'));
        window.addEventListener('offline', () => banner.classList.add('civic-fed-offline-banner--visible'));
        if (!navigator.onLine) banner.classList.add('civic-fed-offline-banner--visible');
    }
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
