<?php
/**
 * CivicOne View: Listing Details
 * GOV.UK Design System Compliant (WCAG 2.1 AA)
 */
if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle = $listing['title'];
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="<?= $basePath ?>/listings">Offers & Requests</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page"><?= htmlspecialchars($listing['title']) ?></li>
    </ol>
</nav>

<a href="<?= $basePath ?>/listings" class="govuk-back-link govuk-!-margin-bottom-6">Back to all listings</a>

<div class="govuk-grid-row govuk-!-margin-bottom-6">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl govuk-!-margin-bottom-2"><?= htmlspecialchars($listing['title']) ?></h1>
        <p class="govuk-body-l">
            <span class="govuk-tag <?= strtolower($listing['type'] ?? 'listing') === 'offer' ? 'govuk-tag--green' : 'govuk-tag--blue' ?>">
                <?= ucfirst($listing['type'] ?? 'Listing') ?>
            </span>
        </p>
    </div>
</div>

<div class="govuk-grid-row">
    <!-- Main Content -->
    <div class="govuk-grid-column-two-thirds">

        <!-- Key Facts -->
        <h2 class="govuk-heading-l">Key facts</h2>
        <dl class="govuk-summary-list govuk-!-margin-bottom-6">
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Type</dt>
                <dd class="govuk-summary-list__value"><?= ucfirst($listing['type'] ?? 'Listing') ?></dd>
            </div>

            <?php if (!empty($listing['category_name'])): ?>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Category</dt>
                <dd class="govuk-summary-list__value"><?= htmlspecialchars($listing['category_name']) ?></dd>
            </div>
            <?php endif; ?>

            <?php if (!empty($listing['location'])): ?>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Location</dt>
                <dd class="govuk-summary-list__value">
                    <i class="fa-solid fa-location-dot govuk-!-margin-right-1" aria-hidden="true"></i>
                    <?= htmlspecialchars($listing['location']) ?>
                </dd>
            </div>
            <?php endif; ?>

            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Posted</dt>
                <dd class="govuk-summary-list__value">
                    <time datetime="<?= date('Y-m-d', strtotime($listing['created_at'])) ?>">
                        <?= date('j F Y', strtotime($listing['created_at'])) ?>
                    </time>
                </dd>
            </div>

            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Posted by</dt>
                <dd class="govuk-summary-list__value">
                    <a href="<?= $basePath ?>/profile/<?= $listing['user_id'] ?>" class="govuk-link">
                        <?= htmlspecialchars($listing['author_name']) ?>
                    </a>
                </dd>
            </div>

            <?php if (!empty($listing['status'])): ?>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key">Status</dt>
                <dd class="govuk-summary-list__value">
                    <span class="govuk-tag <?= $listing['status'] === 'active' ? 'govuk-tag--green' : 'govuk-tag--grey' ?>">
                        <?= ucfirst($listing['status']) ?>
                    </span>
                </dd>
            </div>
            <?php endif; ?>
        </dl>

        <!-- Image -->
        <?php if (!empty($listing['image_url'])): ?>
        <div class="govuk-!-margin-bottom-6">
            <img src="<?= htmlspecialchars($listing['image_url']) ?>"
                 alt="<?= htmlspecialchars($listing['title']) ?>"
                 loading="lazy"
                 style="max-width: 100%; height: auto;">
        </div>
        <?php endif; ?>

        <!-- Description -->
        <h2 class="govuk-heading-l">Description</h2>
        <div class="govuk-body govuk-!-margin-bottom-6">
            <?= nl2br(htmlspecialchars($listing['description'])) ?>
        </div>

        <!-- Additional Attributes -->
        <?php if (!empty($listingAttributes)): ?>
        <h2 class="govuk-heading-l">Additional details</h2>
        <dl class="govuk-summary-list govuk-!-margin-bottom-6">
            <?php foreach ($listingAttributes as $attr): ?>
            <div class="govuk-summary-list__row">
                <dt class="govuk-summary-list__key"><?= htmlspecialchars($attr['name']) ?></dt>
                <dd class="govuk-summary-list__value"><?= htmlspecialchars($attr['value']) ?></dd>
            </div>
            <?php endforeach; ?>
        </dl>
        <?php endif; ?>

        <!-- Important Information -->
        <?php if (!empty($listing['terms']) || !empty($listing['safety_notes'])): ?>
        <details class="govuk-details govuk-!-margin-bottom-6" data-module="govuk-details">
            <summary class="govuk-details__summary">
                <span class="govuk-details__summary-text">Important information</span>
            </summary>
            <div class="govuk-details__text">
                <?php if (!empty($listing['terms'])): ?>
                <h3 class="govuk-heading-s">Terms and conditions</h3>
                <p class="govuk-body"><?= nl2br(htmlspecialchars($listing['terms'])) ?></p>
                <?php endif; ?>

                <?php if (!empty($listing['safety_notes'])): ?>
                <h3 class="govuk-heading-s">Safety notes</h3>
                <p class="govuk-body"><?= nl2br(htmlspecialchars($listing['safety_notes'])) ?></p>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>

        <!-- Social Interactions -->
        <?php
        $targetType = 'listing';
        $targetId = $listing['id'];
        include dirname(__DIR__) . '/partials/social_interactions.php';
        ?>

    </div>

    <!-- Sidebar -->
    <div class="govuk-grid-column-one-third">

        <!-- Action Card -->
        <div class="govuk-!-padding-6 govuk-!-margin-bottom-6" style="border: 1px solid #b1b4b6; border-left: 5px solid #1d70b8;">
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['user_id'] == $listing['user_id']): ?>
                    <!-- Owner Actions -->
                    <div class="govuk-inset-text govuk-!-margin-bottom-4">
                        <p class="govuk-body govuk-!-margin-bottom-0"><strong>This is your listing</strong></p>
                        <p class="govuk-body-s govuk-!-margin-bottom-0">You can edit or manage your listing below.</p>
                    </div>

                    <a href="<?= $basePath ?>/listings/edit/<?= $listing['id'] ?>" class="govuk-button" data-module="govuk-button" style="width: 100%;">
                        <i class="fa-solid fa-edit govuk-!-margin-right-1" aria-hidden="true"></i> Edit this listing
                    </a>

                    <?php if ($listing['status'] === 'active'): ?>
                    <button type="button" class="govuk-button govuk-button--secondary govuk-!-margin-top-2" data-module="govuk-button" style="width: 100%;"
                            onclick="if(confirm('Mark this listing as fulfilled?')) { /* Add close/fulfill logic */ }">
                        <i class="fa-solid fa-check govuk-!-margin-right-1" aria-hidden="true"></i> Mark as fulfilled
                    </button>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Contact Action -->
                    <h2 class="govuk-heading-m">Contact</h2>
                    <p class="govuk-body-s">Send a message to the poster about this listing.</p>

                    <a href="<?= $basePath ?>/messages/<?= $listing['user_id'] ?>?ref=<?= urlencode("Re: " . $listing['title']) ?>" class="govuk-button" data-module="govuk-button" style="width: 100%;">
                        <i class="fa-solid fa-envelope govuk-!-margin-right-1" aria-hidden="true"></i> Send message
                    </a>

                    <button type="button" class="govuk-button govuk-button--secondary govuk-!-margin-top-2" data-module="govuk-button" style="width: 100%;">
                        <i class="fa-solid fa-bookmark govuk-!-margin-right-1" aria-hidden="true"></i> Save this listing
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <!-- Login Prompt -->
                <div class="govuk-inset-text govuk-!-margin-bottom-4">
                    <p class="govuk-body govuk-!-margin-bottom-0"><strong>Sign in to respond</strong></p>
                    <p class="govuk-body-s govuk-!-margin-bottom-0">You need to be signed in to contact the poster or save this listing.</p>
                </div>

                <a href="<?= $basePath ?>/login?return=<?= urlencode('/listings/' . $listing['id']) ?>" class="govuk-button" data-module="govuk-button" style="width: 100%;">
                    Sign in
                </a>

                <a href="<?= $basePath ?>/register" class="govuk-button govuk-button--secondary govuk-!-margin-top-2" data-module="govuk-button" style="width: 100%;">
                    Create account
                </a>
            <?php endif; ?>
        </div>

        <!-- Share/Report -->
        <div class="govuk-!-padding-6" style="border: 1px solid #b1b4b6;">
            <h2 class="govuk-heading-s">Share</h2>
            <p class="govuk-body-s govuk-!-margin-bottom-4">
                <button type="button" class="govuk-link" style="border: none; background: none; cursor: pointer; text-decoration: underline;"
                        onclick="navigator.share ? navigator.share({title: '<?= htmlspecialchars(addslashes($listing['title'])) ?>', url: window.location.href}) : navigator.clipboard.writeText(window.location.href).then(() => alert('Link copied!'))">
                    <i class="fa-solid fa-copy govuk-!-margin-right-1" aria-hidden="true"></i> Copy link to this listing
                </button>
            </p>

            <h2 class="govuk-heading-s">Report</h2>
            <p class="govuk-body-s govuk-!-margin-bottom-0">
                <a href="<?= $basePath ?>/report/listing/<?= $listing['id'] ?>" class="govuk-link">
                    <i class="fa-solid fa-flag govuk-!-margin-right-1" aria-hidden="true"></i> Report this listing
                </a>
            </p>
        </div>

    </div>
</div>

<!-- Related Listings -->
<?php if (!empty($relatedListings)): ?>
<hr class="govuk-section-break govuk-section-break--xl govuk-section-break--visible">
<div class="govuk-grid-row">
    <div class="govuk-grid-column-full">
        <h2 class="govuk-heading-l">Related listings</h2>
        <div class="govuk-grid-row">
            <?php foreach (array_slice($relatedListings, 0, 4) as $related): ?>
            <div class="govuk-grid-column-one-quarter govuk-!-margin-bottom-4">
                <div class="govuk-!-padding-4" style="border: 1px solid #b1b4b6;">
                    <h3 class="govuk-heading-s govuk-!-margin-bottom-2">
                        <a href="<?= $basePath ?>/listings/<?= $related['id'] ?>" class="govuk-link">
                            <?= htmlspecialchars($related['title']) ?>
                        </a>
                    </h3>
                    <p class="govuk-body-s govuk-!-margin-bottom-0" style="color: #505a5f;">
                        <span class="govuk-tag govuk-tag--grey" style="font-size: 0.7rem;"><?= ucfirst($related['type']) ?></span>
                        <?php if (!empty($related['location'])): ?>
                            · <?= htmlspecialchars($related['location']) ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>
