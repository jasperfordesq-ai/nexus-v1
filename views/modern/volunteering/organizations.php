<?php
// Organizations Listing - Glassmorphism 2025
$pageTitle = "Organizations";
$pageSubtitle = "Find volunteer organizations in your community";
$hideHero = true;

Nexus\Core\SEO::setTitle('Volunteer Organizations - Find Causes You Care About');
Nexus\Core\SEO::setDescription('Browse volunteer organizations in your community. Join teams, discover causes, and make a meaningful impact.');

require __DIR__ . '/../../layouts/modern/header.php';

$base = \Nexus\Core\TenantContext::getBasePath();
$hasTimebanking = $hasTimebanking ?? \Nexus\Core\TenantContext::hasFeature('wallet');
?>

<div class="htb-container-full">
<div id="org-glass-wrapper">


    <!-- Page Header -->
    <div class="org-page-header">
        <a href="<?= $base ?>/volunteering" class="org-back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Opportunities
        </a>
        <h1 class="org-page-title">
            <i class="fa-solid fa-building-columns"></i>
            Organizations
        </h1>
        <p class="org-page-subtitle">Discover groups making a difference in your community</p>
    </div>

    <!-- Search Card -->
    <div class="glass-search-card">
        <form class="search-form" method="GET" action="<?= $base ?>/volunteering/organizations">
            <div class="search-input-wrapper">
                <i class="fa-solid fa-search"></i>
                <input type="text"
                       name="q"
                       class="glass-search-input"
                       placeholder="Search organizations by name or cause..."
                       value="<?= htmlspecialchars($query ?? '') ?>">
            </div>
            <button type="submit" class="glass-btn-primary">
                <i class="fa-solid fa-search"></i>
                Search
            </button>
        </form>
    </div>

    <?php if (!empty($query)): ?>
        <p class="org-result-count">
            Found <strong><?= count($organizations) ?></strong> organization<?= count($organizations) !== 1 ? 's' : '' ?>
            matching "<?= htmlspecialchars($query) ?>"
        </p>
    <?php endif; ?>

    <?php if (empty($organizations)): ?>
        <!-- Empty State -->
        <div class="org-empty-state">
            <div class="org-empty-icon">
                <i class="fa-solid fa-building-circle-xmark"></i>
            </div>
            <h2 class="org-empty-title">No Organizations Found</h2>
            <p class="org-empty-text">
                <?php if (!empty($query)): ?>
                    No organizations match your search. Try different keywords.
                <?php else: ?>
                    There are no organizations yet. Be the first to register one!
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <!-- Organizations Grid -->
        <div class="org-grid">
            <?php foreach ($organizations as $org): ?>
                <a href="<?= $base ?>/volunteering/organization/<?= $org['id'] ?>" class="org-card">
                    <div class="org-card-header">
                        <div class="org-logo">
                            <?php if (!empty($org['logo'])): ?>
                                <img src="<?= htmlspecialchars($org['logo']) ?>" loading="lazy" alt="<?= htmlspecialchars($org['name']) ?>">
                            <?php else: ?>
                                <?= strtoupper(substr($org['name'], 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="org-card-title-area">
                            <h3 class="org-card-name"><?= htmlspecialchars($org['name']) ?></h3>
                            <div class="org-card-owner">
                                <i class="fa-solid fa-user"></i>
                                <?= htmlspecialchars($org['owner_name'] ?? 'Unknown') ?>
                            </div>
                        </div>
                    </div>
                    <div class="org-card-body">
                        <p class="org-card-description">
                            <?= htmlspecialchars(substr($org['description'], 0, 200)) ?><?= strlen($org['description']) > 200 ? '...' : '' ?>
                        </p>
                    </div>
                    <div class="org-card-stats">
                        <div class="org-stat">
                            <i class="fa-solid fa-briefcase"></i>
                            <span class="org-stat-value"><?= (int)($org['opportunity_count'] ?? 0) ?></span>
                            Opportunities
                        </div>
                        <?php if ($hasTimebanking && isset($org['member_count'])): ?>
                            <div class="org-stat">
                                <i class="fa-solid fa-users"></i>
                                <span class="org-stat-value"><?= (int)$org['member_count'] ?></span>
                                Members
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
</div>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
