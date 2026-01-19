<?php
// CivicOne View: Volunteering Index - WCAG 2.1 AA Compliant
// CSS extracted to civicone-volunteering.css
$hTitle = "Volunteer Opportunities";
$hSubtitle = "Connect with local organizations and make a difference in your community";
$hType = "Community Impact";

require __DIR__ . '/../../layouts/civicone/header.php';
?>

<!-- Search Bar - MadeOpen Style -->
<div class="civic-search-bar">
    <form action="" method="GET" class="civic-search-row">
        <div class="civic-search-input-wrapper">
            <span class="civic-search-icon dashicons dashicons-search"></span>
            <input type="search" name="q" id="q" class="civic-search-input"
                   placeholder="Search opportunities by role, skill, or location..."
                   value="<?= htmlspecialchars($query ?? '') ?>">
        </div>
        <button type="submit" class="civic-btn">Search</button>
    </form>
</div>

<!-- Action Buttons -->
<div class="civic-action-bar">
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/my-applications" class="civic-btn civic-btn--outline">
            <span class="dashicons dashicons-clipboard" aria-hidden="true"></span> My Applications
        </a>
    <?php endif; ?>
    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/dashboard" class="civic-btn civic-btn--outline">
        <span class="dashicons dashicons-building" aria-hidden="true"></span> Organization Dashboard
    </a>
</div>

<!-- Results Count -->
<p class="civic-results-count" id="civic-count">
    Showing <strong><?= count($opportunities ?? []) ?></strong> volunteer opportunities
</p>

<!-- Opportunities List -->
<?php if (empty($opportunities)): ?>
    <div class="civic-empty-state">
        <div class="civic-empty-state-icon">
            <span class="dashicons dashicons-heart" style="font-size: 48px;"></span>
        </div>
        <h3 class="civic-empty-state-title">No opportunities found</h3>
        <p class="civic-empty-state-text">
            <?php if (!empty($query)): ?>
                No opportunities match your search. Try a different term or check back later.
            <?php else: ?>
                There are no volunteer opportunities available at the moment. Check back soon!
            <?php endif; ?>
        </p>
    </div>
<?php else: ?>
    <div class="civic-opportunities-grid">
        <?php foreach ($opportunities as $opp): ?>
            <article class="civic-listing-card civic-listing-card--event">
                <div class="civic-listing-header">
                    <div class="civic-listing-meta">
                        <span class="civic-listing-type civic-listing-type--event">
                            <?= htmlspecialchars($opp['org_name'] ?? 'Organization') ?>
                        </span>
                        <h3 class="civic-listing-title">
                            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/<?= $opp['id'] ?>">
                                <?= htmlspecialchars($opp['title']) ?>
                            </a>
                        </h3>
                    </div>
                </div>

                <p class="civic-listing-description">
                    <?= htmlspecialchars(substr($opp['description'] ?? '', 0, 180)) ?><?= strlen($opp['description'] ?? '') > 180 ? '...' : '' ?>
                </p>

                <!-- Tags -->
                <div class="civic-tags">
                    <?php if (!empty($opp['location'])): ?>
                        <span class="civic-tag">
                            <span class="dashicons dashicons-location" aria-hidden="true"></span>
                            <?= htmlspecialchars($opp['location']) ?>
                        </span>
                    <?php else: ?>
                        <span class="civic-tag civic-tag--green">
                            <span class="dashicons dashicons-laptop" aria-hidden="true"></span>
                            Remote
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($opp['commitment'])): ?>
                        <span class="civic-tag">
                            <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                            <?= htmlspecialchars($opp['commitment']) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="civic-listing-footer">
                    <span class="civic-listing-time">
                        <?php if (!empty($opp['created_at'])): ?>
                            Posted <?= date('M j, Y', strtotime($opp['created_at'])) ?>
                        <?php endif; ?>
                    </span>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/<?= $opp['id'] ?>" class="civic-btn civic-btn--sm">
                        View Details
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
