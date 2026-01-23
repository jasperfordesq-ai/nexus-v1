<?php
/**
 * Federation Hub - Partner Timebanks Landing Page
 * CivicOne Theme - WCAG 2.1 AA Compliant
 * Template: Hub/Dashboard
 */
$pageTitle = $pageTitle ?? "Partner Timebanks";
$pageSubtitle = "Connect across timebank communities";
$hideHero = true;
$bodyClass = 'civicone--federation';
$currentPage = 'hub';

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = $basePath ?? Nexus\Core\TenantContext::getBasePath();

// Extract data passed from controller
$isGuest = $isGuest ?? false;
$userOptedIn = $userOptedIn ?? false;
$partnerCount = $partnerCount ?? 0;
$partnerTenants = $partnerTenants ?? [];
$features = $features ?? [];
$stats = $stats ?? [];
$partnerCommunities = $partnerCommunities ?? $partnerTenants;
$currentScope = $currentScope ?? 'all';
?>

<!-- Federation Scope Switcher (only if user has 2+ communities) -->
<?php if (count($partnerCommunities) >= 2): ?>
    <?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/federation-scope-switcher.php'; ?>
<?php endif; ?>

<!-- Federation Service Navigation -->
<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/federation-service-navigation.php'; ?>

<!-- Offline Banner -->
<div class="civic-fed-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="civicone-width-container">
    <main class="civicone-main-wrapper">
        <!-- Page Header -->
        <h1 class="govuk-heading-xl">Partner Communities</h1>

        <p class="govuk-body-l">
            Connect with members, browse listings, attend events, and join groups from our partner timebank communities.
        </p>

        <!-- Current Scope Display -->
        <?php if (count($partnerCommunities) >= 2): ?>
        <div class="govuk-inset-text">
            <p>
                <strong>Currently viewing:</strong>
                <?php if ($currentScope === 'all'): ?>
                    All <?= $partnerCount ?> partner communit<?= $partnerCount !== 1 ? 'ies' : 'y' ?>
                <?php else: ?>
                    <?php
                    $selectedCommunity = null;
                    foreach ($partnerCommunities as $p) {
                        if ($p['id'] == $currentScope) {
                            $selectedCommunity = $p;
                            break;
                        }
                    }
                    ?>
                    <?= $selectedCommunity ? htmlspecialchars($selectedCommunity['name']) : 'Selected community' ?>
                <?php endif; ?>
                <br>
                <a href="#" class="govuk-link" onclick="document.querySelector('.moj-organisation-switcher').scrollIntoView({behavior: 'smooth'}); return false;">Change scope</a>
            </p>
        </div>
        <?php endif; ?>

        <!-- Guest Login Notice -->
        <?php if ($isGuest): ?>
        <div class="govuk-notification-banner" role="region" aria-labelledby="govuk-notification-banner-title" data-module="govuk-notification-banner">
            <div class="govuk-notification-banner__header">
                <h2 class="govuk-notification-banner__title" id="govuk-notification-banner-title">
                    Log in required
                </h2>
            </div>
            <div class="govuk-notification-banner__content">
                <p class="govuk-notification-banner__heading">
                    Log in to access partner communities
                </p>
                <p class="govuk-body">
                    Create an account or log in to browse members, listings, events, and groups from our partner timebank communities.
                </p>
                <a href="<?= $basePath ?>/login" role="button" draggable="false" class="govuk-button" data-module="govuk-button">
                    Log in
                </a>
                <a href="<?= $basePath ?>/register" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                    Create account
                </a>
            </div>
        </div>
        <?php elseif (!$userOptedIn): ?>
        <!-- Opt-In Notice (if not opted in) -->
        <div class="govuk-warning-text">
            <span class="govuk-warning-text__icon" aria-hidden="true">!</span>
            <strong class="govuk-warning-text__text">
                <span class="govuk-warning-text__assistive">Warning</span>
                You can browse partner communities, but to be visible to their members and share your own content,
                you need to <a href="<?= $basePath ?>/federation/onboarding" class="govuk-link">enable federation</a>.
            </strong>
        </div>
        <?php endif; ?>

        <!-- What is Shared - Guidance -->
        <div class="govuk-inset-text">
            <h2 class="govuk-heading-s">What is shared across communities?</h2>
            <p class="govuk-body-s">
                Content you see here comes from partner timebank communities you're connected to.
                Every item shows its source community for transparency and trust.
                You control what you share in <a href="<?= $basePath ?>/federation/settings" class="govuk-link">federation settings</a>.
            </p>
        </div>

        <!-- Federation Modules -->
        <h2 class="govuk-heading-m">Explore partner communities</h2>

        <ul class="govuk-list">
            <?php if ($features['members']): ?>
            <li>
                <a href="<?= $basePath ?>/federation/members" class="govuk-link govuk-!-font-weight-bold">Members</a>
                <?php if ($stats['members'] > 0): ?>
                    <span class="govuk-body-s">(<?= number_format($stats['members']) ?> available)</span>
                <?php endif; ?>
                <p class="govuk-body-s govuk-!-margin-top-1">Browse profiles and connect with members from partner timebanks.</p>
            </li>
            <?php endif; ?>

            <?php if ($features['listings']): ?>
            <li>
                <a href="<?= $basePath ?>/federation/listings" class="govuk-link govuk-!-font-weight-bold">Listings</a>
                <?php if ($stats['listings'] > 0): ?>
                    <span class="govuk-body-s">(<?= number_format($stats['listings']) ?> available)</span>
                <?php endif; ?>
                <p class="govuk-body-s govuk-!-margin-top-1">Discover offers and requests from partner communities.</p>
            </li>
            <?php endif; ?>

            <?php if ($features['events']): ?>
            <li>
                <a href="<?= $basePath ?>/federation/events" class="govuk-link govuk-!-font-weight-bold">Events</a>
                <?php if ($stats['events'] > 0): ?>
                    <span class="govuk-body-s">(<?= number_format($stats['events']) ?> upcoming)</span>
                <?php endif; ?>
                <p class="govuk-body-s govuk-!-margin-top-1">Find and attend events hosted by partner timebanks.</p>
            </li>
            <?php endif; ?>

            <?php if ($features['groups']): ?>
            <li>
                <a href="<?= $basePath ?>/federation/groups" class="govuk-link govuk-!-font-weight-bold">Groups</a>
                <?php if ($stats['groups'] > 0): ?>
                    <span class="govuk-body-s">(<?= number_format($stats['groups']) ?> available)</span>
                <?php endif; ?>
                <p class="govuk-body-s govuk-!-margin-top-1">Join interest groups and hubs from partner communities.</p>
            </li>
            <?php endif; ?>

            <?php if ($features['messages']): ?>
            <li>
                <a href="<?= $basePath ?>/federation/messages" class="govuk-link govuk-!-font-weight-bold">Messages</a>
                <p class="govuk-body-s govuk-!-margin-top-1">Send and receive messages with partner timebank members.</p>
            </li>
            <?php endif; ?>

            <?php if ($features['transactions']): ?>
            <li>
                <a href="<?= $basePath ?>/federation/transactions" class="govuk-link govuk-!-font-weight-bold">Transactions</a>
                <p class="govuk-body-s govuk-!-margin-top-1">Exchange time credits with partner timebank members.</p>
            </li>
            <?php endif; ?>
        </ul>

        <!-- Partner Communities List -->
        <h2 class="govuk-heading-m">Partner communities (<?= $partnerCount ?>)</h2>

        <?php if (!empty($partnerTenants)): ?>
        <ul class="govuk-list govuk-list--bullet">
            <?php foreach ($partnerTenants as $partner): ?>
            <li>
                <a href="<?= $basePath ?>/federation/partners/<?= $partner['id'] ?>" class="govuk-link">
                    <?= htmlspecialchars($partner['name']) ?>
                </a>
                <span class="govuk-body-s">
                    —
                    <?php
                    $enabledFeatures = [];
                    if ($partner['members_enabled']) $enabledFeatures[] = 'Members';
                    if ($partner['listings_enabled']) $enabledFeatures[] = 'Listings';
                    if ($partner['events_enabled']) $enabledFeatures[] = 'Events';
                    if ($partner['groups_enabled']) $enabledFeatures[] = 'Groups';
                    echo implode(', ', $enabledFeatures);
                    ?>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="govuk-body">No partner timebanks connected yet. Check back soon!</p>
        <?php endif; ?>

    </main>
</div>

<?php
// Include real-time notifications for opted-in users
if ($userOptedIn):
    require dirname(__DIR__) . '/partials/federation-realtime.php';
endif;
?>

<!-- Page-specific JavaScript -->
<script src="/assets/js/civicone-federation-hub.min.js" defer></script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
