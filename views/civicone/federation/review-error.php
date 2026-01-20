<?php
/**
 * Review Error Page
 * CivicOne Theme - WCAG 2.1 AA Compliant
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
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <div class="civic-fed-error-card" role="alert" aria-labelledby="error-title">
        <div class="civic-fed-error-icon" aria-hidden="true">
            <i class="fa-solid fa-exclamation-circle"></i>
        </div>

        <h1 id="error-title" class="civic-fed-error-title">Cannot Submit Review</h1>

        <p class="civic-fed-error-message"><?= htmlspecialchars($error) ?></p>

        <div class="civic-fed-actions" aria-label="Error actions">
            <a href="<?= $basePath ?>/federation/transactions" class="civic-fed-btn civic-fed-btn--primary">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Back to Transactions
            </a>
            <a href="<?= $basePath ?>/federation/reviews/pending" class="civic-fed-btn civic-fed-btn--secondary">
                <i class="fa-solid fa-star" aria-hidden="true"></i>
                View Pending Reviews
            </a>
        </div>
    </div>
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
