<?php
// Edit Hub - Modern Layout
$hero_title = "Edit Hub";
$hero_subtitle = "Update your hub settings and information.";
$hero_gradient = 'htb-hero-gradient-hub';
$hero_type = 'Community';

require __DIR__ . '/../../layouts/modern/header.php';
?>

<!-- Offline Banner -->
<div class="offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash"></i>
    <span>No internet connection</span>
</div>



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

            <!-- Partner Timebanks (Federation) -->
            <?php if (!empty($federationEnabled)): ?>
            <div class="federation-section">
                <label class="edit-label">
                    <i class="fa-solid fa-globe" style="margin-right: 8px; color: #8b5cf6;"></i>
                    Share with Partner Timebanks
                    <span class="edit-label-hint">(Optional)</span>
                </label>

                <?php if (!empty($userFederationOptedIn)): ?>
                <p class="edit-hint" style="margin-bottom: 12px; margin-top: 0;">
                    Make this hub visible to members of our partner timebanks.
                </p>
                <div class="federation-options">
                    <label class="radio-card">
                        <input type="radio" name="federated_visibility" value="none" <?= ($group['federated_visibility'] ?? 'none') === 'none' ? 'checked' : '' ?>>
                        <span class="radio-content">
                            <span class="radio-label">Local Only</span>
                            <span class="radio-desc">Only visible to members of this timebank</span>
                        </span>
                    </label>
                    <label class="radio-card">
                        <input type="radio" name="federated_visibility" value="listed" <?= ($group['federated_visibility'] ?? '') === 'listed' ? 'checked' : '' ?>>
                        <span class="radio-content">
                            <span class="radio-label">Visible</span>
                            <span class="radio-desc">Partner timebank members can see this hub</span>
                        </span>
                    </label>
                    <label class="radio-card">
                        <input type="radio" name="federated_visibility" value="joinable" <?= ($group['federated_visibility'] ?? '') === 'joinable' ? 'checked' : '' ?>>
                        <span class="radio-content">
                            <span class="radio-label">Joinable</span>
                            <span class="radio-desc">Partner members can join this hub</span>
                        </span>
                    </label>
                </div>
                <?php else: ?>
                <div class="federation-optin-notice">
                    <i class="fa-solid fa-info-circle"></i>
                    <div>
                        <strong>Enable federation to share hubs</strong>
                        <p>To share your hubs with partner timebanks, you need to opt into federation in your <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/settings?section=federation">account settings</a>.</p>
                    </div>
                </div>
                <input type="hidden" name="federated_visibility" value="none">
                <?php endif; ?>
            </div>
            <?php endif; ?>

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

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
