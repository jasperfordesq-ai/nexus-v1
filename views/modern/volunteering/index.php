<?php
// Volunteering Index - Refactored to use shared glass-* and nexus-* classes
$pageTitle = "Volunteer Opportunities";
$pageSubtitle = "Make a difference in your community";
$hideHero = true;

Nexus\Core\SEO::setTitle('Volunteer Opportunities - Make a Difference in Your Community');
Nexus\Core\SEO::setDescription('Find meaningful volunteer opportunities in your community. Connect with organizations, share your skills, and make an impact.');

require __DIR__ . '/../../layouts/modern/header.php';
$base = Nexus\Core\TenantContext::getBasePath();
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<!-- Main content wrapper -->
<div class="htb-container-full">
<div id="volunteering-glass-wrapper">

    <!-- Smart Welcome Hero Section -->
    <div class="nexus-welcome-hero">
        <h1 class="nexus-welcome-title">Volunteer Opportunities</h1>
        <p class="nexus-welcome-subtitle">Discover meaningful ways to give back to your community. Share your skills, connect with organizations, and make a real impact.</p>

        <div class="nexus-smart-buttons">
            <a href="<?= $base ?>/volunteering" class="nexus-smart-btn nexus-smart-btn-primary">
                <i class="fa-solid fa-magnifying-glass"></i>
                <span>Browse All</span>
            </a>
            <a href="<?= $base ?>/volunteering/organizations" class="nexus-smart-btn nexus-smart-btn-secondary">
                <i class="fa-solid fa-building-columns"></i>
                <span>Organizations</span>
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= $base ?>/volunteering/my-applications" class="nexus-smart-btn nexus-smart-btn-secondary">
                <i class="fa-solid fa-clipboard-list"></i>
                <span>My Applications</span>
            </a>
            <?php endif; ?>
            <a href="<?= $base ?>/volunteering/dashboard" class="nexus-smart-btn nexus-smart-btn-outline">
                <i class="fa-solid fa-building"></i>
                <span>For Organizations</span>
            </a>
        </div>
    </div>

    <!-- Glass Search Card -->
    <div class="glass-search-card">
        <div class="glass-search-header">
            <h2 class="glass-search-title">Find Opportunities</h2>
            <span class="glass-search-count"><?= count($opportunities ?? []) ?> opportunities available</span>
        </div>

        <form action="" method="GET" class="glass-search-form">
            <div class="glass-search-input-wrap">
                <i class="fa-solid fa-search"></i>
                <input type="search" aria-label="Search" name="q" placeholder="Search by cause, skill, or organization..."
                       value="<?= htmlspecialchars($query ?? '') ?>" class="glass-search-input">
            </div>

            <div class="filter-row">
                <label for="vol-category-select" class="visually-hidden">Filter by category</label>
                <select name="cat" id="vol-category-select" class="glass-select" aria-label="Filter by category">
                    <option value="">All Categories</option>
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= (isset($activeCat) && $activeCat == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>

                <label class="glass-checkbox">
                    <input type="checkbox" name="remote" value="1" <?= (isset($isRemote) && $isRemote) ? 'checked' : '' ?>>
                    <span>Remote Only</span>
                </label>

                <button type="submit" class="btn btn--primary">
                    <i class="fa-solid fa-search"></i>
                    <span>Find</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Section Header -->
    <div class="section-header">
        <i class="fa-solid fa-hands-helping"></i>
        <h2>Available Opportunities</h2>
    </div>

    <!-- Opportunities Grid -->
    <div class="volunteering-grid" id="volunteeringGrid">
        <?php if (!empty($opportunities)): ?>
            <?php foreach ($opportunities as $opp): ?>
                <a href="<?= $base ?>/volunteering/<?= $opp['id'] ?>" class="glass-volunteer-card">
                    <!-- Card Header with Icon -->
                    <div class="card-icon-header">
                        <i class="fa-solid fa-hands-helping"></i>
                    </div>

                    <div class="card-body">
                        <!-- Organization Info -->
                        <div class="org-info">
                            <div class="org-badge">
                                <?= strtoupper(substr($opp['org_name'] ?? 'O', 0, 1)) ?>
                            </div>
                            <div class="org-details">
                                <div class="org-name"><?= htmlspecialchars($opp['org_name'] ?? 'Organization') ?></div>
                                <div class="org-location">
                                    <i class="fa-solid fa-location-dot"></i>
                                    <?= htmlspecialchars($opp['location'] ?? 'Remote') ?>
                                </div>
                            </div>
                        </div>

                        <h3 class="card-title"><?= htmlspecialchars($opp['title']) ?></h3>

                        <p class="card-desc"><?= htmlspecialchars(substr($opp['description'] ?? '', 0, 150)) ?>...</p>

                        <?php if (!empty($opp['skills_needed'])): ?>
                            <div class="skill-tags">
                                <?php foreach (array_slice(explode(',', $opp['skills_needed']), 0, 4) as $skill): ?>
                                    <span class="skill-tag"><?= trim(htmlspecialchars($skill)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-footer">
                        <?php if (!empty($opp['credits_offered'])): ?>
                            <span class="credits-badge">
                                <i class="fa-solid fa-coins"></i>
                                <?= $opp['credits_offered'] ?> credits
                            </span>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>

                        <span class="view-link">
                            View Details <i class="fa-solid fa-arrow-right"></i>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="glass-empty-state">
                <div class="empty-icon">🔍</div>
                <h3 class="empty-title">No opportunities found</h3>
                <p class="empty-text">Check back later or adjust your search criteria.</p>
                <a href="<?= $base ?>/volunteering" class="btn btn--primary">
                    View All Opportunities
                </a>
            </div>
        <?php endif; ?>
    </div>

</div><!-- #volunteering-glass-wrapper -->
</div>

<script>
// Offline Indicator
(function initOfflineIndicator() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    function handleOffline() {
        banner.classList.add('visible');
        if (navigator.vibrate) navigator.vibrate(100);
    }

    function handleOnline() {
        banner.classList.remove('visible');
    }

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    if (!navigator.onLine) {
        handleOffline();
    }
})();

// Button Press States
document.querySelectorAll('.nexus-smart-btn, .btn--primary, .view-link').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.96)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});

// Card hover effects
document.querySelectorAll('.glass-volunteer-card').forEach(card => {
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
