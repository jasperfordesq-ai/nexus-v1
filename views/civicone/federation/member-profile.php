<?php
// Federation Member Profile - Glassmorphism 2025
$pageTitle = $pageTitle ?? "Member Profile";
$hideHero = true;

Nexus\Core\SEO::setTitle(($member['name'] ?? 'Member') . ' - Federated Profile');
Nexus\Core\SEO::setDescription('View federated member profile from partner timebank.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

// Extract data passed from controller
$member = $member ?? [];
$canMessage = $canMessage ?? false;
$canTransact = $canTransact ?? false;
$reviews = $reviews ?? [];
$reviewStats = $reviewStats ?? null;
$trustScore = $trustScore ?? ['score' => 0, 'level' => 'new'];
$pendingReviewTransaction = $pendingReviewTransaction ?? null; // Transaction ID if user can review this member

$memberName = $member['name'] ?? 'Member';
$fallbackUrl = 'https://ui-avatars.com/api/?name=' . urlencode($memberName) . '&background=00796B&color=fff&size=200';
$avatarUrl = !empty($member['avatar_url']) ? $member['avatar_url'] : $fallbackUrl;

$reachClass = '';
$reachLabel = '';
$reachIcon = '';
switch ($member['service_reach'] ?? 'local_only') {
    case 'remote_ok':
        $reachClass = 'remote';
        $reachLabel = 'Offers Remote Services';
        $reachIcon = 'fa-laptop-house';
        break;
    case 'travel_ok':
        $reachClass = 'travel';
        $reachLabel = 'Will Travel for Services';
        $reachIcon = 'fa-car';
        break;
    default:
        $reachClass = 'local';
        $reachLabel = 'Local Services Only';
        $reachIcon = 'fa-location-dot';
}
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container-full">
    <div id="federation-profile-wrapper">

        <!-- Back to Directory -->
        <a href="<?= $basePath ?>/federation/members" class="back-link" aria-label="Return to member directory">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            Back to Federated Directory
        </a>

        <!-- Profile Card -->
        <div class="profile-card">
            <!-- Header -->
            <div class="profile-header">
                <div class="profile-avatar-container">
                    <div class="profile-avatar-ring"></div>
                    <img src="<?= htmlspecialchars($avatarUrl) ?>"
                         onerror="this.onerror=null; this.src='<?= $fallbackUrl ?>'"
                         alt="<?= htmlspecialchars($memberName) ?>"
                         class="profile-avatar-img">
                </div>

                <h1 class="profile-name"><?= htmlspecialchars($memberName) ?></h1>

                <div class="federation-badge" role="status">
                    <i class="fa-solid fa-building" aria-hidden="true"></i>
                    <?= htmlspecialchars($member['tenant_name'] ?? 'Partner Timebank') ?>
                </div>

                <div class="reach-badge <?= $reachClass ?>" role="status">
                    <i class="fa-solid <?= $reachIcon ?>" aria-hidden="true"></i>
                    <?= $reachLabel ?>
                </div>
            </div>

            <!-- Body -->
            <div class="profile-body">
                <?php if (!empty($member['bio'])): ?>
                    <section class="info-section" aria-labelledby="about-heading">
                        <h3 id="about-heading">
                            <i class="fa-solid fa-user" aria-hidden="true"></i>
                            About
                        </h3>
                        <div class="info-content">
                            <?= nl2br(htmlspecialchars($member['bio'])) ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($member['location'])): ?>
                    <section class="info-section" aria-labelledby="location-heading">
                        <h3 id="location-heading">
                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                            Location
                        </h3>
                        <div class="location-display">
                            <i class="fa-solid fa-map-marker-alt" aria-hidden="true"></i>
                            <?= htmlspecialchars($member['location']) ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($member['skills'])): ?>
                    <section class="info-section" aria-labelledby="skills-heading">
                        <h3 id="skills-heading">
                            <i class="fa-solid fa-star" aria-hidden="true"></i>
                            Skills & Services
                        </h3>
                        <div class="skills-container" role="list" aria-label="Skills">
                            <?php
                            $skills = is_array($member['skills'])
                                ? $member['skills']
                                : array_map('trim', explode(',', $member['skills']));
                            foreach ($skills as $skill):
                                if (trim($skill)):
                            ?>
                                <span class="skill-tag" role="listitem"><?= htmlspecialchars(trim($skill)) ?></span>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Trust Score -->
                <?php if ($trustScore['score'] > 0): ?>
                <div class="trust-score-section">
                    <div class="trust-score-circle">
                        <svg viewBox="0 0 80 80">
                            <defs>
                                <linearGradient id="trustGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:#00796B"/>
                                    <stop offset="100%" style="stop-color:#4db6ac"/>
                                </linearGradient>
                            </defs>
                            <circle class="trust-score-bg" cx="40" cy="40" r="36"/>
                            <circle class="trust-score-progress" cx="40" cy="40" r="36"
                                    stroke-dasharray="<?= 2 * M_PI * 36 ?>"
                                    stroke-dashoffset="<?= 2 * M_PI * 36 * (1 - $trustScore['score'] / 100) ?>"/>
                        </svg>
                        <div class="trust-score-value"><?= $trustScore['score'] ?></div>
                    </div>
                    <div class="trust-score-info">
                        <div class="trust-score-label">Trust Score</div>
                        <div class="trust-score-level">
                            <?php
                            $levelLabels = [
                                'excellent' => 'Excellent Member',
                                'trusted' => 'Trusted Member',
                                'established' => 'Established Member',
                                'growing' => 'Growing Reputation',
                                'new' => 'New Member',
                            ];
                            echo $levelLabels[$trustScore['level']] ?? 'Member';
                            ?>
                        </div>
                        <div class="trust-score-details">
                            <?php if (!empty($trustScore['details']['review_count'])): ?>
                            <span class="trust-detail">
                                <i class="fa-solid fa-star"></i>
                                <?= $trustScore['details']['review_count'] ?> reviews
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($trustScore['details']['transaction_count'])): ?>
                            <span class="trust-detail">
                                <i class="fa-solid fa-exchange-alt"></i>
                                <?= $trustScore['details']['transaction_count'] ?> exchanges
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($trustScore['details']['cross_tenant_activity'])): ?>
                            <span class="trust-detail">
                                <i class="fa-solid fa-globe"></i>
                                Federated activity
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Reviews Section -->
                <?php if ($reviewStats && $reviewStats['total'] > 0): ?>
                <section class="reviews-section" aria-labelledby="reviews-heading">
                    <div class="reviews-header">
                        <h3 id="reviews-heading" class="reviews-title">
                            <i class="fa-solid fa-comments" aria-hidden="true"></i>
                            Reviews
                        </h3>
                        <div class="reviews-stats">
                            <div class="reviews-average">
                                <span class="reviews-average-value"><?= number_format($reviewStats['average'], 1) ?></span>
                                <div class="reviews-average-stars" aria-label="<?= number_format($reviewStats['average'], 1) ?> out of 5 stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fa-solid fa-star<?= $i <= round($reviewStats['average']) ? '' : '-half-stroke' ?><?= $i > round($reviewStats['average']) ? ' star-inactive' : '' ?>" aria-hidden="true"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <span class="reviews-count"><?= $reviewStats['total'] ?> review<?= $reviewStats['total'] !== 1 ? 's' : '' ?></span>
                        </div>
                    </div>

                    <div class="reviews-list" role="list" aria-label="User reviews">
                        <?php foreach ($reviews as $review): ?>
                        <article class="review-card" role="listitem">
                            <div class="review-avatar" aria-hidden="true">
                                <?php if (!empty($review['reviewer_avatar'])): ?>
                                    <img src="<?= htmlspecialchars($review['reviewer_avatar']) ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <?= strtoupper(substr($review['reviewer_name'], 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                            <div class="review-content">
                                <div class="review-header">
                                    <div class="review-author">
                                        <span class="review-author-name"><?= htmlspecialchars($review['reviewer_name']) ?></span>
                                        <?php if ($review['is_cross_tenant']): ?>
                                            <span class="review-author-badge">
                                                <i class="fa-solid fa-globe" aria-hidden="true"></i> <?= htmlspecialchars($review['reviewer_timebank'] ?? 'Partner') ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="review-rating" aria-label="<?= $review['rating'] ?> out of 5 stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa-solid fa-star<?= $i > $review['rating'] ? ' star-inactive' : '' ?>" aria-hidden="true"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <?php if (!empty($review['comment'])): ?>
                                <p class="review-text"><?= htmlspecialchars($review['comment']) ?></p>
                                <?php endif; ?>
                                <div class="review-meta">
                                    <span><i class="fa-regular fa-clock" aria-hidden="true"></i> <?= htmlspecialchars($review['time_ago']) ?></span>
                                    <?php if ($review['has_transaction']): ?>
                                    <span><i class="fa-solid fa-check-circle" aria-hidden="true"></i> Verified exchange</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php elseif (!$reviewStats || $reviewStats['total'] === 0): ?>
                <section class="info-section" aria-labelledby="no-reviews-heading">
                    <div class="no-reviews" role="status">
                        <i class="fa-regular fa-comments" aria-hidden="true"></i>
                        <p id="no-reviews-heading">No reviews yet</p>
                        <small>Be the first to leave a review after an exchange!</small>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="action-buttons" role="group" aria-label="Member actions">
                    <?php if ($canMessage): ?>
                        <a href="<?= $basePath ?>/messages/compose?to=<?= $member['id'] ?>&federated=1" class="action-btn action-btn-primary" aria-label="Send message to <?= htmlspecialchars($memberName) ?>">
                            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                            Send Message
                        </a>
                    <?php else: ?>
                        <span class="action-btn action-btn-disabled" aria-disabled="true" aria-describedby="messaging-disabled">
                            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                            Messaging Unavailable
                        </span>
                        <span id="messaging-disabled" class="visually-hidden">Messaging not enabled for this member</span>
                    <?php endif; ?>

                    <?php if ($canTransact): ?>
                        <a href="<?= $basePath ?>/transactions/new?with=<?= $member['id'] ?>&tenant=<?= $member['tenant_id'] ?>" class="action-btn action-btn-secondary" aria-label="Start transaction with <?= htmlspecialchars($memberName) ?>">
                            <i class="fa-solid fa-exchange-alt" aria-hidden="true"></i>
                            Start Transaction
                        </a>
                    <?php else: ?>
                        <span class="action-btn action-btn-disabled" aria-disabled="true" aria-describedby="transaction-disabled">
                            <i class="fa-solid fa-exchange-alt" aria-hidden="true"></i>
                            Transactions Unavailable
                        </span>
                        <span id="transaction-disabled" class="visually-hidden">Transactions not enabled for this member</span>
                    <?php endif; ?>

                    <?php if ($pendingReviewTransaction): ?>
                        <a href="<?= $basePath ?>/federation/review/<?= $pendingReviewTransaction ?>" class="action-btn action-btn-review" aria-label="Leave a review for <?= htmlspecialchars($memberName) ?>">
                            <i class="fa-solid fa-star" aria-hidden="true"></i>
                            Leave a Review
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Privacy Notice -->
                <aside class="privacy-notice" role="note">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    <div>
                        <strong>Federated Profile</strong><br>
                        This member is from <strong><?= htmlspecialchars($member['tenant_name'] ?? 'a partner timebank') ?></strong>.
                        Only information they've chosen to share with federated partners is displayed here.
                    </div>
                </aside>
            </div>
        </div>

    </div><!-- #federation-profile-wrapper -->
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
