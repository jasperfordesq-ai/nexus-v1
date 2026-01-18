<?php
// Edit Hub - Modern Layout
$hero_title = "Edit Hub";
$hero_subtitle = "Update your hub settings and information.";
$hero_gradient = 'htb-hero-gradient-hub';
$hero_type = 'Community';

require __DIR__ . '/../../layouts/civicone/header.php';
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>

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
    .edit-submit:active,
    button:active {
        transform: scale(0.96) !important;
        transition: transform 0.1s ease !important;
    }

    /* Touch Targets - WCAG 2.1 AA (44px minimum) */
    .edit-submit,
    .edit-input,
    .edit-textarea,
    .edit-select,
    button {
        min-height: 44px;
    }

    .edit-input,
    .edit-textarea {
        font-size: 16px !important; /* Prevent iOS zoom */
    }

    /* Focus Visible */
    .edit-submit:focus-visible,
    .edit-input:focus-visible,
    .edit-textarea:focus-visible,
    .edit-select:focus-visible,
    button:focus-visible,
    a:focus-visible {
        outline: 3px solid rgba(219, 39, 119, 0.5);
        outline-offset: 2px;
    }

    /* Smooth Scroll */
    html {
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
    }

    /* Mobile Responsive - Gold Standard */
    @media (max-width: 768px) {
        .edit-submit,
        button {
            min-height: 48px;
        }
    }
</style>

