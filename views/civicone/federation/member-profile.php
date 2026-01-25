<?php
/**
 * Federation Member Profile
 * GOV.UK Design System (WCAG 2.1 AA)
 */
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
$pendingReviewTransaction = $pendingReviewTransaction ?? null;

$memberName = $member['name'] ?? 'Member';
$fallbackUrl = 'https://ui-avatars.com/api/?name=' . urlencode($memberName) . '&background=00703c&color=fff&size=200';
$avatarUrl = !empty($member['avatar_url']) ? $member['avatar_url'] : $fallbackUrl;

$reachLabel = '';
$reachIcon = '';
$reachColor = '#505a5f';
switch ($member['service_reach'] ?? 'local_only') {
    case 'remote_ok':
        $reachLabel = 'Offers Remote Services';
        $reachIcon = 'fa-laptop-house';
        $reachColor = '#1d70b8';
        break;
    case 'travel_ok':
        $reachLabel = 'Will Travel for Services';
        $reachIcon = 'fa-car';
        $reachColor = '#00703c';
        break;
    default:
        $reachLabel = 'Local Services Only';
        $reachIcon = 'fa-location-dot';
}
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
    <a href="<?= $basePath ?>/federation/members" class="govuk-back-link govuk-!-margin-top-4">
        Back to Federated Directory
    </a>

    <main class="govuk-main-wrapper govuk-!-padding-top-4" id="main-content" role="main">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <!-- Profile Card -->
                <div class="govuk-!-padding-6 civicone-profile-card">
                    <!-- Header -->
                    <div class="govuk-!-text-align-center govuk-!-margin-bottom-6">
                        <img src="<?= htmlspecialchars($avatarUrl) ?>"
                             onerror="this.onerror=null; this.src='<?= $fallbackUrl ?>'"
                             alt="<?= htmlspecialchars($memberName) ?>"
                             class="govuk-!-margin-bottom-4 civicone-profile-avatar--xl">

                        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2"><?= htmlspecialchars($memberName) ?></h1>

                        <div class="govuk-!-margin-bottom-2">
                            <span class="govuk-tag govuk-tag--grey">
                                <i class="fa-solid fa-building govuk-!-margin-right-1" aria-hidden="true"></i>
                                <?= htmlspecialchars($member['tenant_name'] ?? 'Partner Timebank') ?>
                            </span>
                        </div>

                        <span class="govuk-tag" style="background: <?= $reachColor ?>;">
                            <i class="fa-solid <?= $reachIcon ?> govuk-!-margin-right-1" aria-hidden="true"></i>
                            <?= $reachLabel ?>
                        </span>
                    </div>

                    <!-- About -->
                    <?php if (!empty($member['bio'])): ?>
                        <h2 class="govuk-heading-m">
                            <i class="fa-solid fa-user govuk-!-margin-right-2 civicone-secondary-text" aria-hidden="true"></i>
                            About
                        </h2>
                        <p class="govuk-body govuk-!-margin-bottom-6">
                            <?= nl2br(htmlspecialchars($member['bio'])) ?>
                        </p>
                    <?php endif; ?>

                    <!-- Location -->
                    <?php if (!empty($member['location'])): ?>
                        <h2 class="govuk-heading-m">
                            <i class="fa-solid fa-location-dot govuk-!-margin-right-2 civicone-secondary-text" aria-hidden="true"></i>
                            Location
                        </h2>
                        <p class="govuk-body govuk-!-margin-bottom-6">
                            <i class="fa-solid fa-map-marker-alt govuk-!-margin-right-1 civicone-icon-blue" aria-hidden="true"></i>
                            <?= htmlspecialchars($member['location']) ?>
                        </p>
                    <?php endif; ?>

                    <!-- Skills -->
                    <?php if (!empty($member['skills'])): ?>
                        <h2 class="govuk-heading-m">
                            <i class="fa-solid fa-star govuk-!-margin-right-2 civicone-secondary-text" aria-hidden="true"></i>
                            Skills & Services
                        </h2>
                        <div class="govuk-!-margin-bottom-6" role="list" aria-label="Skills">
                            <?php
                            $skills = is_array($member['skills'])
                                ? $member['skills']
                                : array_map('trim', explode(',', $member['skills']));
                            foreach ($skills as $skill):
                                if (trim($skill)):
                            ?>
                                <span class="govuk-tag govuk-tag--light-blue govuk-!-margin-right-1 govuk-!-margin-bottom-1" role="listitem">
                                    <?= htmlspecialchars(trim($skill)) ?>
                                </span>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Trust Score -->
                    <?php if ($trustScore['score'] > 0): ?>
                        <div class="govuk-!-padding-4 govuk-!-margin-bottom-6 civicone-panel-bg civicone-border-left-green">
                            <div class="civicone-trust-panel">
                                <div class="civicone-trust-score">
                                    <p class="govuk-heading-xl govuk-!-margin-bottom-0 civicone-heading-green">
                                        <?= $trustScore['score'] ?>
                                    </p>
                                    <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">/100</p>
                                </div>
                                <div>
                                    <p class="govuk-body-l govuk-!-font-weight-bold govuk-!-margin-bottom-1">Trust Score</p>
                                    <p class="govuk-body govuk-!-margin-bottom-2 civicone-text-success">
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
                                    </p>
                                    <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">
                                        <?php if (!empty($trustScore['details']['review_count'])): ?>
                                            <i class="fa-solid fa-star govuk-!-margin-right-1" aria-hidden="true"></i>
                                            <?= $trustScore['details']['review_count'] ?> reviews
                                        <?php endif; ?>
                                        <?php if (!empty($trustScore['details']['transaction_count'])): ?>
                                            <span class="govuk-!-margin-left-2">
                                                <i class="fa-solid fa-exchange-alt govuk-!-margin-right-1" aria-hidden="true"></i>
                                                <?= $trustScore['details']['transaction_count'] ?> exchanges
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($trustScore['details']['cross_tenant_activity'])): ?>
                                            <span class="govuk-!-margin-left-2">
                                                <i class="fa-solid fa-globe govuk-!-margin-right-1" aria-hidden="true"></i>
                                                Federated activity
                                            </span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Reviews Section -->
                    <?php if ($reviewStats && $reviewStats['total'] > 0): ?>
                        <h2 class="govuk-heading-m">
                            <i class="fa-solid fa-comments govuk-!-margin-right-2 civicone-secondary-text" aria-hidden="true"></i>
                            Reviews
                            <span class="govuk-tag govuk-tag--light-blue govuk-!-margin-left-2">
                                <?= number_format($reviewStats['average'], 1) ?> / 5
                            </span>
                            <span class="govuk-body-s govuk-!-margin-left-2 civicone-secondary-text">
                                (<?= $reviewStats['total'] ?> review<?= $reviewStats['total'] !== 1 ? 's' : '' ?>)
                            </span>
                        </h2>

                        <div role="list" aria-label="User reviews">
                            <?php foreach ($reviews as $review): ?>
                            <article class="govuk-!-padding-4 govuk-!-margin-bottom-4 civicone-panel-bg" role="listitem">
                                <div class="civicone-review-item">
                                    <div class="civicone-review-avatar">
                                        <?php if (!empty($review['reviewer_avatar'])): ?>
                                            <img src="<?= htmlspecialchars($review['reviewer_avatar']) ?>" alt="" loading="lazy">
                                        <?php else: ?>
                                            <?= strtoupper(substr($review['reviewer_name'], 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="civicone-review-content">
                                        <p class="govuk-body govuk-!-font-weight-bold govuk-!-margin-bottom-1">
                                            <?= htmlspecialchars($review['reviewer_name']) ?>
                                            <?php if ($review['is_cross_tenant']): ?>
                                                <span class="govuk-tag govuk-tag--grey civicone-text-tiny">
                                                    <i class="fa-solid fa-globe" aria-hidden="true"></i>
                                                    <?= htmlspecialchars($review['reviewer_timebank'] ?? 'Partner') ?>
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="govuk-body-s govuk-!-margin-bottom-2">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fa-solid fa-star <?= $i > $review['rating'] ? 'civicone-star-empty' : 'civicone-star-filled' ?>" aria-hidden="true"></i>
                                            <?php endfor; ?>
                                        </p>
                                        <?php if (!empty($review['comment'])): ?>
                                            <p class="govuk-body govuk-!-margin-bottom-2"><?= htmlspecialchars($review['comment']) ?></p>
                                        <?php endif; ?>
                                        <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">
                                            <i class="fa-regular fa-clock govuk-!-margin-right-1" aria-hidden="true"></i>
                                            <?= htmlspecialchars($review['time_ago']) ?>
                                            <?php if ($review['has_transaction']): ?>
                                                <span class="govuk-!-margin-left-2">
                                                    <i class="fa-solid fa-check-circle civicone-verified-check" aria-hidden="true"></i>
                                                    Verified exchange
                                                </span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif (!$reviewStats || $reviewStats['total'] === 0): ?>
                        <div class="govuk-!-padding-6 govuk-!-text-align-center govuk-!-margin-bottom-6 civicone-panel-bg">
                            <i class="fa-regular fa-comments fa-2x govuk-!-margin-bottom-2 civicone-secondary-text" aria-hidden="true"></i>
                            <p class="govuk-body govuk-!-margin-bottom-1">No reviews yet</p>
                            <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">
                                Be the first to leave a review after an exchange!
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="govuk-button-group">
                        <?php if ($canMessage): ?>
                            <a href="<?= $basePath ?>/messages/compose?to=<?= $member['id'] ?>&federated=1" class="govuk-button" data-module="govuk-button">
                                <i class="fa-solid fa-envelope govuk-!-margin-right-2" aria-hidden="true"></i>
                                Send Message
                            </a>
                        <?php else: ?>
                            <span class="govuk-button govuk-button--disabled" aria-disabled="true">
                                <i class="fa-solid fa-envelope govuk-!-margin-right-2" aria-hidden="true"></i>
                                Messaging Unavailable
                            </span>
                        <?php endif; ?>

                        <?php if ($canTransact): ?>
                            <a href="<?= $basePath ?>/transactions/new?with=<?= $member['id'] ?>&tenant=<?= $member['tenant_id'] ?>" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                                <i class="fa-solid fa-exchange-alt govuk-!-margin-right-2" aria-hidden="true"></i>
                                Start Transaction
                            </a>
                        <?php else: ?>
                            <span class="govuk-button govuk-button--secondary govuk-button--disabled" aria-disabled="true">
                                <i class="fa-solid fa-exchange-alt govuk-!-margin-right-2" aria-hidden="true"></i>
                                Transactions Unavailable
                            </span>
                        <?php endif; ?>

                        <?php if ($pendingReviewTransaction): ?>
                            <a href="<?= $basePath ?>/federation/review/<?= $pendingReviewTransaction ?>" class="govuk-button civicone-btn-orange" data-module="govuk-button">
                                <i class="fa-solid fa-star govuk-!-margin-right-2" aria-hidden="true"></i>
                                Leave a Review
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Privacy Notice -->
                <div class="govuk-inset-text govuk-!-margin-top-6">
                    <p class="govuk-body govuk-!-margin-bottom-0">
                        <i class="fa-solid fa-shield-halved govuk-!-margin-right-2 civicone-icon-blue" aria-hidden="true"></i>
                        <strong>Federated Profile</strong> — This member is from <strong><?= htmlspecialchars($member['tenant_name'] ?? 'a partner timebank') ?></strong>.
                        Only information they've chosen to share with federated partners is displayed here.
                    </p>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Offline indicator handled by civicone-common.js -->

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
