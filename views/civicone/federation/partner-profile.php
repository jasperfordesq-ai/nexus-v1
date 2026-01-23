<?php
/**
 * Federation Partner Profile
 * CivicOne Theme - WCAG 2.1 AA Compliant
 * Template: Detail Page with GOV.UK patterns
 */
$pageTitle = $pageTitle ?? "Partner Timebank";
$hideHero = true;
$bodyClass = 'civicone--federation';
$currentPage = 'hub';

\Nexus\Core\SEO::setTitle(($partner['name'] ?? 'Partner') . ' - Partner Timebank Profile');
\Nexus\Core\SEO::setDescription('View details and available features from ' . ($partner['name'] ?? 'partner timebank'));

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();

// Extract data passed from controller
$partner = $partner ?? [];
$partnership = $partnership ?? [];
$features = $features ?? [];
$stats = $stats ?? [];
$recentActivity = $recentActivity ?? [];
$partnershipSince = $partnershipSince ?? null;
$userOptedIn = $userOptedIn ?? false;
$partnerCommunities = $partnerCommunities ?? [];
$currentScope = $currentScope ?? 'all';

// Format partnership date
$partnerSinceFormatted = $partnershipSince ? date('F Y', strtotime($partnershipSince)) : 'Unknown';

// Count enabled features
$enabledFeatureCount = count(array_filter($features));
?>

<!-- Federation Scope Switcher (only if user has 2+ communities) -->
<?php if (count($partnerCommunities) >= 2): ?>
    <?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/federation-scope-switcher.php'; ?>
<?php endif; ?>

<!-- Federation Service Navigation -->
<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/federation-service-navigation.php'; ?>

