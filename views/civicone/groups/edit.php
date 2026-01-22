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

<!-- Groups Edit CSS -->
<link rel="stylesheet" href="<?= NexusCoreTenantContext::getBasePath() ?>/assets/css/purged/civicone-groups-edit.min.css">

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
                    <span class="featured-label-text">⭐ Featured Hub</span>
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
                            <div class="current-image-title">Current Avatar</div>
                            <div class="current-image-hint">Upload new to replace</div>
                        </div>
                        <label class="clear-image-btn" title="Remove avatar">
                            <input type="checkbox" name="clear_avatar" value="1" class="visually-hidden-input">
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
                            <div class="current-image-title">Current Cover</div>
                            <div class="current-image-hint">Upload new to replace</div>
                        </div>
                        <label class="clear-image-btn" title="Remove cover">
                            <input type="checkbox" name="clear_cover" value="1" class="visually-hidden-input">
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

<!-- Groups Edit JavaScript -->
<script src="<?= NexusCoreTenantContext::getBasePath() ?>/assets/js/civicone-groups-edit.min.js" defer></script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
