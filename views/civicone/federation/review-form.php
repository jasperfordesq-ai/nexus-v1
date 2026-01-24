<?php
/**
 * Federation Review Form
 * GOV.UK Design System (WCAG 2.1 AA)
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
$fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($receiverName) . '&background=1d70b8&color=fff&size=200';
$amount = number_format((float)($transaction['amount'] ?? 0), 2);
$description = htmlspecialchars($transaction['description'] ?? 'Time exchange');
$completedAt = $transaction['completed_at'] ?? $transaction['created_at'] ?? null;
?>

<div class="govuk-width-container">
    <!-- Offline Banner -->
    <div class="govuk-notification-banner govuk-notification-banner--warning govuk-!-display-none" id="offlineBanner" role="alert" aria-live="polite" data-module="govuk-notification-banner">
        <div class="govuk-notification-banner__content">
            <p class="govuk-notification-banner__heading">
                <i class="fa-solid fa-wifi-slash govuk-!-margin-right-2" aria-hidden="true"></i>
                No internet connection
            </p>
        </div>
    </div>

    <!-- Back Link -->
    <a href="<?= $basePath ?>/federation/transactions" class="govuk-back-link govuk-!-margin-top-4">
        Back to Transactions
    </a>

    <main class="govuk-main-wrapper govuk-!-padding-top-4" id="main-content" role="main">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <!-- Review Card -->
                <div class="govuk-!-padding-6" style="background: #fff; border: 1px solid #b1b4b6; border-left: 5px solid #f47738;">
                    <!-- Header -->
                    <h1 class="govuk-heading-xl govuk-!-margin-bottom-6">
                        <i class="fa-solid fa-star govuk-!-margin-right-2" style="color: #f47738;" aria-hidden="true"></i>
                        Leave a Review
                    </h1>

                    <!-- Receiver Info -->
                    <div class="govuk-!-padding-4 govuk-!-margin-bottom-6 govuk-!-text-align-center civicone-panel-bg">
                        <div style="width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 16px; overflow: hidden; border: 3px solid #1d70b8;">
                            <?php if ($receiverAvatar): ?>
                                <img src="<?= htmlspecialchars($receiverAvatar) ?>"
                                     onerror="this.src='<?= $fallbackAvatar ?>'"
                                     alt=""
                                     style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; background: #1d70b8; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: bold;">
                                    <?= strtoupper(substr($receiverName, 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <p class="govuk-heading-m govuk-!-margin-bottom-2"><?= $receiverName ?></p>
                        <span class="govuk-tag govuk-tag--grey">
                            <i class="fa-solid fa-building govuk-!-margin-right-1" aria-hidden="true"></i>
                            <?= htmlspecialchars($receiverTimebank) ?>
                        </span>
                    </div>

                    <!-- Transaction Summary -->
                    <dl class="govuk-summary-list govuk-!-margin-bottom-6">
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Amount</dt>
                            <dd class="govuk-summary-list__value">
                                <strong style="color: #00703c;"><?= $amount ?> hrs</strong>
                            </dd>
                        </div>
                        <div class="govuk-summary-list__row">
                            <dt class="govuk-summary-list__key">Completed</dt>
                            <dd class="govuk-summary-list__value">
                                <?php if ($completedAt): ?>
                                    <time datetime="<?= date('c', strtotime($completedAt)) ?>">
                                        <?= date('M j, Y', strtotime($completedAt)) ?>
                                    </time>
                                <?php else: ?>
                                    Recently
                                <?php endif; ?>
                            </dd>
                        </div>
                        <?php if ($description): ?>
                            <div class="govuk-summary-list__row">
                                <dt class="govuk-summary-list__key">Description</dt>
                                <dd class="govuk-summary-list__value"><?= $description ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>

                    <!-- Review Form -->
                    <form method="POST" action="<?= $basePath ?>/federation/review/<?= $transactionId ?>" id="review-form">
                        <?= \Nexus\Core\Csrf::input() ?>

                        <!-- Star Rating -->
                        <div class="govuk-form-group">
                            <label class="govuk-label govuk-label--m" id="rating-label">
                                <i class="fa-solid fa-star-half-stroke govuk-!-margin-right-2" style="color: #f47738;" aria-hidden="true"></i>
                                How was your experience?
                            </label>
                            <div id="star-rating" role="radiogroup" aria-labelledby="rating-label" style="display: flex; gap: 8px; margin-top: 8px;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <button type="button"
                                            class="govuk-button govuk-button--secondary"
                                            data-rating="<?= $i ?>"
                                            role="radio"
                                            aria-checked="false"
                                            aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"
                                            style="width: 48px; height: 48px; padding: 0; margin-bottom: 0;">
                                        <i class="far fa-star fa-lg" aria-hidden="true"></i>
                                    </button>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="rating" id="rating-input" value="0" required>
                            <p class="govuk-hint govuk-!-margin-top-2" id="rating-text" aria-live="polite">Click to rate</p>
                        </div>

                        <!-- Comment -->
                        <div class="govuk-form-group">
                            <label class="govuk-label govuk-label--m" for="comment">
                                <i class="fa-solid fa-comment-dots govuk-!-margin-right-2" style="color: #505a5f;" aria-hidden="true"></i>
                                Share your experience (optional)
                            </label>
                            <p class="govuk-hint">
                                How was working with <?= $receiverName ?>? Was the exchange smooth? Would you recommend them to others?
                            </p>
                            <textarea name="comment"
                                      id="comment"
                                      class="govuk-textarea"
                                      rows="5"
                                      maxlength="2000"
                                      aria-describedby="char-count-text"></textarea>
                            <p class="govuk-hint govuk-!-margin-top-1" id="char-count-text">
                                <span id="char-count">0</span>/2000 characters
                            </p>
                        </div>

                        <!-- Submit -->
                        <button type="submit" class="govuk-button" style="background: #f47738;" id="submit-btn" disabled aria-disabled="true" data-module="govuk-button">
                            <i class="fa-solid fa-paper-plane govuk-!-margin-right-2" aria-hidden="true"></i>
                            Submit Review
                        </button>
                    </form>

                    <!-- Guidelines -->
                    <div class="govuk-inset-text govuk-!-margin-top-6">
                        <p class="govuk-body govuk-!-margin-bottom-0">
                            <i class="fa-solid fa-shield-heart govuk-!-margin-right-2" style="color: #1d70b8;" aria-hidden="true"></i>
                            Reviews help build trust across timebanks. Please be honest and constructive in your feedback.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
(function() {
    'use strict';

    var starRating = document.getElementById('star-rating');
    var ratingInput = document.getElementById('rating-input');
    var ratingText = document.getElementById('rating-text');
    var submitBtn = document.getElementById('submit-btn');
    var commentInput = document.getElementById('comment');
    var charCount = document.getElementById('char-count');

    var ratingLabels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

    if (starRating) {
        var stars = starRating.querySelectorAll('button');

        stars.forEach(function(star, index) {
            star.addEventListener('click', function() {
                var rating = parseInt(this.dataset.rating);
                ratingInput.value = rating;

                stars.forEach(function(s, i) {
                    var icon = s.querySelector('i');
                    s.setAttribute('aria-checked', i < rating ? 'true' : 'false');
                    icon.className = i < rating ? 'fa-solid fa-star fa-lg' : 'far fa-star fa-lg';
                    icon.style.color = i < rating ? '#f47738' : '';
                });

                ratingText.textContent = ratingLabels[rating];
                submitBtn.disabled = false;
                submitBtn.setAttribute('aria-disabled', 'false');
            });

            star.addEventListener('mouseenter', function() {
                var rating = parseInt(this.dataset.rating);
                stars.forEach(function(s, i) {
                    var icon = s.querySelector('i');
                    icon.className = i < rating ? 'fa-solid fa-star fa-lg' : 'far fa-star fa-lg';
                    icon.style.color = i < rating ? '#f47738' : '';
                });
            });
        });

        starRating.addEventListener('mouseleave', function() {
            var currentRating = parseInt(ratingInput.value);
            stars.forEach(function(s, i) {
                var icon = s.querySelector('i');
                icon.className = i < currentRating ? 'fa-solid fa-star fa-lg' : 'far fa-star fa-lg';
                icon.style.color = i < currentRating ? '#f47738' : '';
            });
        });
    }

    if (commentInput && charCount) {
        commentInput.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });
    }

    // Offline indicator
    var banner = document.getElementById('offlineBanner');
    function updateOffline(offline) {
        if (banner) banner.classList.toggle('govuk-!-display-none', !offline);
    }
    window.addEventListener('online', function() { updateOffline(false); });
    window.addEventListener('offline', function() { updateOffline(true); });
    if (!navigator.onLine) updateOffline(true);
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
