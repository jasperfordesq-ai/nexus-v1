<?php
/**
 * CivicOne Dashboard - My Hubs Page
 * WCAG 2.1 AA Compliant
 * Template: Account Area Template (Template G)
 */

$hTitle = "My Hubs";
$hSubtitle = "Your community connections";
$hGradient = 'civic-hero-gradient';
$hType = 'Dashboard';

require dirname(dirname(__DIR__)) . '/layouts/civicone/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<div class="civic-dashboard civicone-account-area">

    <!-- Account Area Secondary Navigation -->
    <?php require dirname(dirname(__DIR__)) . '/layouts/civicone/partials/account-navigation.php'; ?>

    <!-- GROUPS/HUBS CONTENT -->
    <section class="civic-dash-card" aria-labelledby="my-hubs-heading">
        <div class="civic-dash-card-header">
            <h2 id="my-hubs-heading" class="civic-dash-card-title">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                My Hubs
            </h2>
            <a href="<?= $basePath ?>/groups" class="civic-button" role="button">
                <i class="fa-solid fa-compass" aria-hidden="true"></i> Browse All Hubs
            </a>
        </div>
        <?php if (empty($myGroups)): ?>
            <div class="civic-empty-state civic-empty-large">
                <div class="civic-empty-icon"><i class="fa-solid fa-user-group" aria-hidden="true"></i></div>
                <h3>No hubs joined</h3>
                <p class="civic-empty-text">Join a hub to connect with your community.</p>
                <a href="<?= $basePath ?>/groups" class="civic-button" role="button">Browse Hubs</a>
            </div>
        <?php else: ?>
            <div class="civic-hubs-grid">
                <?php foreach ($myGroups as $grp): ?>
                    <article class="civic-hub-card">
                        <h3 class="civic-hub-card-title"><?= htmlspecialchars($grp['name']) ?></h3>
                        <p class="civic-hub-card-desc"><?= htmlspecialchars($grp['description'] ?? '') ?></p>
                        <div class="civic-hub-card-footer">
                            <span class="civic-hub-card-members">
                                <i class="fa-solid fa-users" aria-hidden="true"></i>
                                <?= $grp['member_count'] ?? 0 ?> members
                            </span>
                            <a href="<?= $basePath ?>/groups/<?= $grp['id'] ?>" class="civic-button" role="button">Enter Hub</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</div>

<script src="/assets/js/civicone-dashboard.js"></script>

<?php require dirname(dirname(__DIR__)) . '/layouts/civicone/footer.php'; ?>
