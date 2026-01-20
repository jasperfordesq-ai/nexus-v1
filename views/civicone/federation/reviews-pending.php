<?php
/**
 * Pending Reviews List
 * CivicOne Theme - WCAG 2.1 AA Compliant
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
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <!-- Back Link -->
    <a href="<?= $basePath ?>/federation/transactions" class="civic-fed-back-link" aria-label="Return to transactions">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back to Transactions
    </a>

    <!-- Header -->
    <header class="civic-fed-header">
        <h1>
            <i class="fa-solid fa-star" aria-hidden="true"></i>
            Pending Reviews
        </h1>
        <p class="civic-fed-subtitle">Leave feedback for your federated exchanges</p>
    </header>

    <?php if (empty($pendingReviews)): ?>
        <!-- Empty State -->
        <div class="civic-fed-empty" role="status" aria-labelledby="empty-title">
            <div class="civic-fed-empty-icon" aria-hidden="true">
                <i class="fa-solid fa-check-circle"></i>
            </div>
            <h3 id="empty-title">All caught up!</h3>
            <p>You've reviewed all your completed federated exchanges.</p>
            <a href="<?= $basePath ?>/federation/transactions" class="civic-fed-btn civic-fed-btn--primary">
                <i class="fa-solid fa-exchange-alt" aria-hidden="true"></i>
                View Transactions
            </a>
        </div>
    <?php else: ?>
        <!-- Pending Reviews List -->
        <div class="civic-fed-pending-list" role="list" aria-label="Pending reviews">
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
                <article class="civic-fed-pending-card" role="listitem">
                    <div class="civic-fed-pending-icon civic-fed-pending-icon--<?= $direction ?>" aria-hidden="true">
                        <i class="fa-solid fa-<?= $direction === 'sent' ? 'arrow-up' : 'arrow-down' ?>"></i>
                    </div>

                    <div class="civic-fed-pending-info">
                        <h3 class="civic-fed-pending-name"><?= $otherPartyName ?></h3>
                        <p class="civic-fed-pending-tenant">
                            <i class="fa-solid fa-building" aria-hidden="true"></i>
                            <?= $timebank ?>
                        </p>
                    </div>

                    <div class="civic-fed-pending-amount">
                        <div class="civic-fed-amount civic-fed-amount--<?= $direction ?>">
                            <?= $direction === 'sent' ? '-' : '+' ?><?= $amount ?> hrs
                        </div>
                        <?php if ($completedAt): ?>
                            <time class="civic-fed-pending-date" datetime="<?= date('c', strtotime($completedAt)) ?>">
                                <?= date('j M Y', strtotime($completedAt)) ?>
                            </time>
                        <?php endif; ?>
                    </div>

                    <a href="<?= $basePath ?>/federation/review/<?= $transactionId ?>"
                       class="civic-fed-btn civic-fed-btn--accent civic-fed-btn--small"
                       aria-label="Leave review for <?= $otherPartyName ?>">
                        <i class="fa-solid fa-star" aria-hidden="true"></i>
                        Leave Review
                    </a>

                    <?php if ($description): ?>
                        <div class="civic-fed-pending-desc">
                            <?= $description ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <!-- Info Box -->
        <aside class="civic-fed-notice" role="note">
            <i class="fa-solid fa-info-circle" aria-hidden="true"></i>
            <div>
                <strong>Why leave reviews?</strong>
                Reviews help build trust across timebanks. Your feedback helps other members make informed decisions
                and rewards great community members with recognition.
            </div>
        </aside>
    <?php endif; ?>
</div>

<script>
// Offline indicator
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    window.addEventListener('online', () => banner.classList.remove('civic-fed-offline-banner--visible'));
    window.addEventListener('offline', () => banner.classList.add('civic-fed-offline-banner--visible'));
    if (!navigator.onLine) banner.classList.add('civic-fed-offline-banner--visible');
})();
</script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
