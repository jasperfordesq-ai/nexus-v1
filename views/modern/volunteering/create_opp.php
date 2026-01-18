<?php
$hero_title = "Post Volunteer Opportunity";
$hero_subtitle = "Recruit volunteers for your cause.";
$hero_image = "/assets/img/hero_volunteering.jpg";

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
.form-control,
input[type="text"],
input[type="date"],
select,
textarea {
    min-height: 44px;
}

.form-control,
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
    position: relative;
}
.htb-btn.loading::after {
    content: '';
    position: absolute;
    width: 18px;
    height: 18px;
    border: 2px solid transparent;
    border-top-color: currentColor;
    border-radius: 50%;
    animation: btnSpinner 0.8s linear infinite;
    margin-left: 8px;
}
@keyframes btnSpinner {
    to { transform: rotate(360deg); }
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .htb-container {
        padding: 0 15px 100px 15px;
    }

    .htb-btn {
        min-height: 48px;
    }

    .row {
        flex-direction: column;
    }

    .col-md-6, .col-md-8 {
        width: 100%;
    }
}

/* ========================================
   DARK MODE FOR CREATE OPPORTUNITY
   ======================================== */

[data-theme="dark"] .htb-card {
    background: rgba(30, 41, 59, 0.85);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

[data-theme="dark"] .htb-card-header {
    background: rgba(15, 23, 42, 0.6);
    border-color: rgba(255, 255, 255, 0.1);
    color: #f1f5f9;
}

[data-theme="dark"] .htb-label {
    color: #e2e8f0;
}

[data-theme="dark"] .form-control {
    background: rgba(15, 23, 42, 0.6) !important;
    border-color: rgba(255, 255, 255, 0.15) !important;
    color: #f1f5f9 !important;
}

[data-theme="dark"] .form-control::placeholder {
    color: #64748b;
}

[data-theme="dark"] .form-control option {
    background: #1e293b;
    color: #f1f5f9;
}

[data-theme="dark"] .form-text {
    color: #94a3b8 !important;
}

[data-theme="dark"] .htb-btn-outline {
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

<div class="htb-container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="htb-card">
                <div class="htb-card-header">
                    Create New Opportunity
                </div>
                <div class="htb-card-body">
                    <form action="<?= \Nexus\Core\TenantContext::getBasePath() ?>/volunteering/opp/store" method="POST">
                        <?= \Nexus\Core\Csrf::input() ?>

                        <!-- Organization Selection -->
                        <div class="mb-4">
                            <label class="htb-label" for="org_id">Organization</label>
                            <select name="org_id" id="org_id" class="form-control" required>
                                <?php foreach ($myOrgs as $org): ?>
                                    <option value="<?= $org['id'] ?>" <?= ($preselectedOrgId == $org['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($org['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Select which organization this opportunity belongs to.</div>
                        </div>

                        <!-- Title -->
                        <div class="mb-4">
                            <label class="htb-label" for="title">Opportunity Title</label>
                            <input type="text" name="title" id="title" class="form-control" placeholder="e.g. Weekend Tree Planting" required>
                        </div>

                        <!-- Category -->
                        <div class="mb-4">
                            <label class="htb-label" for="category_id">Category</label>
                            <select name="category_id" id="category_id" class="form-control" required>
                                <option value="" disabled selected>Select a category...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Description -->
                        <div class="mb-4">
                            <label class="htb-label" for="description">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="5" placeholder="Describe the role, responsibilities, and impact..." required></textarea>
                        </div>

                        <!-- Location -->
                        <div class="mb-4">
                            <label class="htb-label" for="location">Location</label>
                            <input type="text" name="location" id="location" class="form-control mapbox-location-input-v2" placeholder="e.g. Central Park or 'Remote'" required>
                            <input type="hidden" name="latitude">
                            <input type="hidden" name="longitude">
                        </div>

                        <!-- Skills -->
                        <div class="mb-4">
                            <label class="htb-label">Required Skills (Optional)</label>
                            <input type="text" name="skills" class="form-control" placeholder="e.g. Gardening, Teamwork">
                        </div>

                        <!-- Dates -->
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="htb-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="htb-label">End Date (Optional)</label>
                                <input type="date" name="end_date" class="form-control">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/volunteering/dashboard" class="htb-btn htb-btn-outline">Cancel</a>
                            <button type="submit" class="htb-btn htb-btn-primary">Create Opportunity</button>
                        </div>

                    </form>
                </div>
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

// Form Submission Loading State & Offline Protection
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        if (!navigator.onLine) {
            e.preventDefault();
            alert('You are offline. Please connect to the internet to create opportunities.');
            return;
        }
        const btn = form.querySelector('button[type="submit"], .htb-btn-primary');
        if (btn) {
            btn.classList.add('loading');
            btn.innerHTML = 'Creating...';
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

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#14b8a6');
        }
    }

    const observer = new MutationObserver(updateThemeColor);
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });

    updateThemeColor();
})();
</script>

<?php require __DIR__ . '/../../layouts/footer.php'; ?>