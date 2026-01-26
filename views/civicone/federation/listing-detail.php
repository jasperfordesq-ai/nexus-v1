<?php
/**
 * Federation Listing Detail
 * GOV.UK Design System (WCAG 2.1 AA)
 */
$pageTitle = $pageTitle ?? "Federated Listing";
$hideHero = true;

Nexus\Core\SEO::setTitle(($listing['title'] ?? 'Listing') . ' - Federated');
Nexus\Core\SEO::setDescription('Listing details from a partner timebank in the federation network.');

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';
$basePath = Nexus\Core\TenantContext::getBasePath();

$listing = $listing ?? [];
$canMessage = $canMessage ?? false;
$isExternalListing = $isExternalListing ?? (!empty($listing['is_external']));
$externalPartnerId = $listing['external_partner_id'] ?? null;
$externalTenantId = $listing['external_tenant_id'] ?? 1;

$ownerName = $listing['owner_name'] ?? 'Unknown';
$fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($ownerName) . '&background=00703c&color=fff&size=200';
$ownerAvatar = !empty($listing['owner_avatar']) ? $listing['owner_avatar'] : $fallbackAvatar;
$type = $listing['type'] ?? 'offer';
$typeColor = $type === 'offer' ? '#00703c' : '#1d70b8';
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
    <a href="<?= $basePath ?>/federation/listings" class="govuk-back-link govuk-!-margin-top-4">
        Back to Federated Listings
    </a>

    <main class="govuk-main-wrapper govuk-!-padding-top-4" id="main-content" role="main">
        <div class="govuk-grid-row">
            <div class="govuk-grid-column-two-thirds">
                <!-- Listing Card -->
                <article class="govuk-!-padding-6" style="background: #fff; border: 1px solid #b1b4b6; border-left: 5px solid <?= $typeColor ?>;" aria-labelledby="listing-title">
                    <!-- Badges -->
                    <div class="govuk-!-margin-bottom-4">
                        <span class="govuk-tag" style="background: <?= $typeColor ?>;">
                            <i class="fa-solid <?= $type === 'offer' ? 'fa-hand-holding-heart' : 'fa-hand-holding' ?> govuk-!-margin-right-1" aria-hidden="true"></i>
                            <?= ucfirst($type) ?>
                        </span>
                        <span class="govuk-tag govuk-tag--grey govuk-!-margin-left-2">
                            <i class="fa-solid <?= $isExternalListing ? 'fa-globe' : 'fa-building' ?> govuk-!-margin-right-1" aria-hidden="true"></i>
                            <?= htmlspecialchars($listing['tenant_name'] ?? 'Partner Timebank') ?>
                            <?php if ($isExternalListing): ?>
                            <span class="govuk-tag govuk-tag--blue" style="margin-left: 4px; font-size: 0.7em;">External</span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <h1 class="govuk-heading-xl govuk-!-margin-bottom-4" id="listing-title">
                        <?= htmlspecialchars($listing['title'] ?? 'Untitled') ?>
                    </h1>

                    <?php if (!empty($listing['category_name'])): ?>
                        <p class="govuk-body govuk-!-margin-bottom-6">
                            <span class="govuk-tag govuk-tag--light-blue">
                                <i class="fa-solid fa-tag govuk-!-margin-right-1" aria-hidden="true"></i>
                                <?= htmlspecialchars($listing['category_name']) ?>
                            </span>
                        </p>
                    <?php endif; ?>

                    <!-- Description -->
                    <?php if (!empty($listing['description'])): ?>
                        <h2 class="govuk-heading-m">
                            <i class="fa-solid fa-align-left govuk-!-margin-right-2 civicone-secondary-text" aria-hidden="true"></i>
                            Description
                        </h2>
                        <p class="govuk-body-l govuk-!-margin-bottom-6">
                            <?= nl2br(htmlspecialchars($listing['description'])) ?>
                        </p>
                    <?php endif; ?>

                    <!-- Owner Section -->
                    <h2 class="govuk-heading-m">
                        <i class="fa-solid fa-user govuk-!-margin-right-2 civicone-secondary-text" aria-hidden="true"></i>
                        Posted By
                    </h2>
                    <div class="govuk-!-padding-4 govuk-!-margin-bottom-6 civicone-panel-bg civicone-flex-gap">
                        <img src="<?= htmlspecialchars($ownerAvatar) ?>"
                             onerror="this.src='<?= $fallbackAvatar ?>'"
                             alt=""
                             class="civicone-avatar-lg"
                             loading="lazy">
                        <div>
                            <p class="govuk-body-l govuk-!-font-weight-bold govuk-!-margin-bottom-1">
                                <?= htmlspecialchars($ownerName) ?>
                            </p>
                            <p class="govuk-body-s govuk-!-margin-bottom-0 civicone-secondary-text">
                                <i class="fa-solid fa-building govuk-!-margin-right-1" aria-hidden="true"></i>
                                <?= htmlspecialchars($listing['tenant_name'] ?? 'Partner Timebank') ?>
                            </p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="govuk-button-group">
                        <?php if ($canMessage): ?>
                            <?php if ($isExternalListing && $externalPartnerId): ?>
                                <!-- External listing - use federated messaging with external partner -->
                                <a href="<?= $basePath ?>/federation/messages/compose?external_partner=<?= $externalPartnerId ?>&member_id=<?= $listing['owner_id'] ?>&member_name=<?= urlencode($ownerName) ?>&external_tenant=<?= $externalTenantId ?>"
                                   class="govuk-button" data-module="govuk-button">
                                    <i class="fa-solid fa-envelope govuk-!-margin-right-2" aria-hidden="true"></i>
                                    Contact <?= htmlspecialchars(explode(' ', $ownerName)[0]) ?>
                                </a>
                            <?php else: ?>
                                <!-- Internal federated listing -->
                                <a href="<?= $basePath ?>/federation/messages/compose?to=<?= $listing['owner_id'] ?>&tenant=<?= $listing['owner_tenant_id'] ?>"
                                   class="govuk-button" data-module="govuk-button">
                                    <i class="fa-solid fa-envelope govuk-!-margin-right-2" aria-hidden="true"></i>
                                    Contact <?= htmlspecialchars(explode(' ', $ownerName)[0]) ?>
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="govuk-button govuk-button--disabled" aria-disabled="true">
                                <i class="fa-solid fa-envelope govuk-!-margin-right-2" aria-hidden="true"></i>
                                Messaging Unavailable
                            </span>
                        <?php endif; ?>

                        <?php if ($isExternalListing && $externalPartnerId): ?>
                            <!-- External listing - link to external member profile -->
                            <a href="<?= $basePath ?>/federation/members/external/<?= $externalPartnerId ?>/<?= $listing['owner_id'] ?>"
                               class="govuk-button govuk-button--secondary" data-module="govuk-button">
                                <i class="fa-solid fa-user govuk-!-margin-right-2" aria-hidden="true"></i>
                                View Profile
                            </a>
                        <?php else: ?>
                            <a href="<?= $basePath ?>/federation/members/<?= $listing['owner_id'] ?>"
                               class="govuk-button govuk-button--secondary" data-module="govuk-button">
                                <i class="fa-solid fa-user govuk-!-margin-right-2" aria-hidden="true"></i>
                                View Profile
                            </a>
                        <?php endif; ?>
                    </div>
                </article>

                <!-- Privacy Notice -->
                <div class="govuk-inset-text govuk-!-margin-top-6">
                    <p class="govuk-body govuk-!-margin-bottom-0">
                        <i class="fa-solid fa-shield-halved govuk-!-margin-right-2 civicone-icon-blue" aria-hidden="true"></i>
                        <strong>Federated Listing</strong> — This listing is from <strong><?= htmlspecialchars($listing['tenant_name'] ?? 'a partner timebank') ?></strong>.
                        Contact the poster to discuss terms and arrange an exchange.
                    </p>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Offline indicator handled by civicone-common.js -->

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