<div class="civicone-width-container">
    <main class="civicone-main-wrapper">
        <!-- Back Link -->
        <a href="<?= $basePath ?>/federation" class="govuk-back-link">Back to Partner Timebanks</a>

        <!-- Partner Header Card -->
        <div class="govuk-panel govuk-panel--confirmation">
            <h1 class="govuk-panel__title" id="partner-name">
                <?= htmlspecialchars($partner['name']) ?>
            </h1>
            <div class="govuk-panel__body">
                <span class="govuk-tag govuk-tag--green">Active Partnership</span>
            </div>
        </div>

        <!-- Partner Information Summary -->
        <dl class="govuk-summary-list govuk-!-margin-bottom-6">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">
                    Partnership status
                </dt>
                <dd class="govuk-summary-list__value">
                    Partner since <?= $partnerSinceFormatted ?>
                </dd>
            </div>
            <?php if (!empty($partner['domain'])): ?>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">
                    Website
                </dt>
                <dd class="govuk-summary-list__value">
                    <?= htmlspecialchars($partner['domain']) ?>
                </dd>
            </div>
            <?php endif; ?>
            <?php if (!empty($partner['description'])): ?>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">
                    About
                </dt>
                <dd class="govuk-summary-list__value">
                    <?= htmlspecialchars($partner['description']) ?>
                </dd>
            </div>
            <?php endif; ?>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">
                    Features enabled
                </dt>
                <dd class="govuk-summary-list__value">
                    <strong><?= $enabledFeatureCount ?></strong> out of 6 features
                </dd>
            </div>
        </dl>

        <!-- Stats Grid -->
        <h2 class="govuk-heading-m">Statistics</h2>
        <div class="govuk-grid-row" role="region" aria-label="Partner statistics">
            <?php if ($features['members']): ?>
            <div class="govuk-grid-column-one-third">
                <div class="civic-fed-stat-card">
                    <div class="civic-fed-stat-icon" aria-hidden="true">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="civic-fed-stat-content">
                        <div class="civic-fed-stat-value"><?= number_format($stats['members']) ?></div>
                        <div class="civic-fed-stat-label">Members</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($features['listings']): ?>
            <div class="govuk-grid-column-one-third">
                <div class="civic-fed-stat-card">
                    <div class="civic-fed-stat-icon" aria-hidden="true">
                        <i class="fa-solid fa-hand-holding-heart"></i>
                    </div>
                    <div class="civic-fed-stat-content">
                        <div class="civic-fed-stat-value"><?= number_format($stats['listings']) ?></div>
                        <div class="civic-fed-stat-label">Listings</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($features['events']): ?>
            <div class="govuk-grid-column-one-third">
                <div class="civic-fed-stat-card">
                    <div class="civic-fed-stat-icon" aria-hidden="true">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    <div class="civic-fed-stat-content">
                        <div class="civic-fed-stat-value"><?= number_format($stats['events']) ?></div>
                        <div class="civic-fed-stat-label">Upcoming Events</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($features['groups']): ?>
            <div class="govuk-grid-column-one-third">
                <div class="civic-fed-stat-card">
                    <div class="civic-fed-stat-icon" aria-hidden="true">
                        <i class="fa-solid fa-people-group"></i>
                    </div>
                    <div class="civic-fed-stat-content">
                        <div class="civic-fed-stat-value"><?= number_format($stats['groups']) ?></div>
                        <div class="civic-fed-stat-label">Groups</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($features['transactions']): ?>
            <div class="govuk-grid-column-one-third">
                <div class="civic-fed-stat-card">
                    <div class="civic-fed-stat-icon" aria-hidden="true">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div class="civic-fed-stat-content">
                        <div class="civic-fed-stat-value"><?= number_format($stats['total_hours_exchanged'], 1) ?></div>
                        <div class="civic-fed-stat-label">Hours Exchanged</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Available Features -->
        <section aria-labelledby="features-heading">
            <h2 id="features-heading" class="govuk-heading-m">
                Available Features
            </h2>
            <ul class="govuk-list">
                <li class="govuk-!-margin-bottom-4">
                <a href="<?= $basePath ?>/federation/members?tenant=<?= $partner['id'] ?>" class="civic-fed-feature-item <?= !$features['members'] ? 'civic-fed-feature-item--disabled' : '' ?>" <?= !$features['members'] ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                    <div class="civic-fed-feature-icon" aria-hidden="true">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="civic-fed-feature-info">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">Browse Members</h3>
                        <p class="govuk-body-s govuk-!-margin-bottom-0">View profiles from this timebank</p>
                    </div>
                    <span class="civic-fed-feature-status <?= $features['members'] ? 'civic-fed-feature-status--enabled' : 'civic-fed-feature-status--disabled' ?>">
                        <?= $features['members'] ? 'Enabled' : 'Disabled' ?>
                    </span>
                </a>
                </li>

                <li class="govuk-!-margin-bottom-4">
                <a href="<?= $basePath ?>/federation/listings?tenant=<?= $partner['id'] ?>" class="civic-fed-feature-item <?= !$features['listings'] ? 'civic-fed-feature-item--disabled' : '' ?>" <?= !$features['listings'] ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                    <div class="civic-fed-feature-icon" aria-hidden="true">
                        <i class="fa-solid fa-hand-holding-heart"></i>
                    </div>
                    <div class="civic-fed-feature-info">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">Browse Listings</h3>
                        <p class="govuk-body-s govuk-!-margin-bottom-0">Offers & requests</p>
                    </div>
                    <span class="civic-fed-feature-status <?= $features['listings'] ? 'civic-fed-feature-status--enabled' : 'civic-fed-feature-status--disabled' ?>">
                        <?= $features['listings'] ? 'Enabled' : 'Disabled' ?>
                    </span>
                </a>
                </li>

                <li class="govuk-!-margin-bottom-4">
                <a href="<?= $basePath ?>/federation/events?tenant=<?= $partner['id'] ?>" class="civic-fed-feature-item <?= !$features['events'] ? 'civic-fed-feature-item--disabled' : '' ?>" <?= !$features['events'] ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                    <div class="civic-fed-feature-icon" aria-hidden="true">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    <div class="civic-fed-feature-info">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">Browse Events</h3>
                        <p class="govuk-body-s govuk-!-margin-bottom-0">Upcoming events to join</p>
                    </div>
                    <span class="civic-fed-feature-status <?= $features['events'] ? 'civic-fed-feature-status--enabled' : 'civic-fed-feature-status--disabled' ?>">
                        <?= $features['events'] ? 'Enabled' : 'Disabled' ?>
                    </span>
                </a>
                </li>

                <li class="govuk-!-margin-bottom-4">
                <a href="<?= $basePath ?>/federation/groups?tenant=<?= $partner['id'] ?>" class="civic-fed-feature-item <?= !$features['groups'] ? 'civic-fed-feature-item--disabled' : '' ?>" <?= !$features['groups'] ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                    <div class="civic-fed-feature-icon" aria-hidden="true">
                        <i class="fa-solid fa-people-group"></i>
                    </div>
                    <div class="civic-fed-feature-info">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">Browse Groups</h3>
                        <p class="govuk-body-s govuk-!-margin-bottom-0">Interest groups to join</p>
                    </div>
                    <span class="civic-fed-feature-status <?= $features['groups'] ? 'civic-fed-feature-status--enabled' : 'civic-fed-feature-status--disabled' ?>">
                        <?= $features['groups'] ? 'Enabled' : 'Disabled' ?>
                    </span>
                </a>
                </li>

                <li class="govuk-!-margin-bottom-4">
                <div class="civic-fed-feature-item <?= !$features['messaging'] ? 'civic-fed-feature-item--disabled' : '' ?>">
                    <div class="civic-fed-feature-icon" aria-hidden="true">
                        <i class="fa-solid fa-comments"></i>
                    </div>
                    <div class="civic-fed-feature-info">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">Cross-Messaging</h3>
                        <p class="govuk-body-s govuk-!-margin-bottom-0">Message members directly</p>
                    </div>
                    <span class="civic-fed-feature-status <?= $features['messaging'] ? 'civic-fed-feature-status--enabled' : 'civic-fed-feature-status--disabled' ?>">
                        <?= $features['messaging'] ? 'Enabled' : 'Disabled' ?>
                    </span>
                </div>
                </li>

                <li class="govuk-!-margin-bottom-4">
                <div class="civic-fed-feature-item <?= !$features['transactions'] ? 'civic-fed-feature-item--disabled' : '' ?>">
                    <div class="civic-fed-feature-icon" aria-hidden="true">
                        <i class="fa-solid fa-exchange-alt"></i>
                    </div>
                    <div class="civic-fed-feature-info">
                        <h3 class="govuk-heading-s govuk-!-margin-bottom-1">Hour Exchanges</h3>
                        <p class="govuk-body-s govuk-!-margin-bottom-0">Exchange time credits</p>
                    </div>
                    <span class="civic-fed-feature-status <?= $features['transactions'] ? 'civic-fed-feature-status--enabled' : 'civic-fed-feature-status--disabled' ?>">
                        <?= $features['transactions'] ? 'Enabled' : 'Disabled' ?>
                    </span>
                </div>
                </li>
            </ul>
        </section>

        <!-- Recent Activity with Partner -->
        <section aria-labelledby="activity-heading">
            <h2 id="activity-heading" class="govuk-heading-m">
                Your Recent Activity
            </h2>
            <?php if (!empty($recentActivity)): ?>
            <ul class="govuk-list" aria-label="Recent activity">
                <?php foreach ($recentActivity as $activity): ?>
                <li class="govuk-!-margin-bottom-3">
                <div class="civic-fed-activity-item">
                    <div class="civic-fed-activity-icon" aria-hidden="true">
                        <i class="fa-solid <?= htmlspecialchars($activity['icon']) ?>"></i>
                    </div>
                    <div class="civic-fed-activity-content">
                        <p class="civic-fed-activity-text"><?= htmlspecialchars($activity['description']) ?></p>
                        <span class="civic-fed-activity-time">
                            <time datetime="<?= date('c', strtotime($activity['date'])) ?>"><?= date('M j, Y', strtotime($activity['date'])) ?></time>
                        </span>
                    </div>
                </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <div class="govuk-inset-text" role="status">
                <p class="govuk-body">No recent activity with this partner yet.</p>
                <p class="govuk-body-s">Start by browsing their members or listings!</p>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="govuk-button-group" role="group" aria-label="Quick actions">
                <?php if ($features['members']): ?>
                <a href="<?= $basePath ?>/federation/members?tenant=<?= $partner['id'] ?>" class="govuk-button">
                    Browse Members
                </a>
                <?php endif; ?>

                <?php if ($features['listings']): ?>
                <a href="<?= $basePath ?>/federation/listings?tenant=<?= $partner['id'] ?>" class="govuk-button govuk-button--secondary">
                    Browse Listings
                </a>
                <?php endif; ?>

                <?php if ($features['events']): ?>
                <a href="<?= $basePath ?>/federation/events?tenant=<?= $partner['id'] ?>" class="govuk-button govuk-button--secondary">
                    View Events
                </a>
                <?php endif; ?>
            </div>
        </section>

    </main>
</div>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