<style>
    .edit-wrapper {
        padding-top: 120px;
        padding-bottom: 100px;
    }

    .edit-wrapper .htb-header-box {
        margin-bottom: 20px;
    }

    .edit-wrapper .htb-card {
        padding: 24px;
        background: var(--htb-card-bg, white);
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    [data-theme="dark"] .edit-wrapper .htb-card {
        background: rgba(30, 41, 59, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .edit-form-group {
        margin-bottom: 20px;
    }

    .edit-label {
        display: block;
        font-weight: 600;
        color: var(--htb-text-main);
        margin-bottom: 8px;
        font-size: 0.95rem;
    }

    .edit-label-hint {
        font-weight: 400;
        color: var(--htb-text-muted);
        font-size: 0.85rem;
        margin-left: 4px;
    }

    .edit-input,
    .edit-textarea,
    .edit-select {
        width: 100%;
        padding: 14px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 1rem;
        font-family: inherit;
        box-sizing: border-box;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        background: white;
        color: var(--htb-text-main);
        -webkit-appearance: none;
    }

    .edit-textarea {
        resize: vertical;
        min-height: 120px;
    }

    .edit-input:focus,
    .edit-textarea:focus,
    .edit-select:focus {
        outline: none;
        border-color: #db2777;
        box-shadow: 0 0 0 4px rgba(219, 39, 119, 0.1);
    }

    [data-theme="dark"] .edit-input,
    [data-theme="dark"] .edit-textarea,
    [data-theme="dark"] .edit-select {
        background: rgba(30, 41, 59, 0.8);
        border-color: rgba(255, 255, 255, 0.15);
        color: #f1f5f9;
    }

    .edit-hint {
        font-size: 0.85rem;
        color: var(--htb-text-muted);
        margin-top: 6px;
    }

    .edit-current-file {
        font-size: 0.85rem;
        color: #10b981;
        margin-top: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .edit-submit {
        width: 100%;
        padding: 16px;
        font-size: 1.05rem;
        border-radius: 14px;
        background: linear-gradient(135deg, #db2777, #ec4899);
        border: none;
        color: white;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(219, 39, 119, 0.3);
        transition: all 0.2s ease;
        -webkit-tap-highlight-color: transparent;
        margin-top: 10px;
    }

    .edit-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(219, 39, 119, 0.4);
    }

    .edit-submit:active {
        transform: scale(0.98);
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #db2777;
        text-decoration: none;
        font-weight: 600;
        margin-bottom: 20px;
    }

    .back-link:hover {
        text-decoration: underline;
    }

    /* Visibility Toggle */
    .visibility-options {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .visibility-option {
        position: relative;
        cursor: pointer;
    }

    .visibility-option input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .visibility-option-card {
        padding: 16px;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        transition: all 0.2s ease;
        background: white;
    }

    [data-theme="dark"] .visibility-option-card {
        background: rgba(30, 41, 59, 0.6);
        border-color: rgba(255, 255, 255, 0.15);
    }

    .visibility-option input:checked + .visibility-option-card {
        border-color: #db2777;
        background: linear-gradient(135deg, rgba(219, 39, 119, 0.05), rgba(236, 72, 153, 0.05));
    }

    [data-theme="dark"] .visibility-option input:checked + .visibility-option-card {
        background: rgba(219, 39, 119, 0.15);
    }

    .visibility-option-icon {
        font-size: 1.5rem;
        margin-bottom: 8px;
    }

    .visibility-option-title {
        font-weight: 600;
        color: var(--htb-text-main);
        margin-bottom: 4px;
    }

    .visibility-option-desc {
        font-size: 0.8rem;
        color: var(--htb-text-muted);
    }

    /* File Input Styling */
    .file-input-wrapper {
        position: relative;
    }

    .file-input-wrapper input[type="file"] {
        position: absolute;
        opacity: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
    }

    .file-input-label {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 16px;
        border: 2px dashed #d1d5db;
        border-radius: 12px;
        background: #f9fafb;
        color: var(--htb-text-muted);
        font-weight: 500;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    [data-theme="dark"] .file-input-label {
        background: rgba(30, 41, 59, 0.4);
        border-color: rgba(255, 255, 255, 0.2);
    }

    .file-input-wrapper:hover .file-input-label {
        border-color: #db2777;
        background: rgba(219, 39, 119, 0.05);
        color: #db2777;
    }

    /* Image Previews */
    .current-image-preview {
        margin-top: 12px;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: #f0fdf4;
        border-radius: 10px;
        border: 1px solid #86efac;
    }

    [data-theme="dark"] .current-image-preview {
        background: rgba(16, 185, 129, 0.1);
        border-color: rgba(16, 185, 129, 0.3);
    }

    .current-image-preview img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
    }

    .current-image-preview .cover-img {
        width: 120px;
        height: 60px;
    }

    .clear-image-btn {
        margin-left: auto;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        cursor: pointer;
        color: #9ca3af;
        background: transparent;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .clear-image-btn .clear-icon {
        font-size: 20px;
        font-weight: 300;
        line-height: 1;
    }

    .clear-image-btn:hover {
        background: #fef2f2;
        color: #dc2626;
    }

    [data-theme="dark"] .clear-image-btn:hover {
        background: rgba(220, 38, 38, 0.15);
        color: #f87171;
    }

    .clear-image-btn:has(input:checked) {
        background: #dc2626;
        color: white;
    }

    [data-theme="dark"] .clear-image-btn:has(input:checked) {
        background: #b91c1c;
    }

    .current-image-preview:has(.clear-image-btn input:checked) {
        background: #fef2f2;
        border-color: #fca5a5;
    }

    .current-image-preview:has(.clear-image-btn input:checked) img {
        opacity: 0.4;
    }

    [data-theme="dark"] .current-image-preview:has(.clear-image-btn input:checked) {
        background: rgba(220, 38, 38, 0.1);
        border-color: rgba(220, 38, 38, 0.3);
    }

    @media (max-width: 768px) {
        .edit-wrapper {
            padding-top: 100px;
            padding-bottom: 120px;
        }

        .edit-wrapper .htb-card {
            padding: 20px 16px;
            border-radius: 14px;
        }

        .visibility-options {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="htb-container-focused edit-wrapper">

    <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/<?= $group['id'] ?>?tab=settings" class="back-link">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to <?= htmlspecialchars($group['name']) ?>
    </a>

    <div class="htb-header-box">
        <h1>Edit Hub</h1>
        <p>Update the settings for <strong><?= htmlspecialchars($group['name']) ?></strong></p>
    </div>

    <div class="htb-card">
        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/groups/update" method="POST" enctype="multipart/form-data" id="editForm">
            <?= Nexus\Core\Csrf::input() ?>
            <input type="hidden" name="group_id" value="<?= $group['id'] ?>">

            <!-- Hub Name -->
            <div class="edit-form-group">
                <label class="edit-label" for="name">Hub Name</label>
                <input type="text" name="name" id="name" class="edit-input" required
                       value="<?= htmlspecialchars($group['name']) ?>"
                       placeholder="Enter hub name...">
            </div>

            <!-- Description -->
            <div class="edit-form-group">
                <label class="edit-label" for="description">Description</label>
                <textarea name="description" id="description" class="edit-textarea" required
                          placeholder="Describe what this hub is about..."><?= htmlspecialchars($group['description']) ?></textarea>
            </div>

            <!-- Visibility -->
            <div class="edit-form-group">
                <label class="edit-label">Visibility</label>
                <div class="visibility-options">
                    <label class="visibility-option">
                        <input type="radio" name="visibility" value="public" <?= ($group['visibility'] ?? 'public') === 'public' ? 'checked' : '' ?>>
                        <div class="visibility-option-card">
                            <div class="visibility-option-icon">🌍</div>
                            <div class="visibility-option-title">Public</div>
                            <div class="visibility-option-desc">Anyone can join instantly</div>
                        </div>
                    </label>
                    <label class="visibility-option">
                        <input type="radio" name="visibility" value="private" <?= ($group['visibility'] ?? 'public') === 'private' ? 'checked' : '' ?>>
                        <div class="visibility-option-card">
                            <div class="visibility-option-icon">🔒</div>
                            <div class="visibility-option-title">Private</div>
                            <div class="visibility-option-desc">Requires approval to join</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Featured Toggle (Site Admins Only) -->
            <?php if (!empty($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
            <div class="edit-form-group">
                <label class="edit-label">
                    <input type="checkbox" name="is_featured" value="1" <?= !empty($group['is_featured']) ? 'checked' : '' ?>>
                    <span style="margin-left: 8px;">⭐ Featured Hub</span>
                </label>
                <div class="edit-hint">Featured hubs appear in a special section at the top of the hubs page. Only site administrators can mark groups as featured.</div>
            </div>
            <?php endif; ?>

            <!-- Location -->
            <div class="edit-form-group">
                <label class="edit-label" for="location">
                    Location <span class="edit-label-hint">(Optional)</span>
                </label>
                <input type="text" name="location" id="location" class="edit-input mapbox-location-input-v2"
                       placeholder="Start typing a location..."
                       value="<?= htmlspecialchars($group['location'] ?? '') ?>"
                       autocomplete="off">
                <input type="hidden" name="latitude" id="location_lat" value="<?= htmlspecialchars($group['latitude'] ?? '') ?>">
                <input type="hidden" name="longitude" id="location_lng" value="<?= htmlspecialchars($group['longitude'] ?? '') ?>">
                <div class="edit-hint">Add a location to help members find local hubs.</div>
            </div>

            <!-- Hub Avatar -->
            <div class="edit-form-group">
                <label class="edit-label">Hub Avatar</label>
                <div class="file-input-wrapper">
                    <input type="file" name="image" id="image" accept="image/*">
                    <div class="file-input-label">
                        <i class="fa-solid fa-image"></i>
                        <span>Choose avatar image...</span>
                    </div>
                </div>
                <?php if (!empty($group['image_url'])): ?>
                    <div class="current-image-preview">
                        <img src="<?= htmlspecialchars($group['image_url']) ?>" loading="lazy" alt="Current avatar">
                        <div>
                            <div style="font-weight: 600; color: #166534; font-size: 0.85rem;">Current Avatar</div>
                            <div style="font-size: 0.75rem; color: #15803d;">Upload new to replace</div>
                        </div>
                        <label class="clear-image-btn" title="Remove avatar">
                            <input type="checkbox" name="clear_avatar" value="1" style="display: none;">
                            <span class="clear-icon">×</span>
                        </label>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Cover Image -->
            <div class="edit-form-group">
                <label class="edit-label">Cover Image</label>
                <div class="file-input-wrapper">
                    <input type="file" name="cover_image" id="cover_image" accept="image/*">
                    <div class="file-input-label">
                        <i class="fa-solid fa-panorama"></i>
                        <span>Choose cover image...</span>
                    </div>
                </div>
                <?php if (!empty($group['cover_image_url'])): ?>
                    <div class="current-image-preview">
                        <img src="<?= htmlspecialchars($group['cover_image_url']) ?>" loading="lazy" alt="Current cover" class="cover-img">
                        <div>
                            <div style="font-weight: 600; color: #166534; font-size: 0.85rem;">Current Cover</div>
                            <div style="font-size: 0.75rem; color: #15803d;">Upload new to replace</div>
                        </div>
                        <label class="clear-image-btn" title="Remove cover">
                            <input type="checkbox" name="clear_cover" value="1" style="display: none;">
                            <span class="clear-icon">×</span>
                        </label>
                    </div>
                <?php endif; ?>
            </div>

            <!-- SEO Settings Accordion -->
            <?php
            $seo = $seo ?? \Nexus\Models\SeoMetadata::get('group', $group['id']);
            $entityTitle = $group['name'] ?? '';
            $entityUrl = \Nexus\Core\TenantContext::getBasePath() . '/groups/' . $group['id'];
            require __DIR__ . '/../../partials/seo-accordion.php';
            ?>

            <button type="submit" class="edit-submit">
                <i class="fa-solid fa-check"></i> Save Changes
            </button>
        </form>
    </div>
</div>

<script>
// Update file input labels when files are selected
document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', function() {
        const label = this.nextElementSibling.querySelector('span');
        if (this.files.length > 0) {
            label.textContent = this.files[0].name;
            this.nextElementSibling.style.borderColor = '#10b981';
            this.nextElementSibling.style.color = '#10b981';
        }
    });
});

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
document.querySelectorAll('.edit-submit, button').forEach(btn => {
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
        meta.content = '#db2777';
        document.head.appendChild(meta);
    }

    function updateThemeColor() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#db2777');
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

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
