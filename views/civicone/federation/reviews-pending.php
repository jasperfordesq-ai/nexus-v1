<?php
/**
 * Pending Reviews List - Glassmorphism 2025
 * Shows transactions that the user hasn't reviewed yet
 */

$pageTitle = $pageTitle ?? 'Pending Reviews';
$hideHero = true;

Nexus\Core\SEO::setTitle('Pending Reviews - Federation');
Nexus\Core\SEO::setDescription('Leave feedback for your completed federated exchanges.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$pendingReviews = $pendingReviews ?? [];
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="pending-reviews-wrapper">

        <!-- Header -->
        <header class="pending-header">
            <div>
                <h1>
                    <i class="fa-solid fa-star" aria-hidden="true"></i>
                    Pending Reviews
                </h1>
                <p>Leave feedback for your federated exchanges</p>
            </div>
            <a href="<?= $basePath ?>/federation/transactions" class="back-link" aria-label="Return to transactions">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Back
            </a>
        </header>

        <?php if (empty($pendingReviews)): ?>
            <!-- Empty State -->
            <div class="reviews-empty-state" role="status" aria-labelledby="empty-title">
                <div class="empty-icon" aria-hidden="true">
                    <i class="fa-solid fa-check-circle"></i>
                </div>
                <h4 id="empty-title">All caught up!</h4>
                <p>You've reviewed all your completed federated exchanges.</p>
                <a href="<?= $basePath ?>/federation/transactions" class="action-btn action-btn-primary">
                    <i class="fa-solid fa-exchange-alt" aria-hidden="true"></i>
                    View Transactions
                </a>
            </div>
        <?php else: ?>
            <!-- Pending Reviews List -->
            <div class="pending-list" role="list" aria-label="Pending reviews">
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
                    <article class="pending-card" role="listitem">
                        <div class="avatar-circle <?= $direction ?>" aria-hidden="true">
                            <i class="fa-solid fa-<?= $direction === 'sent' ? 'arrow-up' : 'arrow-down' ?>"></i>
                        </div>

                        <div class="party-info">
                            <h3 class="party-name"><?= $otherPartyName ?></h3>
                            <p class="party-timebank">
                                <i class="fa-solid fa-building" aria-hidden="true"></i>
                                <?= $timebank ?>
                            </p>
                        </div>

                        <div class="amount-info">
                            <div class="amount-value <?= $direction ?>">
                                <?= $direction === 'sent' ? '-' : '+' ?><?= $amount ?> hrs
                            </div>
                            <?php if ($completedAt): ?>
                                <time class="amount-date" datetime="<?= date('c', strtotime($completedAt)) ?>">
                                    <?= date('j M Y', strtotime($completedAt)) ?>
                                </time>
                            <?php endif; ?>
                        </div>

                        <a href="<?= $basePath ?>/federation/review/<?= $transactionId ?>"
                           class="review-btn"
                           aria-label="Leave review for <?= $otherPartyName ?>">
                            <i class="fa-solid fa-star" aria-hidden="true"></i>
                            Leave Review
                        </a>

                        <?php if ($description): ?>
                            <div class="description-line">
                                <?= $description ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- Info Box -->
            <aside class="reviews-info-box" role="note">
                <i class="fa-solid fa-info-circle" aria-hidden="true"></i>
                <p>
                    <strong>Why leave reviews?</strong>
                    Reviews help build trust across timebanks. Your feedback helps other members make informed decisions
                    and rewards great community members with recognition.
                </p>
            </aside>
        <?php endif; ?>

    </div>
</div>

<script>
// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('visible'));
    window.addEventListener('offline', () => banner.classList.add('visible'));
    if (!navigator.onLine) banner.classList.add('visible');
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
