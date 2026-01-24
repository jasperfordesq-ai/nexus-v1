<?php
/**
 * Template D: Form Page - Edit Volunteer Opportunity
 *
 * Purpose: Edit existing volunteer opportunity details and manage shifts
 * Features: Offline detection, form validation, shift scheduling
 * WCAG 2.1 AA: 44px minimum touch targets, keyboard navigation, focus states
 */

// views/modern/volunteering/edit_opp.php
$hero_title = "Edit Opportunity";
$hero_subtitle = "Update details for this role.";
$hero_gradient = 'htb-hero-gradient-teal';

require __DIR__ . '/../../layouts/header.php';

$basePath = \Nexus\Core\TenantContext::getBasePath();
?>
<link rel="stylesheet" href="<?= $basePath ?>/assets/css/purged/civicone-volunteering-edit-opp.min.css">

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container edit-opp-container">
    <div class="htb-card">
        <div class="htb-card-body">
            <h3>Edit <?= htmlspecialchars($opp['title']) ?></h3>
            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/opp/update" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="opp_id" value="<?= $opp['id'] ?>">

                <div class="form-field">
                    <label class="form-field-label">Role Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($opp['title']) ?>" required class="form-input mapbox-location-input-v2">
                </div>

                <div class="form-field">
                    <label class="form-field-label">Category</label>
                    <select name="category_id" class="form-input">
                        <option value="">Select Category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $opp['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label class="form-field-label">Location</label>
                    <input type="text" name="location" value="<?= htmlspecialchars($opp['location']) ?>" required class="form-input mapbox-location-input-v2">
                    <input type="hidden" name="latitude" value="<?= $opp['latitude'] ?? '' ?>">
                    <input type="hidden" name="longitude" value="<?= $opp['longitude'] ?? '' ?>">
                </div>

                <div class="form-field">
                    <label class="form-field-label">Skills</label>
                    <input type="text" name="skills" value="<?= htmlspecialchars($opp['skills_needed']) ?>" placeholder="Comma separated" class="form-input">
                </div>

                <div class="date-grid">
                    <div>
                        <label class="form-field-label">Start Date</label>
                        <input type="date" name="start_date" value="<?= $opp['start_date'] ?>" class="form-input">
                    </div>
                    <div>
                        <div>
                            <label class="form-field-label-sm">End Time</label>
                            <input type="datetime-local" name="end_time" required class="form-input-sm">
                        </div>
                        <div>
                            <label class="form-field-label-sm">Capacity</label>
                            <input type="number" name="capacity" value="1" min="1" required class="form-input-sm">
                        </div>
                    </div>
                    <button class="htb-btn htb-btn-sm btn-add-shift">+ Add Shift</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// GOLD STANDARD - Native App Features
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

// Form Submission Offline Protection
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to save changes.');
            return;
        }
    });
});

// Button Press States - Handled by CSS :active pseudo-class

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

<script src="<?= $basePath ?>/assets/js/civicone-volunteering-edit-opp.js"></script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>
