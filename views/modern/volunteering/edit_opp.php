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

<div class="htb-container mte-vol-edit--container">
    <div class="htb-card">
        <div class="htb-card-body">
            <h3>Edit <?= htmlspecialchars($opp['title']) ?></h3>
            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/opp/update" method="POST">
                <?= \Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="opp_id" value="<?= $opp['id'] ?>">

                <div class="mte-vol-edit--form-group">
                    <label class="mte-vol-edit--label">Role Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($opp['title']) ?>" required class="mte-vol-edit--input">
                </div>

                <div class="mte-vol-edit--form-group">
                    <label class="mte-vol-edit--label">Category</label>
                    <select name="category_id" class="mte-vol-edit--input">
                        <option value="">Select Category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $opp['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mte-vol-edit--form-group">
                    <label class="mte-vol-edit--label">Location</label>
                    <input type="text" name="location" value="<?= htmlspecialchars($opp['location']) ?>" required class="mte-vol-edit--input mapbox-location-input-v2">
                    <input type="hidden" name="latitude" value="<?= $opp['latitude'] ?? '' ?>">
                    <input type="hidden" name="longitude" value="<?= $opp['longitude'] ?? '' ?>">
                </div>

                <div class="mte-vol-edit--form-group">
                    <label class="mte-vol-edit--label">Skills</label>
                    <input type="text" name="skills" value="<?= htmlspecialchars($opp['skills_needed']) ?>" placeholder="Comma separated" class="mte-vol-edit--input">
                </div>

                <div class="mte-vol-edit--date-grid">
                    <div>
                        <label class="mte-vol-edit--label">Start Date</label>
                        <input type="date" name="start_date" value="<?= $opp['start_date'] ?>" class="mte-vol-edit--input">
                    </div>
                    <div>
                        <label class="mte-vol-edit--label">End Date</label>
                        <input type="date" name="end_date" value="<?= $opp['end_date'] ?>" class="mte-vol-edit--input">
                    </div>
                </div>

                <div class="mte-vol-edit--form-group">
                    <label class="mte-vol-edit--label">Description</label>
                    <textarea name="description" rows="6" required class="mte-vol-edit--input"><?= htmlspecialchars($opp['description']) ?></textarea>
                </div>

                <div class="mte-vol-edit--btn-row">
                    <button class="htb-btn htb-btn-primary mte-vol-edit--btn-flex">Save Changes</button>
                    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/dashboard" class="htb-btn htb-btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Shifts Management -->
    <div class="htb-card mte-vol-edit--shifts-card">
        <div class="htb-card-body">
            <h3>Manage Shifts</h3>
            <p class="mte-vol-edit--shifts-hint">Add specific time slots for this opportunity.</p>

            <?php if (!empty($shifts)): ?>
                <table class="mte-vol-edit--table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Capacity</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shifts as $shift): ?>
                            <tr>
                                <td>
                                    <?= date('M d, Y', strtotime($shift['start_time'])) ?><br>
                                    <span class="mte-vol-edit--time-sub">
                                        <?= date('h:i A', strtotime($shift['start_time'])) ?> - <?= date('h:i A', strtotime($shift['end_time'])) ?>
                                    </span>
                                </td>
                                <td><?= $shift['capacity'] ?> vols</td>
                                <td>
                                    <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/shift/delete" method="POST" onsubmit="return confirm('Are you sure?');">
                                        <?= \Nexus\Core\Csrf::input() ?>
                                        <input type="hidden" name="shift_id" value="<?= $shift['id'] ?>">
                                        <button class="mte-vol-edit--delete-btn" title="Delete">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="mte-vol-edit--no-shifts">No shifts added yet. This opportunity is "Flexible" by default.</p>
            <?php endif; ?>

            <div class="mte-vol-edit--add-shift-box">
                <h5 class="mte-vol-edit--add-shift-title">Add New Shift</h5>
                <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/volunteering/shift/store" method="POST">
                    <?= \Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="opp_id" value="<?= $opp['id'] ?>">

                    <div class="mte-vol-edit--shift-grid">
                        <div>
                            <label class="mte-vol-edit--shift-label">Start Time</label>
                            <input type="datetime-local" name="start_time" required class="mte-vol-edit--shift-input">
                        </div>
                        <div>
                            <label class="mte-vol-edit--shift-label">End Time</label>
                            <input type="datetime-local" name="end_time" required class="mte-vol-edit--shift-input">
                        </div>
                        <div>
                            <label class="mte-vol-edit--shift-label">Capacity</label>
                            <input type="number" name="capacity" value="1" min="1" required class="mte-vol-edit--shift-input">
                        </div>
                    </div>
                    <button class="htb-btn htb-btn-sm mte-vol-edit--add-shift-btn">+ Add Shift</button>
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
        this.classList.add('pressed');
    });
    btn.addEventListener('pointerup', function() {
        this.classList.remove('pressed');
    });
    btn.addEventListener('pointerleave', function() {
        this.classList.remove('pressed');
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
