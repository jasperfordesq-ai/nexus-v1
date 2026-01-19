<?php
// CivicOne Groups Index - WCAG 2.1 AA Compliant
// CSS extracted to civicone-groups.css
require __DIR__ . '/../../layouts/civicone/header.php';
?>

<div class="civic-container">
    <?php
    $breadcrumbs = [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'Local Hubs']
    ];
    require __DIR__ . '/../../layouts/civicone/partials/breadcrumb.php';
    ?>

    <div class="civic-groups-header">
        <h1>Local Hubs</h1>
        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/create-group" class="civic-btn">Start a Hub</a>
    </div>

    <?php if (empty($groups)): ?>
        <div class="civic-group-empty-state">
            <p>No hubs found.</p>
        </div>
    <?php else: ?>
        <div class="civic-groups-grid" role="list">
            <?php foreach ($groups as $group): ?>
                <?php
                $gName = htmlspecialchars($group['name']);
                $gDesc = htmlspecialchars(substr($group['description'] ?? '', 0, 100)) . '...';
                $hasImg = !empty($group['image_path']);
                ?>
                <article class="civic-group-card" role="listitem">
                    <!-- Image Section -->
                    <div class="civic-group-card-image">
                        <?php if ($hasImg): ?>
                            <img src="<?= $group['image_path'] ?>" alt="<?= $gName ?>" class="civic-group-card-avatar">
                        <?php else: ?>
                            <div class="civic-group-card-placeholder" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Content Section -->
                    <div class="civic-group-card-content">
                        <h3 class="civic-group-card-title">
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>">
                                <?= $gName ?>
                            </a>
                        </h3>

                        <p class="civic-group-card-description"><?= $gDesc ?></p>

                        <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>"
                           class="civic-group-card-btn"
                           aria-label="Visit <?= $gName ?> hub">Visit Hub</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>