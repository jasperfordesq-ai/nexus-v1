<?php
// views/modern/volunteering/edit_opp.php
$hero_title = "Edit Opportunity";
$hero_subtitle = "Update details for this role.";
$hero_gradient = 'htb-hero-gradient-teal';

require __DIR__ . '/../../layouts/header.php';
?>


<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

<div class="htb-container" style="max-width: 600px; margin-top: 50px;">
    <div class="htb-card">
        <div class="htb-card-body">
            <h3>Edit <?= htmlspecialchars($opp['title']) ?></h3>
            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/opp/update" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="opp_id" value="<?= $opp['id'] ?>">

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Role Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($opp['title']) ?>" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Category</label>
                    <select name="category_id" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                        <option value="">Select Category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $opp['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Location</label>
                    <input type="text" name="location" value="<?= htmlspecialchars($opp['location']) ?>" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;" class="mapbox-location-input-v2">
                    <input type="hidden" name="latitude" value="<?= $opp['latitude'] ?? '' ?>">
                    <input type="hidden" name="longitude" value="<?= $opp['longitude'] ?? '' ?>">
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Skills</label>
                    <input type="text" name="skills" value="<?= htmlspecialchars($opp['skills_needed']) ?>" placeholder="Comma separated" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Start Date</label>
                        <input type="date" name="start_date" value="<?= $opp['start_date'] ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">End Date</label>
                        <input type="date" name="end_date" value="<?= $opp['end_date'] ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">Description</label>
                    <textarea name="description" rows="6" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;"><?= htmlspecialchars($opp['description']) ?></textarea>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button class="htb-btn htb-btn-primary" style="flex: 1;">Save Changes</button>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/dashboard" class="htb-btn htb-btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Shifts Management -->
    <div class="htb-card" style="margin-top: 30px;">
        <div class="htb-card-body">
            <h3>Manage Shifts</h3>
            <p style="color:#666; font-size:0.9rem;">Add specific time slots for this opportunity.</p>

            <?php if (!empty($shifts)): ?>
                <table style="width: 100%; text-align: left; border-collapse: collapse; margin-bottom: 20px;">
                    <thead>
                        <tr style="border-bottom: 1px solid #eee;">
                            <th style="padding: 10px;">Time</th>
                            <th style="padding: 10px;">Capacity</th>
                            <th style="padding: 10px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shifts as $shift): ?>
                            <tr style="border-bottom: 1px solid #f9f9f9;">
                                <td style="padding: 10px;">
                                    <?= date('M d, Y', strtotime($shift['start_time'])) ?><br>
                                    <span style="color:#666; font-size:0.85rem;">
                                        <?= date('h:i A', strtotime($shift['start_time'])) ?> - <?= date('h:i A', strtotime($shift['end_time'])) ?>
                                    </span>
                                </td>
                                <td style="padding: 10px;"><?= $shift['capacity'] ?> vols</td>
                                <td style="padding: 10px;">
                                    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/shift/delete" method="POST" onsubmit="return confirm('Are you sure?');">
                                        <?= \Nexus\Core\Csrf::input() ?>
                                        <input type="hidden" name="shift_id" value="<?= $shift['id'] ?>">
                                        <button style="border:none; background:none; cursor:pointer; color:#ef4444;" title="Delete">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="margin-bottom: 20px; font-style: italic; color: #888;">No shifts added yet. This opportunity is "Flexible" by default.</p>
            <?php endif; ?>

            <div style="background: #f9fafb; padding: 15px; border-radius: 6px; border: 1px solid #eee;">
                <h5 style="margin-top:0;">Add New Shift</h5>
                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/shift/store" method="POST">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="opp_id" value="<?= $opp['id'] ?>">

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                        <div>
                            <label style="display: block; font-size: 0.8rem; font-weight: bold;">Start Time</label>
                            <input type="datetime-local" name="start_time" required style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.8rem; font-weight: bold;">End Time</label>
                            <input type="datetime-local" name="end_time" required style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.8rem; font-weight: bold;">Capacity</label>
                            <input type="number" name="capacity" value="1" min="1" required style="width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                    </div>
                    <button class="htb-btn htb-btn-sm" style="background: #4f46e5; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;">+ Add Shift</button>
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

// Button Press States
document.querySelectorAll('.htb-btn, button').forEach(btn => {
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