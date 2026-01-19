<?php
// Volunteering Index - Gold Standard Holographic Glassmorphism 2025
$pageTitle = "Volunteer Opportunities";
$pageSubtitle = "Make a difference in your community";
$hideHero = true;

Nexus\Core\SEO::setTitle('Volunteer Opportunities - Make a Difference in Your Community');
Nexus\Core\SEO::setDescription('Find meaningful volunteer opportunities in your community. Connect with organizations, share your skills, and make an impact.');

require __DIR__ . '/../../layouts/modern/header.php';
?>


<!-- Holographic Background -->
<div class="vol-page-bg"></div>

<!-- Offline Banner -->
<div class="vol-offline-banner" id="offlineBanner">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="vol-glass-container">

    <!-- Hero Section -->
    <div class="vol-hero">
        <h1 class="vol-hero-title">Volunteer Opportunities</h1>
        <p class="vol-hero-subtitle">Discover meaningful ways to give back to your community. Share your skills, connect with organizations, and make a real impact.</p>

        <div class="vol-actions">
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering" class="vol-action-btn vol-action-btn-primary">
                <i class="fa-solid fa-magnifying-glass"></i>
                <span>Browse All</span>
            </a>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/organizations" class="vol-action-btn vol-action-btn-secondary">
                <i class="fa-solid fa-building-columns"></i>
                <span>Organizations</span>
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/my-applications" class="vol-action-btn vol-action-btn-secondary">
                <i class="fa-solid fa-clipboard-list"></i>
                <span>My Applications</span>
            </a>
            <?php endif; ?>
            <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/dashboard" class="vol-action-btn vol-action-btn-secondary">
                <i class="fa-solid fa-building"></i>
                <span>For Organizations</span>
            </a>
        </div>
    </div>

    <!-- Search Section -->
    <div class="vol-search-section">
        <div class="vol-search-header">
            <h2 class="vol-search-title">Find Opportunities</h2>
            <span class="vol-search-count"><?= count($opportunities ?? []) ?> opportunities available</span>
        </div>

        <form action="" method="GET" class="vol-search-form">
            <div class="vol-search-input-wrap">
                <i class="fa-solid fa-search"></i>
                <input type="search" aria-label="Search" name="q" placeholder="Search by cause, skill, or organization..."
                       value="<?= htmlspecialchars($query ?? '') ?>" class="vol-search-input">
            </div>

            <select name="cat" class="vol-search-select">
                <option value="">All Categories</option>
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= (isset($activeCat) && $activeCat == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>

            <label class="vol-search-checkbox">
                <input type="checkbox" name="remote" value="1" <?= (isset($isRemote) && $isRemote) ? 'checked' : '' ?>>
                <span>Remote Only</span>
            </label>

            <button type="submit" class="vol-search-btn">
                <i class="fa-solid fa-search"></i>
                <span>Find</span>
            </button>
        </form>
    </div>

    <!-- Opportunities Grid -->
    <div class="vol-grid">
        <?php if (!empty($opportunities)): ?>
            <?php foreach ($opportunities as $opp): ?>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/<?= $opp['id'] ?>" class="vol-card">
                    <div class="vol-card-header">
                        <i class="fa-solid fa-hands-helping vol-card-header-icon"></i>
                    </div>

                    <div class="vol-card-body">
                        <div class="vol-org-row">
                            <div class="vol-org-badge">
                                <?= strtoupper(substr($opp['org_name'] ?? 'O', 0, 1)) ?>
                            </div>
                            <div class="vol-org-info">
                                <div class="vol-org-name"><?= htmlspecialchars($opp['org_name'] ?? 'Organization') ?></div>
                                <div class="vol-org-location">
                                    <i class="fa-solid fa-location-dot"></i>
                                    <?= htmlspecialchars($opp['location'] ?? 'Remote') ?>
                                </div>
                            </div>
                        </div>

                        <h3 class="vol-card-title"><?= htmlspecialchars($opp['title']) ?></h3>

                        <p class="vol-card-desc"><?= htmlspecialchars(substr($opp['description'] ?? '', 0, 150)) ?>...</p>

                        <?php if (!empty($opp['skills_needed'])): ?>
                            <div class="vol-skills">
                                <?php foreach (array_slice(explode(',', $opp['skills_needed']), 0, 4) as $skill): ?>
                                    <span class="vol-skill-tag"><?= trim(htmlspecialchars($skill)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="vol-card-footer">
                            <?php if (!empty($opp['credits_offered'])): ?>
                                <span class="vol-credits">
                                    <i class="fa-solid fa-coins"></i>
                                    <?= $opp['credits_offered'] ?> credits
                                </span>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>

                            <span class="vol-view-link">
                                View Details <i class="fa-solid fa-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="vol-empty">
                <div class="vol-empty-icon">🔍</div>
                <h3 class="vol-empty-title">No opportunities found</h3>
                <p class="vol-empty-text">Check back later or adjust your search criteria.</p>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering" class="vol-action-btn vol-action-btn-primary">
                    View All Opportunities
                </a>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
// Offline Detection
(function() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function updateStatus() {
        if (!navigator.onLine) {
            banner.classList.add('visible');
        } else {
            banner.classList.remove('visible');
        }
    }

    window.addEventListener('online', updateStatus);
    window.addEventListener('offline', updateStatus);
    updateStatus();
})();

// Card hover effects with haptic feedback
document.querySelectorAll('.vol-card').forEach(card => {
    card.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.98)';
    });

    card.addEventListener('pointerup', function() {
        this.style.transform = '';
    });

    card.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
