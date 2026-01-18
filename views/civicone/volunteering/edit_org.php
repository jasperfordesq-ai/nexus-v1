<?php
// views/modern/volunteering/edit_org.php
$hero_title = "Edit Organisation";
$hero_subtitle = "Update your organisation profile.";
$hero_gradient = 'htb-hero-gradient-teal';
$hideHero = true;

require __DIR__ . '/../../layouts/header.php';
?>

<style>
/* ============================================
   EDIT ORGANIZATION - GLASSMORPHISM THEME
   ============================================ */

/* Animated Background */
.edit-org-glass-bg {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: -1;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%, #f8fafc 100%);
}

.edit-org-glass-bg::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background:
        radial-gradient(ellipse at 25% 30%, rgba(20, 184, 166, 0.12) 0%, transparent 50%),
        radial-gradient(ellipse at 75% 25%, rgba(79, 70, 229, 0.1) 0%, transparent 45%),
        radial-gradient(ellipse at 50% 80%, rgba(139, 92, 246, 0.08) 0%, transparent 50%);
    animation: editOrgFloat 20s ease-in-out infinite;
}

[data-theme="dark"] .edit-org-glass-bg {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
}

[data-theme="dark"] .edit-org-glass-bg::before {
    background:
        radial-gradient(ellipse at 25% 30%, rgba(20, 184, 166, 0.2) 0%, transparent 50%),
        radial-gradient(ellipse at 75% 25%, rgba(79, 70, 229, 0.15) 0%, transparent 45%);
}

@keyframes editOrgFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-1%, 1%) scale(1.02); }
}

/* Container */
.edit-org-container {
    padding: 120px 24px 40px 24px;
    position: relative;
    z-index: 20;
    max-width: 700px;
    margin: 0 auto;
}

/* Back Link */
.edit-org-back-link {
    text-decoration: none;
    color: var(--htb-text-main, #374151);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.6);
    padding: 10px 18px;
    border-radius: 12px;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.2s ease;
    border: 1px solid rgba(255, 255, 255, 0.5);
}

.edit-org-back-link:hover {
    background: rgba(255, 255, 255, 0.8);
    transform: translateX(-2px);
}

[data-theme="dark"] .edit-org-back-link {
    background: rgba(30, 41, 59, 0.6);
    border-color: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
}

[data-theme="dark"] .edit-org-back-link:hover {
    background: rgba(30, 41, 59, 0.8);
}

/* Glass Card */
.edit-org-card {
    background: linear-gradient(135deg,
        rgba(255, 255, 255, 0.85) 0%,
        rgba(255, 255, 255, 0.7) 100%);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.5);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.1);
    padding: 30px;
    animation: fadeInUp 0.4s ease-out;
}

[data-theme="dark"] .edit-org-card {
    background: linear-gradient(135deg,
        rgba(30, 41, 59, 0.85) 0%,
        rgba(30, 41, 59, 0.7) 100%);
    border-color: rgba(255, 255, 255, 0.1);
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Card Header */
.edit-org-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

[data-theme="dark"] .edit-org-card-header {
    border-bottom-color: rgba(255, 255, 255, 0.1);
}

.edit-org-card-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #14b8a6, #0d9488);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.edit-org-card-title {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 700;
    color: #111827;
}

[data-theme="dark"] .edit-org-card-title {
    color: #f1f5f9;
}

/* Form Group */
.edit-org-form-group {
    margin-bottom: 22px;
}

.edit-org-label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    font-size: 0.9rem;
    color: #374151;
}

[data-theme="dark"] .edit-org-label {
    color: #e2e8f0;
}

/* Form Inputs */
.edit-org-input,
.edit-org-textarea {
    width: 100%;
    padding: 14px 16px;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    font-size: 1rem;
    font-family: inherit;
    transition: all 0.2s ease;
    background: rgba(255, 255, 255, 0.8);
    color: #1f2937;
    min-height: 44px;
    box-sizing: border-box;
}

.edit-org-input:focus,
.edit-org-textarea:focus {
    outline: none;
    border-color: #14b8a6;
    box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.15);
    background: #fff;
}

[data-theme="dark"] .edit-org-input,
[data-theme="dark"] .edit-org-textarea {
    background: rgba(15, 23, 42, 0.5);
    border-color: rgba(255, 255, 255, 0.15);
    color: #f1f5f9;
}

[data-theme="dark"] .edit-org-input:focus,
[data-theme="dark"] .edit-org-textarea:focus {
    border-color: #14b8a6;
    background: rgba(15, 23, 42, 0.7);
}

.edit-org-textarea {
    resize: vertical;
    line-height: 1.6;
}

/* Feature Box - Auto Pay */
.edit-org-feature-box {
    margin-bottom: 22px;
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.08), rgba(168, 85, 247, 0.05));
    border: 1px solid rgba(139, 92, 246, 0.2);
    padding: 18px;
    border-radius: 14px;
}

[data-theme="dark"] .edit-org-feature-box {
    background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(168, 85, 247, 0.1));
    border-color: rgba(139, 92, 246, 0.3);
}

