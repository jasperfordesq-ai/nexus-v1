<?php
/**
 * Federation Review Form - Glassmorphism 2025
 * Allows users to leave reviews after completing a federated transaction
 */

$pageTitle = $pageTitle ?? 'Leave a Review';
$hideHero = true;

Nexus\Core\SEO::setTitle('Leave a Review - Federated Transaction');
Nexus\Core\SEO::setDescription('Share your experience with a federated exchange.');

require dirname(dirname(__DIR__)) . '/layouts/modern/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$receiver = $receiver ?? [];
$transaction = $transaction ?? [];
$receiverTimebank = $receiverTimebank ?? 'Unknown Timebank';
$transactionId = $transactionId ?? 0;

$receiverName = htmlspecialchars($receiver['first_name'] ?? $receiver['name'] ?? 'Member');
$receiverAvatar = $receiver['avatar_url'] ?? null;
$fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($receiverName) . '&background=8b5cf6&color=fff&size=200';
$amount = number_format((float)($transaction['amount'] ?? 0), 2);
$description = htmlspecialchars($transaction['description'] ?? 'Time exchange');
$completedAt = $transaction['completed_at'] ?? $transaction['created_at'] ?? null;
?>

<link rel="stylesheet" href="<?= $basePath ?>/assets/css/federation-reviews.min.css?v=<?= time() ?>">

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="federation-review-wrapper">

        <!-- Back Button -->
        <a href="<?= $basePath ?>/federation/transactions" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Transactions
        </a>

        <!-- Review Card -->
        <div class="review-card">
            <!-- Header -->
            <div class="review-header">
                <h2>
                    <i class="fa-solid fa-star"></i>
                    Leave a Review
                </h2>
            </div>

            <!-- Receiver Info -->
            <div class="receiver-info">
                <div class="avatar-wrapper">
                    <?php if ($receiverAvatar): ?>
                        <img src="<?= htmlspecialchars($receiverAvatar) ?>"
                             onerror="this.src='<?= $fallbackAvatar ?>'"
                             alt="<?= $receiverName ?>"
                             class="avatar-lg">
                    <?php else: ?>
                        <div class="avatar-lg">
                            <span class="avatar-initial-text"><?= strtoupper(substr($receiverName, 0, 1)) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <h4><?= $receiverName ?></h4>
                <div class="federation-badge">
                    <i class="fa-solid fa-building"></i>
                    <?= htmlspecialchars($receiverTimebank) ?>
                </div>
            </div>

            <!-- Transaction Summary -->
            <div class="transaction-summary">
                <div class="row">
                    <div class="col-6">
                        <div class="text-muted small">Amount</div>
                        <div class="fw-bold text-primary"><?= $amount ?> hrs</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Completed</div>
                        <div class="fw-bold">
                            <?php if ($completedAt): ?>
                                <?= date('M j, Y', strtotime($completedAt)) ?>
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
            </div>

            <!-- Review Form -->
            <div class="review-form-section">
                <form method="POST" action="<?= $basePath ?>/federation/review/<?= $transactionId ?>" id="review-form">
                    <?= \Nexus\Core\Csrf::input() ?>

                    <!-- Star Rating -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fa-solid fa-star-half-stroke icon-amber"></i>
                            How was your experience?
                        </label>
                        <div class="star-rating" id="star-rating" role="radiogroup" aria-label="Rate your experience">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <button type="button"
                                        class="star-btn"
                                        data-rating="<?= $i ?>"
                                        role="radio"
                                        aria-checked="false"
                                        aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">
                                    <i class="far fa-star"></i>
                                </button>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="rating-input" value="0" required>
                        <div class="rating-text text-muted" id="rating-text">Click to rate</div>
                    </div>

                    <!-- Comment -->
                    <div class="form-group">
                        <label for="comment" class="form-label">
                            <i class="fa-solid fa-comment-dots icon-purple"></i>
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
                            <i class="fa-solid fa-paper-plane"></i>
                            Submit Review
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Guidelines -->
        <div class="review-guidelines">
            <p>
                <i class="fa-solid fa-shield-heart"></i>
                Reviews help build trust across timebanks. Please be honest and constructive in your feedback.
            </p>
        </div>
    </div>
</div>

<script src="/assets/js/federation-review-form.min.js?v=<?= time() ?>"></script>

<?php require dirname(dirname(__DIR__)) . '/layouts/modern/footer.php'; ?>
