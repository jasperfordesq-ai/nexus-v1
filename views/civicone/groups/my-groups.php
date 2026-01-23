<?php
/**
 * CivicOne My Groups - User's Group Memberships
 * Template A: Directory/List (Section 10.3)
 * Glassmorphism grid view of joined groups/hubs
 * WCAG 2.1 AA Compliant
 */
$pageTitle = "My Hubs";
$pageSubtitle = "Hubs you have joined";
$hideHero = true;

require __DIR__ . '/../../layouts/civicone/header.php';

$basePath = Nexus\Core\TenantContext::getBasePath();
?>
<link rel="stylesheet" href="/assets/css/purged/civicone-groups-my-groups.min.css?v=<?= time() ?>">

<!-- GOV.UK Page Template Boilerplate (Section 10.0) -->
<div class="civicone-width-container">
    <main class="civicone-main-wrapper">

        <!-- Offline Banner -->
        <div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
            <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
            <span>No internet connection</span>
        </div>

        <div class="htb-container-full">
            <div id="my-groups-glass-wrapper">

                <!-- Page Header -->
                <div class="page-header">
                    <h1><span aria-hidden="true">👥</span> My Hubs</h1>
                    <p>Community hubs you have joined</p>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <a href="<?= $basePath ?>/groups" class="quick-action-btn">
                        <i class="fa-solid fa-compass" aria-hidden="true"></i> Browse All Hubs
                    </a>
                    <a href="<?= $basePath ?>/dashboard?tab=groups" class="quick-action-btn">
                        <i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Dashboard
                    </a>
                </div>

                <!-- Groups Grid -->
                <div class="groups-grid" role="list">
                    <?php if (empty($myGroups)): ?>
                        <div class="empty-state" role="listitem">
                            <span class="empty-icon" aria-hidden="true">🏘️</span>
                            <h3>No hubs yet</h3>
                            <p>You haven't joined any community hubs. Explore and find groups that match your interests!</p>
                            <a href="<?= $basePath ?>/groups" class="browse-btn">
                                <i class="fa-solid fa-compass" aria-hidden="true"></i> Browse Hubs
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($myGroups as $group): ?>
                            <a href="<?= $basePath ?>/groups/<?= $group['id'] ?>" class="group-card" role="listitem">
                                <!-- Cover Image -->
                                <?php
                                $displayImage = !empty($group['cover_image_url']) ? $group['cover_image_url'] : ($group['image_url'] ?? '');
                                ?>
                                <?php if (!empty($displayImage)): ?>
                                    <div class="card-cover">
                                        <img src="<?= htmlspecialchars($displayImage) ?>" loading="lazy" alt="<?= htmlspecialchars($group['name']) ?> cover image">
                                        <span class="card-badge">MEMBER</span>
                                    </div>
                                <?php else: ?>
                                    <div class="card-cover card-cover-gradient">
                                        <i class="fa-solid fa-users-rectangle" aria-hidden="true"></i>
                                        <span class="card-badge">MEMBER</span>
                                    </div>
                                <?php endif; ?>

                                <!-- Card Body -->
                                <div class="card-body">
                                    <h3><?= htmlspecialchars($group['name']) ?></h3>
                                    <p><?= htmlspecialchars(substr($group['description'] ?? 'A community hub for members to connect and collaborate.', 0, 100)) ?>...</p>

                                    <div class="card-meta">
                                        <div class="member-count">
                                            <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                                            <span><?= $group['member_count'] ?? 0 ?> members</span>
                                        </div>
                                        <span class="visit-btn">
                                            Enter <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div><!-- #my-groups-glass-wrapper -->
        </div>

    </main>
</div><!-- /civicone-width-container -->

<script src="/assets/js/civicone-groups-my-groups.js?v=<?= time() ?>"></script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
