<?php
/**
 * Federation Member Profile
 * CivicOne Theme - WCAG 2.1 AA Compliant
 */
$pageTitle = $pageTitle ?? "Member Profile";
$hideHero = true;
$bodyClass = 'civicone--federation';

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
$pendingReviewTransaction = $pendingReviewTransaction ?? null;

$memberName = $member['name'] ?? 'Member';
$fallbackUrl = 'https://ui-avatars.com/api/?name=' . urlencode($memberName) . '&background=00796B&color=fff&size=200';
$avatarUrl = !empty($member['avatar_url']) ? $member['avatar_url'] : $fallbackUrl;

$reachClass = '';
$reachLabel = '';
$reachIcon = '';
switch ($member['service_reach'] ?? 'local_only') {
    case 'remote_ok':
        $reachClass = 'civic-fed-reach--remote';
        $reachLabel = 'Offers Remote Services';
        $reachIcon = 'fa-laptop-house';
        break;
    case 'travel_ok':
        $reachClass = 'civic-fed-reach--travel';
        $reachLabel = 'Will Travel for Services';
        $reachIcon = 'fa-car';
        break;
    default:
        $reachClass = 'civic-fed-reach--local';
        $reachLabel = 'Local Services Only';
        $reachIcon = 'fa-location-dot';
}
?>

