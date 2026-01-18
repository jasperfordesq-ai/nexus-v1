<?php
// CivicOne View: Edit Listing
$hTitle = 'Edit Listing';
$hSubtitle = 'Update your offer or request details';
$hType = 'Listings';

require __DIR__ . '/../../layouts/civicone/header.php';
$basePath = \Nexus\Core\TenantContext::getBasePath();
?>

<!-- Action Bar -->
<div class="civic-action-bar" style="margin-bottom: 24px;">
    <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>" class="civic-btn civic-btn--outline">
        <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
        Back to Listing
    </a>
</div>

<div class="civic-card" style="max-width: 800px;">
    <form action="<?= $basePath ?>/listings/update" method="POST" enctype="multipart/form-data">
        <?= Nexus\Core\Csrf::input() ?>
        <input type="hidden" name="id" value="<?= $listing['id'] ?>">

        <!-- Type Selection -->
        <div class="civic-form-group">
            <label class="civic-label">I want to...</label>
            <div class="civic-type-selector-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 10px;">
                <label style="cursor: pointer;">
                    <input type="radio" name="type" value="offer" <?= $listing['type'] === 'offer' ? 'checked' : '' ?>
                           style="display: none;" onchange="updateTypeStyles(this)">
                    <div id="type-offer-box" class="civic-type-card"
                         style="text-align: center; padding: 20px; border-radius: 8px; border: 2px solid var(--civic-border); transition: all 0.2s;">
                        <div style="font-size: 2rem; margin-bottom: 8px;" aria-hidden="true">🎁</div>
                        <div style="font-weight: 700;">Offer Help</div>
                    </div>
                </label>
                <label style="cursor: pointer;">
                    <input type="radio" name="type" value="request" <?= $listing['type'] === 'request' ? 'checked' : '' ?>
                           style="display: none;" onchange="updateTypeStyles(this)">
                    <div id="type-request-box" class="civic-type-card"
                         style="text-align: center; padding: 20px; border-radius: 8px; border: 2px solid var(--civic-border); transition: all 0.2s;">
                        <div style="font-size: 2rem; margin-bottom: 8px;" aria-hidden="true">🙋</div>
                        <div style="font-weight: 700;">Request Help</div>
                    </div>
                </label>
            </div>
        </div>

        <!-- Category -->
        <div class="civic-form-group">
            <label for="category_id" class="civic-label">Category <span class="civic-required">*</span></label>
            <select name="category_id" id="category_id" required class="civic-input">
                <option value="" disabled>Select a Category...</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $listing['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Title -->
        <div class="civic-form-group">
            <label for="title" class="civic-label">Title <span class="civic-required">*</span></label>
            <input type="text" name="title" id="title"
                   value="<?= htmlspecialchars($listing['title']) ?>"
                   placeholder="e.g. Gardening Assistance"
                   required class="civic-input">
        </div>

        <!-- Description -->
        <div class="civic-form-group">
            <label for="description" class="civic-label">Description <span class="civic-required">*</span></label>
            <textarea name="description" id="description" rows="5"
                      placeholder="Describe what you can offer or need in detail..."
                      required class="civic-textarea"><?= htmlspecialchars($listing['description']) ?></textarea>
        </div>

        <!-- Location -->
        <div class="civic-form-group">
            <label for="location" class="civic-label">Location</label>
            <input type="text" name="location" id="location"
                   value="<?= htmlspecialchars($listing['location'] ?? '') ?>"
                   placeholder="City or area"
                   class="civic-input mapbox-location-input-v2">
            <input type="hidden" name="latitude" value="<?= htmlspecialchars($listing['latitude'] ?? '') ?>">
            <input type="hidden" name="longitude" value="<?= htmlspecialchars($listing['longitude'] ?? '') ?>">
        </div>

        <!-- Image -->
        <div class="civic-form-group">
            <label for="image" class="civic-label">Image (Optional)</label>

            <?php if (!empty($listing['image_url'])): ?>
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px; padding: 12px; background: var(--civic-bg-page); border-radius: 8px;">
                    <img src="<?= htmlspecialchars($listing['image_url']) ?>"
                         alt="Current listing image"
                         style="height: 60px; width: 60px; border-radius: 8px; object-fit: cover;">
                    <span style="color: var(--civic-text-muted);">Current Image</span>
                </div>
            <?php endif; ?>

            <input type="file" name="image" id="image" accept="image/*" class="civic-input" style="padding: 12px;">
        </div>

        <!-- Submit -->
        <div class="civic-modal-actions" style="margin-top: 30px;">
            <button type="submit" class="civic-btn">
                <span class="dashicons dashicons-yes" aria-hidden="true"></span>
                Save Changes
            </button>
            <a href="<?= $basePath ?>/listings/<?= $listing['id'] ?>" class="civic-btn civic-btn--outline">
                Cancel
            </a>
        </div>
    </form>
</div>

<!-- Delete Option -->
<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--civic-border);">
    <form action="<?= $basePath ?>/listings/delete" method="POST"
          onsubmit="return confirm('Are you sure you want to delete this listing? This action cannot be undone.');">
        <?= \Nexus\Core\Csrf::input() ?>
        <input type="hidden" name="id" value="<?= $listing['id'] ?>">
        <button type="submit" class="civic-btn civic-btn--sm" style="background: #FEF2F2; color: #B91C1C; border: 1px solid #FECACA;">
            <span class="dashicons dashicons-trash" aria-hidden="true"></span>
            Delete Listing
        </button>
    </form>
</div>

<style>
    .civic-required {
        color: #DC2626;
    }

    .civic-type-card:hover {
        border-color: var(--civic-brand) !important;
    }

    /* Mobile responsive for type selector */
    @media (max-width: 400px) {
        .civic-type-selector-grid {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<script>
    function updateTypeStyles(radio) {
        const offerBox = document.getElementById('type-offer-box');
        const requestBox = document.getElementById('type-request-box');

        // Reset both
        offerBox.style.borderColor = 'var(--civic-border)';
        offerBox.style.backgroundColor = 'var(--civic-bg-card)';
        requestBox.style.borderColor = 'var(--civic-border)';
        requestBox.style.backgroundColor = 'var(--civic-bg-card)';

        if (radio.value === 'offer') {
            offerBox.style.borderColor = '#10b981';
            offerBox.style.backgroundColor = '#ecfdf5';
        } else {
            requestBox.style.borderColor = '#f97316';
            requestBox.style.backgroundColor = '#ffedd5';
        }
    }

    // Init on page load
    document.addEventListener('DOMContentLoaded', () => {
        const checkedRadio = document.querySelector('input[name="type"]:checked');
        if (checkedRadio) {
            updateTypeStyles(checkedRadio);
        }
    });
</script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
