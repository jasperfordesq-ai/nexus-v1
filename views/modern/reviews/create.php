<?php
// View: reviews/create.php
$pageTitle = 'Leave a Review';
$hideHero = true;

// Include the modern layout header
require dirname(__DIR__, 2) . '/layouts/header.php';
?>

<!-- Review Page Styles -->

<div class="review-page-wrapper">
    <div class="review-card-container">
        <div class="review-glass-card">
            <!-- Header -->
            <div class="review-header">
                <h2>Rate Experience</h2>
                <div class="subtitle">with <?= htmlspecialchars($receiver['name']) ?></div>
            </div>

            <!-- Body -->
            <div class="review-body">
                <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/reviews/store" method="POST">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="transaction_id" value="<?= htmlspecialchars($transaction_id ?? '') ?>">
                    <input type="hidden" name="receiver_id" value="<?= htmlspecialchars($receiver['id']) ?>">

                    <!-- Star Rating -->
                    <div class="star-rating-section">
                        <label class="star-rating-label">Tap stars to rate</label>
                        <div class="star-rating-wrapper">
                            <input type="radio" name="rating" value="5" id="r5"><label for="r5" title="Excellent">★</label>
                            <input type="radio" name="rating" value="4" id="r4"><label for="r4" title="Good">★</label>
                            <input type="radio" name="rating" value="3" id="r3"><label for="r3" title="Average">★</label>
                            <input type="radio" name="rating" value="2" id="r2"><label for="r2" title="Poor">★</label>
                            <input type="radio" name="rating" value="1" id="r1"><label for="r1" title="Very Poor">★</label>
                        </div>
                    </div>

                    <!-- Comment -->
                    <div class="comment-section">
                        <label class="comment-label">Leave a Comment (Optional)</label>
                        <textarea name="comment" rows="4" class="comment-textarea" placeholder="How did it go? Share details..."></textarea>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="submit-btn">Submit Review</button>
                </form>
            </div>
        </div>

        <div class="back-link-section">
            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/wallet" class="back-link">Return to Wallet</a>
        </div>
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/layouts/footer.php'; ?>
