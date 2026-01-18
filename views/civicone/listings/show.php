<?php
// CivicOne View: Show Listing - MadeOpen Style
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

<!-- Action Bar -->
<div class="civic-action-bar" style="margin-bottom: 24px;">
    <a href="<?= $basePath ?>/listings" class="civic-btn civic-btn--outline">
        <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
        Back to Listings
    </a>
</div>

<div class="civic-listing-detail">
    <div class="civic-listing-detail-grid">
        <!-- Main Content -->
        <div class="civic-listing-main">
            <article class="civic-card">
                <?php if (!empty($listing['image_url'])): ?>
                    <div class="civic-listing-image">
                        <img src="<?= htmlspecialchars($listing['image_url']) ?>"
                             alt="<?= htmlspecialchars($listing['title']) ?>">
                    </div>
                <?php endif; ?>

                <div class="civic-listing-body">
                    <?= nl2br(htmlspecialchars($listing['description'])) ?>
                </div>

                <?php if (!empty($listingAttributes)): ?>
                    <div class="civic-listing-attributes">
                        <h3 class="civic-section-subtitle">Details</h3>
                        <dl class="civic-attribute-list">
                            <?php foreach ($listingAttributes as $attr): ?>
                                <div class="civic-attribute-item">
                                    <dt><?= htmlspecialchars($attr['name']) ?></dt>
                                    <dd><?= htmlspecialchars($attr['value']) ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                <?php endif; ?>

                <!-- Social Interactions -->
                <?php
                $targetType = 'listing';
                $targetId = $listing['id'];
                include dirname(__DIR__) . '/partials/social_interactions.php';
                ?>
            </article>
        </div>

        <!-- Sidebar -->
        <aside class="civic-listing-sidebar">
            <div class="civic-card civic-author-card">
                <h3 class="civic-section-subtitle">Posted By</h3>
                <p class="civic-author-name">
                    <a href="<?= $basePath ?>/profile/<?= $listing['user_id'] ?>">
                        <?= htmlspecialchars($listing['author_name']) ?>
                    </a>
                </p>

                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['user_id'] == $listing['user_id']): ?>
                        <div class="civic-owner-notice">
                            <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                            This is your listing
                        </div>
                        <a href="<?= $basePath ?>/listings/edit/<?= $listing['id'] ?>" class="civic-btn" style="width: 100%;">
                            <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                            Edit Listing
                        </a>
                    <?php else: ?>
                        <a href="<?= $basePath ?>/messages/<?= $listing['user_id'] ?>?ref=<?= urlencode("Re: " . $listing['title']) ?>" class="civic-btn" style="width: 100%;">
                            <span class="dashicons dashicons-email" aria-hidden="true"></span>
                            Contact Member
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="civic-login-prompt">
                        <a href="<?= $basePath ?>/login">Sign in</a> to contact this member.
                    </p>
                <?php endif; ?>
            </div>

            <?php if (!empty($listing['location'])): ?>
                <div class="civic-card" style="margin-top: 16px;">
                    <h3 class="civic-section-subtitle">
                        <span class="dashicons dashicons-location" aria-hidden="true"></span>
                        Location
                    </h3>
                    <p class="civic-location-text"><?= htmlspecialchars($listing['location']) ?></p>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</div>

<style>
    .civic-listing-detail-grid {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 24px;
    }

    .civic-listing-image {
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 24px;
    }

    .civic-listing-image img {
        width: 100%;
        height: auto;
        display: block;
    }

    .civic-listing-body {
        font-size: 1.1rem;
        line-height: 1.7;
        color: var(--civic-text-main);
    }

    .civic-listing-attributes {
        margin-top: 24px;
        padding-top: 24px;
        border-top: 1px solid var(--civic-border);
    }

    .civic-section-subtitle {
        font-size: 1rem;
        font-weight: 700;
        color: var(--civic-text-main);
        margin: 0 0 16px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .civic-attribute-list {
        margin: 0;
    }

    .civic-attribute-item {
        display: flex;
        gap: 8px;
        margin-bottom: 8px;
    }

    .civic-attribute-item dt {
        font-weight: 600;
        color: var(--civic-text-secondary);
    }

    .civic-attribute-item dd {
        margin: 0;
        color: var(--civic-text-main);
    }

    .civic-author-card {
        text-align: center;
    }

    .civic-author-name {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 16px;
    }

    .civic-author-name a {
        color: var(--civic-brand);
        text-decoration: none;
    }

    .civic-author-name a:hover {
        text-decoration: underline;
    }

    .civic-owner-notice {
        background: #FDF2F8;
        color: #BE185D;
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-weight: 600;
    }

    .civic-login-prompt {
        color: var(--civic-text-muted);
    }

    .civic-login-prompt a {
        color: var(--civic-brand);
        font-weight: 600;
    }

    .civic-location-text {
        margin: 0;
        font-size: 1.1rem;
        color: var(--civic-text-main);
    }

    @media (max-width: 900px) {
        .civic-listing-detail-grid {
            grid-template-columns: 1fr;
        }

        .civic-listing-sidebar {
            order: -1;
        }
    }
</style>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>