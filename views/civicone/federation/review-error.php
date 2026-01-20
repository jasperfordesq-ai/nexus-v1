<?php
/**
 * Review Error Page - Glassmorphism 2025
 * Shown when a user cannot leave a review for a transaction
 */

$pageTitle = $pageTitle ?? 'Cannot Submit Review';
$hideHero = true;

Nexus\Core\SEO::setTitle('Cannot Submit Review - Federation');
Nexus\Core\SEO::setDescription('Unable to submit your review at this time.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$error = $error ?? 'Unable to submit review';
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="federation-error-wrapper">

        <article class="error-card" role="alert" aria-labelledby="error-title">
            <div class="error-icon" aria-hidden="true">
                <i class="fa-solid fa-exclamation-circle"></i>
            </div>

            <h2 id="error-title">Cannot Submit Review</h2>

            <p class="error-message"><?= htmlspecialchars($error) ?></p>

            <nav class="error-actions" aria-label="Error actions">
                <a href="<?= $basePath ?>/federation/transactions" class="action-btn action-btn-primary">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    Back to Transactions
                </a>
                <a href="<?= $basePath ?>/federation/reviews/pending" class="action-btn action-btn-secondary">
                    <i class="fa-solid fa-star" aria-hidden="true"></i>
                    View Pending Reviews
                </a>
            </nav>
        </article>

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
