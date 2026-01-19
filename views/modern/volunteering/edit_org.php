<?php
// views/modern/volunteering/edit_org.php
$hero_title = "Edit Organisation";
$hero_subtitle = "Update your organisation profile.";
$hero_gradient = 'htb-hero-gradient-teal';
$hideHero = true;

require __DIR__ . '/../../layouts/header.php';
?>


<!-- Animated Background -->
<div class="edit-org-glass-bg"></div>

<!-- Offline Banner -->
<div class="edit-org-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="edit-org-container">

    <!-- Back Link -->
    <div style="margin-bottom: 24px;">
        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/dashboard" class="edit-org-back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Dashboard
        </a>
    </div>

    <!-- Main Card -->
    <div class="edit-org-card">
        <!-- Card Header -->
        <div class="edit-org-card-header">
            <div class="edit-org-card-icon">
                <i class="fa-solid fa-building"></i>
            </div>
            <h1 class="edit-org-card-title">Edit <?= htmlspecialchars($org['name']) ?></h1>
        </div>

        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/org/update" method="POST" id="editOrgForm">
            <?= \Nexus\Core\Csrf::input() ?>
            <input type="hidden" name="org_id" value="<?= $org['id'] ?>">

            <!-- Organization Name -->
            <div class="edit-org-form-group">
                <label class="edit-org-label" for="org-name">
                    <i class="fa-solid fa-signature" style="margin-right: 6px; opacity: 0.6;"></i>
                    Organization Name
                </label>
                <input
                    type="text"
                    name="name"
                    id="org-name"
                    value="<?= htmlspecialchars($org['name']) ?>"
                    required
                    class="edit-org-input"
                    placeholder="Enter organization name"
                >
            </div>

            <!-- Contact Email -->
            <div class="edit-org-form-group">
                <label class="edit-org-label" for="org-email">
                    <i class="fa-solid fa-envelope" style="margin-right: 6px; opacity: 0.6;"></i>
                    Contact Email
                </label>
                <input
                    type="email"
                    name="email"
                    id="org-email"
                    value="<?= htmlspecialchars($org['contact_email']) ?>"
                    required
                    class="edit-org-input"
                    placeholder="org@example.com"
                >
            </div>

            <!-- Website -->
            <div class="edit-org-form-group">
                <label class="edit-org-label" for="org-website">
                    <i class="fa-solid fa-globe" style="margin-right: 6px; opacity: 0.6;"></i>
                    Website
                </label>
                <input
                    type="url"
                    name="website"
                    id="org-website"
                    value="<?= htmlspecialchars($org['website']) ?>"
                    class="edit-org-input"
                    placeholder="https://..."
                >
            </div>

            <!-- Description -->
            <div class="edit-org-form-group">
                <label class="edit-org-label" for="org-description">
                    <i class="fa-solid fa-align-left" style="margin-right: 6px; opacity: 0.6;"></i>
                    Description
                </label>
                <textarea
                    name="description"
                    id="org-description"
                    rows="5"
                    required
                    class="edit-org-textarea"
                    placeholder="Describe your organization's mission and activities..."
                ><?= htmlspecialchars($org['description']) ?></textarea>
            </div>

            <?php if (Nexus\Core\TenantContext::hasFeature('wallet')): ?>
                <!-- Auto-Pay Feature Box -->
                <div class="edit-org-feature-box">
                    <label class="edit-org-feature-label">
                        <input
                            type="checkbox"
                            name="auto_pay"
                            value="1"
                            <?= $org['auto_pay_enabled'] ? 'checked' : '' ?>
                            class="edit-org-feature-checkbox"
                        >
                        <div>
                            <p class="edit-org-feature-title">
                                <i class="fa-solid fa-wand-magic-sparkles" style="margin-right: 6px;"></i>
                                Enable Auto-Pay Time Credits
                            </p>
                            <p class="edit-org-feature-description">
                                When enabled, approving hours will automatically transfer Time Credits from your personal wallet to the volunteer's wallet (1 Hour = 1 Credit).
                            </p>
                        </div>
                    </label>
                </div>

                <!-- Quick Actions -->
                <div class="edit-org-quick-actions">
                    <p class="edit-org-quick-actions-title">
                        <i class="fa-solid fa-bolt" style="margin-right: 4px;"></i>
                        Quick Actions
                    </p>
                    <div class="edit-org-quick-actions-grid">
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/wallet" class="edit-org-quick-btn edit-org-quick-btn--primary">
                            <i class="fa-solid fa-wallet"></i>
                            Org Wallet
                        </a>
                        <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/organizations/<?= $org['id'] ?>/members" class="edit-org-quick-btn edit-org-quick-btn--secondary">
                            <i class="fa-solid fa-users"></i>
                            Members
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Form Actions -->
            <div class="edit-org-form-actions">
                <button type="submit" class="edit-org-btn edit-org-btn--primary" id="submitBtn">
                    <i class="fa-solid fa-check"></i>
                    Save Changes
                </button>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/dashboard" class="edit-org-btn edit-org-btn--secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>

</div>

<script>
// ============================================
// EDIT ORGANIZATION - Enhanced UX
// ============================================

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

// Form Submission Protection
(function initFormProtection() {
    const form = document.getElementById('editOrgForm');
    const submitBtn = document.getElementById('submitBtn');

    if (!form || !submitBtn) return;

    form.addEventListener('submit', function(e) {
        // Offline check
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to save changes.');
            return;
        }

        // Prevent double submission
        submitBtn.classList.add('loading');
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    });
})();

// Button Press States
document.querySelectorAll('.edit-org-btn, .edit-org-quick-btn').forEach(btn => {
    btn.addEventListener('pointerdown', function() {
        this.style.transform = 'scale(0.97)';
    });
    btn.addEventListener('pointerup', function() {
        this.style.transform = '';
    });
    btn.addEventListener('pointerleave', function() {
        this.style.transform = '';
    });
});

// Dynamic Theme Color
(function initDynamicThemeColor() {
    const metaTheme = document.querySelector('meta[name="theme-color"]');
    if (!metaTheme) {
        const meta = document.createElement('meta');
        meta.name = 'theme-color';
        meta.content = '#14b8a6';
        document.head.appendChild(meta);
    }
})();
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
