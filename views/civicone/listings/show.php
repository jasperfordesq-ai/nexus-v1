<?php
// CivicOne Listing Detail - WCAG 2.1 AA Compliant
// GOV.UK Detail Page Template (Template C)
if (session_status() === PHP_SESSION_NONE) session_start();

$hTitle = $listing['title'];
$hSubtitle = 'Posted ' . date('F j, Y', strtotime($listing['created_at'])) . ' in ' . htmlspecialchars($listing['category_name'] ?? 'General');
$hType = ucfirst($listing['type'] ?? 'Listing');

require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<?php
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'Offers & Requests', 'url' => '/listings'],
    ['label' => $listing['title']]
];
require dirname(__DIR__, 2) . '/layouts/civicone/partials/breadcrumb.php';
?>

<!-- GOV.UK Page Template Boilerplate -->
<div class="civicone-width-container civicone--govuk">
    <main class="civicone-main-wrapper" id="main-content" role="main">

        <!-- Back Link (optional) -->
        <a href="<?= $basePath ?>/listings" class="civicone-back-link">
            Back to all listings
        </a>

        <!-- Page Header -->
        <div class="civicone-grid-row">
            <div class="civicone-grid-column-two-thirds">
                <h1 class="civicone-heading-xl"><?= htmlspecialchars($listing['title']) ?></h1>

                <!-- Type Badge -->
                <p class="civicone-listing-detail__type-badge civicone-listing-detail__type-badge--<?= strtolower($listing['type'] ?? 'listing') ?>">
                    <?= ucfirst($listing['type'] ?? 'Listing') ?>
                </p>
            </div>
        </div>

        <!-- Main Content Area (2/3 + 1/3 split) -->
        <div class="civicone-grid-row">

            <!-- Left: Main Content (2/3) -->
            <div class="civicone-grid-column-two-thirds">

                <!-- Summary List for Key Facts -->
                <h2 class="civicone-heading-l">Key facts</h2>
                <dl class="civicone-summary-list">
                    <div class="civicone-summary-list__row">
                        <dt class="civicone-summary-list__key">Type</dt>
                        <dd class="civicone-summary-list__value"><?= ucfirst($listing['type'] ?? 'Listing') ?></dd>
                    </div>

                    <?php if (!empty($listing['category_name'])): ?>
                    <div class="civicone-summary-list__row">
                        <dt class="civicone-summary-list__key">Category</dt>
                        <dd class="civicone-summary-list__value"><?= htmlspecialchars($listing['category_name']) ?></dd>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($listing['location'])): ?>
                    <div class="civicone-summary-list__row">
                        <dt class="civicone-summary-list__key">Location</dt>
                        <dd class="civicone-summary-list__value"><?= htmlspecialchars($listing['location']) ?></dd>
                    </div>
                    <?php endif; ?>

                    <div class="civicone-summary-list__row">
                        <dt class="civicone-summary-list__key">Posted</dt>
                        <dd class="civicone-summary-list__value">
                            <time datetime="<?= date('Y-m-d', strtotime($listing['created_at'])) ?>">
                                <?= date('j F Y', strtotime($listing['created_at'])) ?>
                            </time>
                        </dd>
                    </div>

                    <div class="civicone-summary-list__row">
                        <dt class="civicone-summary-list__key">Posted by</dt>
                        <dd class="civicone-summary-list__value">
                            <a href="<?= $basePath ?>/profile/<?= $listing['user_id'] ?>" class="civicone-link">
                                <?= htmlspecialchars($listing['author_name']) ?>
                            </a>
                        </dd>
                    </div>

                    <?php if (!empty($listing['status'])): ?>
                    <div class="civicone-summary-list__row">
                        <dt class="civicone-summary-list__key">Status</dt>
                        <dd class="civicone-summary-list__value">
                            <span class="civicone-tag <?= $listing['status'] === 'active' ? 'civicone-tag--green' : '' ?>">
                                <?= ucfirst($listing['status']) ?>
                            </span>
                        </dd>
                    </div>
                    <?php endif; ?>
                </dl>

                <!-- Image (if present) -->
                <?php if (!empty($listing['image_url'])): ?>
                <div class="civicone-listing-detail__image">
                    <img src="<?= htmlspecialchars($listing['image_url']) ?>"
                         alt="<?= htmlspecialchars($listing['title']) ?>"
                         loading="lazy">
                </div>
                <?php endif; ?>

                <!-- Description -->
                <h2 class="civicone-heading-l">Description</h2>
                <div class="civicone-body civicone-listing-detail__description">
                    <?= nl2br(htmlspecialchars($listing['description'])) ?>
                </div>

                <!-- Additional Attributes (if present) -->
                <?php if (!empty($listingAttributes)): ?>
                <h2 class="civicone-heading-l">Additional details</h2>
                <dl class="civicone-summary-list">
                    <?php foreach ($listingAttributes as $attr): ?>
                    <div class="civicone-summary-list__row">
                        <dt class="civicone-summary-list__key"><?= htmlspecialchars($attr['name']) ?></dt>
                        <dd class="civicone-summary-list__value"><?= htmlspecialchars($attr['value']) ?></dd>
                    </div>
                    <?php endforeach; ?>
                </dl>
                <?php endif; ?>

                <!-- GOV.UK Details Component for Extra Info -->
                <?php if (!empty($listing['terms']) || !empty($listing['safety_notes'])): ?>
                <details class="civicone-details" role="group">
                    <summary class="civicone-details__summary">
                        <span class="civicone-details__summary-text">
                            Important information
                        </span>
                    </summary>
                    <div class="civicone-details__text">
                        <?php if (!empty($listing['terms'])): ?>
                        <h3 class="civicone-heading-s">Terms and conditions</h3>
                        <p class="civicone-body"><?= nl2br(htmlspecialchars($listing['terms'])) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($listing['safety_notes'])): ?>
                        <h3 class="civicone-heading-s">Safety notes</h3>
                        <p class="civicone-body"><?= nl2br(htmlspecialchars($listing['safety_notes'])) ?></p>
                        <?php endif; ?>
                    </div>
                </details>
                <?php endif; ?>

                <!-- Social Interactions -->
                <div class="civicone-listing-detail__social">
                    <?php
                    $targetType = 'listing';
                    $targetId = $listing['id'];
                    include dirname(__DIR__) . '/partials/social_interactions.php';
                    ?>
                </div>

            </div>

            <!-- Right: Sidebar (1/3) -->
            <div class="civicone-grid-column-one-third">
                <aside aria-label="Contact and actions">

                    <!-- Primary Action Card -->
                    <div class="civicone-listing-detail__action-card">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if ($_SESSION['user_id'] == $listing['user_id']): ?>
                                <!-- Owner Actions -->
                                <div class="civicone-notification civicone-notification--info">
                                    <p class="civicone-notification__heading">This is your listing</p>
                                    <p class="civicone-body-s">You can edit or manage your listing below.</p>
                                </div>

                                <a href="<?= $basePath ?>/listings/edit/<?= $listing['id'] ?>"
                                   class="civicone-button civicone-listing-detail__action-button">
                                    Edit this listing
                                </a>

                                <?php if ($listing['status'] === 'active'): ?>
                                <button type="button"
                                        class="civicone-button civicone-button--secondary civicone-listing-detail__action-button"
                                        onclick="if(confirm('Mark this listing as fulfilled?')) { /* Add close/fulfill logic */ }">
                                    Mark as fulfilled
                                </button>
                                <?php endif; ?>

                            <?php else: ?>
                                <!-- Contact Action -->
                                <h2 class="civicone-heading-m">Contact</h2>
                                <p class="civicone-body-s">Send a message to the poster about this listing.</p>

                                <a href="<?= $basePath ?>/messages/<?= $listing['user_id'] ?>?ref=<?= urlencode("Re: " . $listing['title']) ?>"
                                   class="civicone-button civicone-listing-detail__action-button">
                                    Send message
                                </a>

                                <button type="button"
                                        class="civicone-button civicone-button--secondary civicone-listing-detail__action-button"
                                        onclick="/* Add save/bookmark logic */">
                                    Save this listing
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Login Prompt -->
                            <div class="civicone-notification civicone-notification--info">
                                <p class="civicone-notification__heading">Sign in to respond</p>
                                <p class="civicone-body-s">You need to be signed in to contact the poster or save this listing.</p>
                            </div>

                            <a href="<?= $basePath ?>/login?return=<?= urlencode('/listings/' . $listing['id']) ?>"
                               class="civicone-button civicone-listing-detail__action-button">
                                Sign in
                            </a>

                            <a href="<?= $basePath ?>/register"
                               class="civicone-button civicone-button--secondary civicone-listing-detail__action-button">
                                Create account
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Share/Report Actions -->
                    <div class="civicone-listing-detail__secondary-actions">
                        <h2 class="civicone-heading-s">Share</h2>
                        <ul class="civicone-listing-detail__share-list">
                            <li>
                                <button type="button"
                                        class="civicone-link"
                                        onclick="navigator.share ? navigator.share({title: '<?= htmlspecialchars($listing['title']) ?>', url: window.location.href}) : navigator.clipboard.writeText(window.location.href).then(() => alert('Link copied!'))">
                                    Copy link to this listing
                                </button>
                            </li>
                        </ul>

                        <h2 class="civicone-heading-s">Report</h2>
                        <ul class="civicone-listing-detail__report-list">
                            <li>
                                <a href="<?= $basePath ?>/report/listing/<?= $listing['id'] ?>" class="civicone-link">
                                    Report this listing
                                </a>
                            </li>
                        </ul>
                    </div>

                </aside>
            </div>

        </div><!-- /grid-row -->

        <!-- Related Listings (if applicable) -->
        <?php if (!empty($relatedListings)): ?>
        <div class="civicone-grid-row">
            <div class="civicone-grid-column-full">
                <h2 class="civicone-heading-l">Related listings</h2>
                <ul class="civicone-listing-detail__related-list">
                    <?php foreach (array_slice($relatedListings, 0, 5) as $related): ?>
                    <li class="civicone-listing-detail__related-item">
                        <h3 class="civicone-heading-s">
                            <a href="<?= $basePath ?>/listings/<?= $related['id'] ?>" class="civicone-link">
                                <?= htmlspecialchars($related['title']) ?>
                            </a>
                        </h3>
                        <p class="civicone-body-s civicone-listing-detail__related-meta">
                            <?= ucfirst($related['type']) ?>
                            <?php if (!empty($related['location'])): ?>
                            · <?= htmlspecialchars($related['location']) ?>
                            <?php endif; ?>
                            · Posted <?= date('j M Y', strtotime($related['created_at'])) ?>
                        </p>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div><!-- /width-container -->

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