<!-- Offline Banner -->
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civic-container">
    <!-- Back Link -->
    <a href="<?= $basePath ?>/federation/members" class="civic-fed-back-link" aria-label="Return to member directory">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Back to Federated Directory
    </a>

    <!-- Profile Card -->
    <div class="civic-fed-profile-card">
        <!-- Header -->
        <div class="civic-fed-profile-header">
            <div class="civic-fed-avatar civic-fed-avatar--large">
                <img src="<?= htmlspecialchars($avatarUrl) ?>"
                     onerror="this.onerror=null; this.src='<?= $fallbackUrl ?>'"
                     alt="<?= htmlspecialchars($memberName) ?>">
            </div>

            <h1 class="civic-fed-profile-name"><?= htmlspecialchars($memberName) ?></h1>

            <div class="civic-fed-badge civic-fed-badge--partner">
                <i class="fa-solid fa-building" aria-hidden="true"></i>
                <?= htmlspecialchars($member['tenant_name'] ?? 'Partner Timebank') ?>
            </div>

            <div class="civic-fed-badge <?= $reachClass ?>">
                <i class="fa-solid <?= $reachIcon ?>" aria-hidden="true"></i>
                <?= $reachLabel ?>
            </div>
        </div>

        <!-- Body -->
        <div class="civic-fed-profile-body">
            <?php if (!empty($member['bio'])): ?>
                <section class="civic-fed-section" aria-labelledby="about-heading">
                    <h3 id="about-heading" class="civic-fed-section-title">
                        <i class="fa-solid fa-user" aria-hidden="true"></i>
                        About
                    </h3>
                    <div class="civic-fed-content">
                        <?= nl2br(htmlspecialchars($member['bio'])) ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($member['location'])): ?>
                <section class="civic-fed-section" aria-labelledby="location-heading">
                    <h3 id="location-heading" class="civic-fed-section-title">
                        <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                        Location
                    </h3>
                    <div class="civic-fed-location">
                        <i class="fa-solid fa-map-marker-alt" aria-hidden="true"></i>
                        <?= htmlspecialchars($member['location']) ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($member['skills'])): ?>
                <section class="civic-fed-section" aria-labelledby="skills-heading">
                    <h3 id="skills-heading" class="civic-fed-section-title">
                        <i class="fa-solid fa-star" aria-hidden="true"></i>
                        Skills & Services
                    </h3>
                    <div class="civic-fed-tags" role="list" aria-label="Skills">
                        <?php
                        $skills = is_array($member['skills'])
                            ? $member['skills']
                            : array_map('trim', explode(',', $member['skills']));
                        foreach ($skills as $skill):
                            if (trim($skill)):
                        ?>
                            <span class="civic-fed-tag" role="listitem"><?= htmlspecialchars(trim($skill)) ?></span>
                        <?php
                            endif;
                        endforeach;
                        ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Trust Score -->
            <?php if ($trustScore['score'] > 0): ?>
            <section class="civic-fed-trust-section" aria-labelledby="trust-heading">
                <h3 id="trust-heading" class="visually-hidden">Trust Score</h3>
                <div class="civic-fed-trust-display">
                    <div class="civic-fed-trust-score">
                        <span class="civic-fed-trust-value"><?= $trustScore['score'] ?></span>
                        <span class="civic-fed-trust-max">/100</span>
                    </div>
                    <div class="civic-fed-trust-info">
                        <div class="civic-fed-trust-label">Trust Score</div>
                        <div class="civic-fed-trust-level">
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
                        <div class="civic-fed-trust-details">
                            <?php if (!empty($trustScore['details']['review_count'])): ?>
                            <span class="civic-fed-trust-detail">
                                <i class="fa-solid fa-star" aria-hidden="true"></i>
                                <?= $trustScore['details']['review_count'] ?> reviews
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($trustScore['details']['transaction_count'])): ?>
                            <span class="civic-fed-trust-detail">
                                <i class="fa-solid fa-exchange-alt" aria-hidden="true"></i>
                                <?= $trustScore['details']['transaction_count'] ?> exchanges
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($trustScore['details']['cross_tenant_activity'])): ?>
                            <span class="civic-fed-trust-detail">
                                <i class="fa-solid fa-globe" aria-hidden="true"></i>
                                Federated activity
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Reviews Section -->
            <?php if ($reviewStats && $reviewStats['total'] > 0): ?>
            <section class="civic-fed-reviews-section" aria-labelledby="reviews-heading">
                <div class="civic-fed-reviews-header">
                    <h3 id="reviews-heading" class="civic-fed-section-title">
                        <i class="fa-solid fa-comments" aria-hidden="true"></i>
                        Reviews
                    </h3>
                    <div class="civic-fed-reviews-stats">
                        <span class="civic-fed-reviews-average"><?= number_format($reviewStats['average'], 1) ?></span>
                        <div class="civic-fed-stars" aria-label="<?= number_format($reviewStats['average'], 1) ?> out of 5 stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fa-solid fa-star<?= $i > round($reviewStats['average']) ? ' civic-fed-star--inactive' : '' ?>" aria-hidden="true"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="civic-fed-reviews-count"><?= $reviewStats['total'] ?> review<?= $reviewStats['total'] !== 1 ? 's' : '' ?></span>
                    </div>
                </div>

                <div class="civic-fed-reviews-list" role="list" aria-label="User reviews">
                    <?php foreach ($reviews as $review): ?>
                    <article class="civic-fed-review-card" role="listitem">
                        <div class="civic-fed-review-avatar" aria-hidden="true">
                            <?php if (!empty($review['reviewer_avatar'])): ?>
                                <img src="<?= htmlspecialchars($review['reviewer_avatar']) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <?= strtoupper(substr($review['reviewer_name'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="civic-fed-review-content">
                            <div class="civic-fed-review-header">
                                <div class="civic-fed-review-author">
                                    <span class="civic-fed-review-name"><?= htmlspecialchars($review['reviewer_name']) ?></span>
                                    <?php if ($review['is_cross_tenant']): ?>
                                        <span class="civic-fed-badge civic-fed-badge--small">
                                            <i class="fa-solid fa-globe" aria-hidden="true"></i> <?= htmlspecialchars($review['reviewer_timebank'] ?? 'Partner') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="civic-fed-stars civic-fed-stars--small" aria-label="<?= $review['rating'] ?> out of 5 stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fa-solid fa-star<?= $i > $review['rating'] ? ' civic-fed-star--inactive' : '' ?>" aria-hidden="true"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <?php if (!empty($review['comment'])): ?>
                            <p class="civic-fed-review-text"><?= htmlspecialchars($review['comment']) ?></p>
                            <?php endif; ?>
                            <div class="civic-fed-review-meta">
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
            <section class="civic-fed-section" aria-labelledby="no-reviews-heading">
                <div class="civic-fed-empty civic-fed-empty--compact" role="status">
                    <i class="fa-regular fa-comments" aria-hidden="true"></i>
                    <p id="no-reviews-heading">No reviews yet</p>
                    <small>Be the first to leave a review after an exchange!</small>
                </div>
            </section>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="civic-fed-actions" role="group" aria-label="Member actions">
                <?php if ($canMessage): ?>
                    <a href="<?= $basePath ?>/messages/compose?to=<?= $member['id'] ?>&federated=1" class="civic-fed-btn civic-fed-btn--primary" aria-label="Send message to <?= htmlspecialchars($memberName) ?>">
                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                        Send Message
                    </a>
                <?php else: ?>
                    <span class="civic-fed-btn civic-fed-btn--disabled" aria-disabled="true" aria-describedby="messaging-disabled">
                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                        Messaging Unavailable
                    </span>
                    <span id="messaging-disabled" class="visually-hidden">Messaging not enabled for this member</span>
                <?php endif; ?>

                <?php if ($canTransact): ?>
                    <a href="<?= $basePath ?>/transactions/new?with=<?= $member['id'] ?>&tenant=<?= $member['tenant_id'] ?>" class="civic-fed-btn civic-fed-btn--secondary" aria-label="Start transaction with <?= htmlspecialchars($memberName) ?>">
                        <i class="fa-solid fa-exchange-alt" aria-hidden="true"></i>
                        Start Transaction
                    </a>
                <?php else: ?>
                    <span class="civic-fed-btn civic-fed-btn--disabled" aria-disabled="true" aria-describedby="transaction-disabled">
                        <i class="fa-solid fa-exchange-alt" aria-hidden="true"></i>
                        Transactions Unavailable
                    </span>
                    <span id="transaction-disabled" class="visually-hidden">Transactions not enabled for this member</span>
                <?php endif; ?>

                <?php if ($pendingReviewTransaction): ?>
                    <a href="<?= $basePath ?>/federation/review/<?= $pendingReviewTransaction ?>" class="civic-fed-btn civic-fed-btn--accent" aria-label="Leave a review for <?= htmlspecialchars($memberName) ?>">
                        <i class="fa-solid fa-star" aria-hidden="true"></i>
                        Leave a Review
                    </a>
                <?php endif; ?>
            </div>

            <!-- Privacy Notice -->
            <aside class="civic-fed-notice" role="note">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                <div>
                    <strong>Federated Profile</strong><br>
                    This member is from <strong><?= htmlspecialchars($member['tenant_name'] ?? 'a partner timebank') ?></strong>.
                    Only information they've chosen to share with federated partners is displayed here.
                </div>
            </aside>
        </div>
    </div>
</div>

<!-- Federation offline indicator -->
<script src="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/js/civicone-federation-offline.min.js" defer></script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