.edit-org-feature-label {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    cursor: pointer;
}

.edit-org-feature-checkbox {
    margin-top: 3px;
    width: 18px;
    height: 18px;
    accent-color: #8b5cf6;
}

.edit-org-feature-title {
    font-weight: 700;
    color: #7c3aed;
    margin: 0 0 6px 0;
    font-size: 0.95rem;
}

[data-theme="dark"] .edit-org-feature-title {
    color: #c4b5fd;
}

.edit-org-feature-description {
    margin: 0;
    font-size: 0.85rem;
    color: #6b21a8;
    line-height: 1.5;
}

[data-theme="dark"] .edit-org-feature-description {
    color: #d8b4fe;
}

/* Quick Actions Box */
.edit-org-quick-actions {
    margin-bottom: 22px;
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.08), rgba(99, 102, 241, 0.05));
    border: 1px solid rgba(79, 70, 229, 0.15);
    padding: 18px;
    border-radius: 14px;
}

[data-theme="dark"] .edit-org-quick-actions {
    background: linear-gradient(135deg, rgba(79, 70, 229, 0.15), rgba(99, 102, 241, 0.1));
    border-color: rgba(79, 70, 229, 0.25);
}

.edit-org-quick-actions-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: #6366f1;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 0 0 12px 0;
}

[data-theme="dark"] .edit-org-quick-actions-title {
    color: #a5b4fc;
}

.edit-org-quick-actions-grid {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Quick Action Button */
.edit-org-quick-btn {
    flex: 1;
    min-width: 140px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 18px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    transition: all 0.2s ease;
    min-height: 44px;
}

.edit-org-quick-btn--primary {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
}

.edit-org-quick-btn--primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(79, 70, 229, 0.35);
}

.edit-org-quick-btn--secondary {
    background: rgba(255, 255, 255, 0.8);
    color: #374151;
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.edit-org-quick-btn--secondary:hover {
    background: rgba(255, 255, 255, 0.95);
    transform: translateY(-2px);
}

[data-theme="dark"] .edit-org-quick-btn--secondary {
    background: rgba(51, 65, 85, 0.6);
    border-color: rgba(255, 255, 255, 0.1);
    color: #e2e8f0;
}

[data-theme="dark"] .edit-org-quick-btn--secondary:hover {
    background: rgba(51, 65, 85, 0.8);
}

/* Form Actions */
.edit-org-form-actions {
    display: flex;
    gap: 12px;
    margin-top: 28px;
    padding-top: 20px;
    border-top: 1px solid rgba(0, 0, 0, 0.06);
}

[data-theme="dark"] .edit-org-form-actions {
    border-top-color: rgba(255, 255, 255, 0.1);
}

/* Buttons */
.edit-org-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 24px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    text-decoration: none;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
    min-height: 48px;
}

.edit-org-btn:active {
    transform: scale(0.97);
}

.edit-org-btn--primary {
    flex: 1;
    background: linear-gradient(135deg, #14b8a6, #0d9488);
    color: white;
    box-shadow: 0 4px 12px rgba(20, 184, 166, 0.25);
}

.edit-org-btn--primary:hover {
    box-shadow: 0 6px 20px rgba(20, 184, 166, 0.35);
    transform: translateY(-2px);
}

.edit-org-btn--secondary {
    background: rgba(255, 255, 255, 0.8);
    color: #6b7280;
    border: 1px solid rgba(0, 0, 0, 0.08);
}

.edit-org-btn--secondary:hover {
    background: rgba(255, 255, 255, 0.95);
    color: #374151;
}

[data-theme="dark"] .edit-org-btn--secondary {
    background: rgba(51, 65, 85, 0.6);
    border-color: rgba(255, 255, 255, 0.1);
    color: #94a3b8;
}

[data-theme="dark"] .edit-org-btn--secondary:hover {
    background: rgba(51, 65, 85, 0.8);
    color: #e2e8f0;
}

/* Focus States */
.edit-org-btn:focus-visible,
.edit-org-input:focus-visible,
.edit-org-textarea:focus-visible,
.edit-org-quick-btn:focus-visible {
    outline: 3px solid rgba(20, 184, 166, 0.5);
    outline-offset: 2px;
}

/* Offline Banner */
.edit-org-offline-banner {
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

.edit-org-offline-banner.visible {
    transform: translateY(0);
}

/* Loading State */
.edit-org-btn.loading {
    pointer-events: none;
    opacity: 0.7;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .edit-org-container {
        padding: 100px 16px 40px 16px;
    }

    .edit-org-card {
        padding: 22px;
        border-radius: 16px;
    }

    .edit-org-card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .edit-org-btn {
        min-height: 52px;
        padding: 16px 20px;
    }

    .edit-org-input,
    .edit-org-textarea {
        font-size: 16px !important; /* Prevents iOS zoom */
        padding: 16px;
    }

    .edit-org-form-actions {
        flex-direction: column;
    }

    .edit-org-quick-actions-grid {
        flex-direction: column;
    }

    .edit-org-quick-btn {
        min-width: 100%;
    }
}

/* Smooth Scroll */
html {
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
}
</style>

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
