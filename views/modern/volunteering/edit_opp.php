<?php
// views/modern/volunteering/edit_opp.php
$hero_title = "Edit Opportunity";
$hero_subtitle = "Update details for this role.";
$hero_gradient = 'htb-hero-gradient-teal';

require __DIR__ . '/../../layouts/header.php';
?>

<style>
/* ============================================
   GOLD STANDARD - Native App Features
   ============================================ */

/* Offline Banner */
.offline-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 10001;
    padding: 12px 20px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    font-size: 0.9rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transform: translateY(-100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.offline-banner.visible {
    transform: translateY(0);
}

/* Content Reveal Animation */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.htb-card {
    animation: fadeInUp 0.4s ease-out;
}

/* Button Press States */
.htb-btn:active,
button:active {
    transform: scale(0.96) !important;
    transition: transform 0.1s ease !important;
}

/* Touch Targets - WCAG 2.1 AA (44px minimum) */
.htb-btn,
button,
input[type="text"],
input[type="date"],
input[type="datetime-local"],
input[type="number"],
select,
textarea {
    min-height: 44px;
}

input[type="text"],
textarea,
select {
    font-size: 16px !important; /* Prevent iOS zoom */
}

/* Focus Visible */
.htb-btn:focus-visible,
button:focus-visible,
a:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible {
    outline: 3px solid rgba(20, 184, 166, 0.5);
    outline-offset: 2px;
}

/* Smooth Scroll */
html {
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}

/* Form Submission Loading State */
.htb-btn.loading {
    pointer-events: none;
    opacity: 0.7;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .htb-container {
        padding: 0 15px 100px 15px;
        max-width: 100% !important;
    }

    div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }

    .htb-btn {
        min-height: 48px;
    }

    table th, table td {
        padding: 8px !important;
    }
}

/* ========================================
   DARK MODE FOR EDIT OPPORTUNITY
   ======================================== */

[data-theme="dark"] .htb-card {
    background: rgba(30, 41, 59, 0.85);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .htb-card h3 {
    color: #f1f5f9;
}

/* Form labels */
[data-theme="dark"] label[style*="font-weight: bold"] {
    color: #e2e8f0 !important;
}

/* Form inputs */
[data-theme="dark"] input[style*="border: 1px solid #ddd"],
[data-theme="dark"] select[style*="border: 1px solid #ddd"],
[data-theme="dark"] textarea[style*="border: 1px solid #ddd"] {
    background: rgba(15, 23, 42, 0.6) !important;
    border-color: rgba(255, 255, 255, 0.15) !important;
    color: #f1f5f9 !important;
}

[data-theme="dark"] select option {
    background: #1e293b;
    color: #f1f5f9;
}

/* Muted text */
[data-theme="dark"] p[style*="color:#666"],
[data-theme="dark"] span[style*="color:#666"] {
    color: #94a3b8 !important;
}

[data-theme="dark"] p[style*="color: #888"],
[data-theme="dark"] p[style*="color:#888"] {
    color: #64748b !important;
}

/* Table */
[data-theme="dark"] table tr[style*="border-bottom: 1px solid #eee"],
[data-theme="dark"] table tr[style*="border-bottom: 1px solid #f9f9f9"] {
    border-color: rgba(255, 255, 255, 0.1) !important;
}

[data-theme="dark"] table td {
    color: #e2e8f0;
}

/* Add Shift Box */
[data-theme="dark"] div[style*="background: #f9fafb"] {
    background: rgba(30, 41, 59, 0.5) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
}

[data-theme="dark"] div[style*="background: #f9fafb"] h5 {
    color: #e2e8f0;
}

[data-theme="dark"] div[style*="background: #f9fafb"] label {
    color: #94a3b8;
}

/* Secondary button */
[data-theme="dark"] .htb-btn-secondary {
    background: rgba(51, 65, 85, 0.6) !important;
    border-color: rgba(255, 255, 255, 0.15) !important;
    color: #e2e8f0 !important;
}
</style>

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