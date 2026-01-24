<?php
/**
 * CivicOne Dashboard - My Listings Page
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 * Template: Account Area Template (Template G)
 */

$hTitle = "My Listings";
$hSubtitle = "Manage your offers and requests";
$hGradient = 'civic-hero-gradient';
$hType = 'Dashboard';

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();

$offerCount = 0;
$reqCount = 0;
foreach ($my_listings as $ml) {
    if ($ml['type'] === 'offer') $offerCount++;
    else $reqCount++;
}
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/dashboard">Dashboard</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">My Listings</li>
    </ol>
</nav>

<!-- Account Area Secondary Navigation -->
<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/account-navigation.php'; ?>

<!-- LISTINGS CONTENT -->
<section aria-labelledby="my-listings-heading" class="govuk-!-margin-bottom-8">
    <div class="govuk-grid-row govuk-!-margin-bottom-4">
        <div class="govuk-grid-column-two-thirds">
            <h2 id="my-listings-heading" class="govuk-heading-l">
                <i class="fa-solid fa-list govuk-!-margin-right-2" aria-hidden="true"></i>
                My Listings
            </h2>
            <p class="govuk-body">
                <span class="govuk-tag govuk-tag--green govuk-!-margin-right-2"><?= $offerCount ?> Offers</span>
                <span class="govuk-tag govuk-tag--blue"><?= $reqCount ?> Requests</span>
            </p>
        </div>
        <div class="govuk-grid-column-one-third govuk-!-text-align-right">
            <a href="<?= $basePath ?>/compose?type=listing" class="govuk-button" data-module="govuk-button">
                <i class="fa-solid fa-plus govuk-!-margin-right-1" aria-hidden="true"></i> Post New Listing
            </a>
        </div>
    </div>

    <?php if (empty($my_listings)): ?>
        <div class="govuk-inset-text">
            <p class="govuk-body-l govuk-!-margin-bottom-2">
                <i class="fa-solid fa-seedling govuk-!-margin-right-2" aria-hidden="true"></i>
                <strong>No listings yet</strong>
            </p>
            <p class="govuk-body govuk-!-margin-bottom-4">You haven't posted any offers or requests yet.</p>
            <a href="<?= $basePath ?>/compose?type=listing" class="govuk-button govuk-button--start" data-module="govuk-button">
                Create your first listing
                <svg class="govuk-button__start-icon" xmlns="http://www.w3.org/2000/svg" width="17.5" height="19" viewBox="0 0 33 40" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M0 0h13l20 20-20 20H0l20-20z"/>
                </svg>
            </a>
        </div>
    <?php else: ?>
        <div class="govuk-grid-row">
            <?php foreach ($my_listings as $listing): ?>
                <div class="govuk-grid-column-one-third govuk-!-margin-bottom-6" id="listing-<?= $listing['id'] ?>">
                    <div class="govuk-!-padding-0" style="border: 1px solid #b1b4b6;">
                        <?php if (!empty($listing['image_url'])): ?>
                            <div style="height: 150px; background-image: url('<?= htmlspecialchars($listing['image_url']) ?>'); background-size: cover; background-position: center;"></div>
                        <?php else: ?>
                            <div class="govuk-!-padding-6 govuk-!-text-align-centre" style="height: 150px; background: <?= $listing['type'] === 'offer' ? '#00703c' : '#1d70b8' ?>; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-<?= $listing['type'] === 'offer' ? 'hand-holding-heart' : 'hand-holding' ?>" aria-hidden="true" style="font-size: 3rem; color: white;"></i>
                            </div>
                        <?php endif; ?>
                        <div class="govuk-!-padding-4">
                            <p class="govuk-body-s govuk-!-margin-bottom-2">
                                <span class="govuk-tag govuk-tag--<?= $listing['type'] === 'offer' ? 'green' : 'blue' ?>">
                                    <?= strtoupper($listing['type']) ?>
                                </span>
                                <span style="color: #505a5f;"><?= date('M j, Y', strtotime($listing['created_at'])) ?></span>
                            </p>
                            <h3 class="govuk-heading-s govuk-!-margin-bottom-3">
                                <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>" class="govuk-link"><?= htmlspecialchars($listing['title']) ?></a>
                            </h3>
                            <div class="govuk-button-group">
                                <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>" class="govuk-button govuk-button--secondary" data-module="govuk-button">
                                    <i class="fa-solid fa-eye govuk-!-margin-right-1" aria-hidden="true"></i> View
                                </a>
                                <button type="button" onclick="deleteListing(<?= $listing['id'] ?>)" class="govuk-button govuk-button--warning" data-module="govuk-button">
                                    <i class="fa-solid fa-trash govuk-!-margin-right-1" aria-hidden="true"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script src="/assets/js/civicone-dashboard.js"></script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
