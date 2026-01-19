<?php
// Phoenix Edit Listing View - Full Holographic Glassmorphism Edition
$hero_title = "Edit Listing";
$hero_subtitle = "Update your offer or request details.";
$hero_gradient = 'htb-hero-gradient-create';
$hero_type = 'Contribution';
$hideHero = true;

require __DIR__ . '/../../layouts/modern/header.php';

// Get user data for location
$currentUser = \Nexus\Models\User::findById($_SESSION['user_id']);
$userLocation = $currentUser['location'] ?? null;
$basePath = Nexus\Core\TenantContext::getBasePath();
?>


<div class="holo-edit-page">
    <!-- Floating Orbs -->
    <div class="holo-orb holo-orb-1"></div>
    <div class="holo-orb holo-orb-2"></div>
    <div class="holo-orb holo-orb-3"></div>

    <div class="holo-edit-container">
        <!-- Page Header -->
        <div class="holo-page-header">
            <div class="holo-page-icon">✏️</div>
            <h1 class="holo-page-title">Edit Listing</h1>
            <p class="holo-page-subtitle">Update your offer or request details</p>
        </div>

        <!-- Glass Card Form -->
        <div class="holo-glass-card">
            <form action="<?= $basePath ?>/listings/update" method="POST" enctype="multipart/form-data" id="editListingForm">
                <?= Nexus\Core\Csrf::input() ?>
                <input type="hidden" name="id" value="<?= $listing['id'] ?>">

                <!-- Type Selection -->
                <div class="holo-section">
                    <div class="holo-section-title">Listing Type</div>
                    <div class="holo-type-grid">
                        <label>
                            <input type="radio" name="type" value="offer" <?= $listing['type'] === 'offer' ? 'checked' : '' ?> style="display: none;" onchange="updateTypeStyles(this)">
                            <div id="type-offer-box" class="holo-type-card <?= $listing['type'] === 'offer' ? 'selected-offer' : '' ?>">
                                <span class="holo-type-icon">🎁</span>
                                <div class="holo-type-label">Offer Help</div>
                                <div class="holo-type-desc">Share a skill or service</div>
                            </div>
                        </label>
                        <label>
                            <input type="radio" name="type" value="request" <?= $listing['type'] === 'request' ? 'checked' : '' ?> style="display: none;" onchange="updateTypeStyles(this)">
                            <div id="type-request-box" class="holo-type-card <?= $listing['type'] === 'request' ? 'selected-request' : '' ?>">
                                <span class="holo-type-icon">🙋</span>
                                <div class="holo-type-label">Request Help</div>
                                <div class="holo-type-desc">Ask for assistance</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Category -->
                <div class="holo-section">
                    <label class="holo-label" for="category_id">Category</label>
                    <select name="category_id" id="category_id" class="holo-select" required>
                        <option value="" disabled>Choose a category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $listing['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Title -->
                <div class="holo-section">
                    <label class="holo-label" for="title">Title</label>
                    <input type="text" name="title" id="title" class="holo-input" value="<?= htmlspecialchars($listing['title']) ?>" placeholder="e.g. Gardening Assistance, Guitar Lessons..." required>
                </div>

                <!-- Location Info -->
                <div class="holo-location-box <?= $userLocation ? 'has-location' : 'no-location' ?>">
                    <div class="holo-location-icon">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>
                    <div class="holo-location-content">
                        <div class="holo-location-title">Location</div>
                        <?php if ($userLocation): ?>
                            <div class="holo-location-text">
                                This listing uses your profile location: <strong><?= htmlspecialchars($userLocation) ?></strong>
                            </div>
                        <?php else: ?>
                            <div class="holo-location-text">
                                <i class="fa-solid fa-exclamation-triangle" style="color: #f59e0b; margin-right: 4px;"></i>
                                No location set. <a href="<?= $basePath ?>/profile/edit">Add one to your profile</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Description -->
                <div class="holo-section">
                    <label class="holo-label" for="description">Description</label>
                    <?php
                    $aiGenerateType = 'listing';
                    $aiTitleField = 'title';
                    $aiDescriptionField = 'description';
                    $aiTypeField = 'type';
                    include __DIR__ . '/../../partials/ai-generate-button.php';
                    ?>
                    <textarea name="description" id="description" class="holo-textarea" placeholder="Describe what you're offering or requesting in detail..." required><?= htmlspecialchars($listing['description']) ?></textarea>
                </div>

                <!-- Image Upload -->
                <div class="holo-section">
                    <label class="holo-label">Image <span class="holo-label-optional">(Optional)</span></label>

                    <?php if (!empty($listing['image_url'])): ?>
                    <div class="holo-current-image">
                        <img src="<?= htmlspecialchars($listing['image_url']) ?>" loading="lazy" alt="Current image">
                        <div class="holo-current-image-info">
                            <div class="holo-current-image-label">Current Image</div>
                            <div class="holo-current-image-hint">Upload a new image to replace</div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="holo-file-upload">
                        <input type="file" name="image" id="image" accept="image/*">
                        <div class="holo-file-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                        <div class="holo-file-text">Drop an image or click to browse</div>
                        <div class="holo-file-hint">PNG, JPG, GIF up to 5MB</div>
                    </div>

                    <div class="holo-new-image-preview" id="newImagePreview">
                        <img id="newImageTag" src="" alt="New image" loading="lazy">
                        <div class="holo-new-image-preview-info">
                            <div class="holo-new-image-preview-label">New Image Selected</div>
                            <div class="holo-new-image-preview-hint">Will be uploaded when you save</div>
                        </div>
                    </div>
                </div>

                <!-- Dynamic Attributes -->
                <?php if (!empty($attributes)): ?>
                <div id="attributes-container" class="holo-attributes-box">
                    <label class="holo-label" style="margin-bottom: 16px;">Service Details</label>
                    <div class="holo-attributes-grid">
                        <?php foreach ($attributes as $attr): ?>
                            <label class="holo-attribute-item attribute-item"
                                data-category-id="<?= $attr['category_id'] ?? 'global' ?>"
                                data-target-type="<?= $attr['target_type'] ?? 'any' ?>">
                                <?php if ($attr['input_type'] === 'checkbox'): ?>
                                    <input type="checkbox" name="attributes[<?= $attr['id'] ?>]" value="1"
                                        <?= isset($selectedAttributes[$attr['id']]) ? 'checked' : '' ?>>
                                <?php endif; ?>
                                <span><?= htmlspecialchars($attr['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Partner Timebanks (Federation) -->
                <?php if (!empty($federationEnabled)): ?>
                <div class="holo-federation-section">
                    <label class="holo-label">
                        <i class="fa-solid fa-globe" style="margin-right: 8px; color: #8b5cf6;"></i>
                        Share with Partner Timebanks
                        <span style="font-weight: 400; opacity: 0.6; font-size: 0.85rem;">(Optional)</span>
                    </label>

                    <?php if (!empty($userFederationOptedIn)): ?>
                    <p class="holo-field-hint" style="margin-bottom: 12px;">
                        Make this listing visible to members of our partner timebanks.
                    </p>
                    <?php $currentVisibility = $listing['federated_visibility'] ?? 'none'; ?>
                    <div class="holo-federation-options">
                        <label class="holo-radio-card">
                            <input type="radio" name="federated_visibility" value="none" <?= $currentVisibility === 'none' ? 'checked' : '' ?>>
                            <span class="radio-content">
                                <span class="radio-icon"><i class="fa-solid fa-lock"></i></span>
                                <span class="radio-label">Local Only</span>
                                <span class="radio-desc">Only visible to members of this timebank</span>
                            </span>
                        </label>
                        <label class="holo-radio-card">
                            <input type="radio" name="federated_visibility" value="listed" <?= $currentVisibility === 'listed' ? 'checked' : '' ?>>
                            <span class="radio-content">
                                <span class="radio-icon"><i class="fa-solid fa-eye"></i></span>
                                <span class="radio-label">Visible</span>
                                <span class="radio-desc">Partner timebank members can see this listing</span>
                            </span>
                        </label>
                        <label class="holo-radio-card">
                            <input type="radio" name="federated_visibility" value="bookable" <?= $currentVisibility === 'bookable' ? 'checked' : '' ?>>
                            <span class="radio-content">
                                <span class="radio-icon"><i class="fa-solid fa-handshake"></i></span>
                                <span class="radio-label">Bookable</span>
                                <span class="radio-desc">Partner members can contact you about this listing</span>
                            </span>
                        </label>
                    </div>
                    <?php else: ?>
                    <div class="holo-federation-optin-notice">
                        <i class="fa-solid fa-info-circle"></i>
                        <div>
                            <strong>Enable federation to share listings</strong>
                            <p>To share your listings with partner timebanks, you need to opt into federation in your <a href="<?= $basePath ?>/settings?section=federation">account settings</a>.</p>
                        </div>
                    </div>
                    <input type="hidden" name="federated_visibility" value="none">
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- SDGs -->
                <details class="holo-sdg-accordion">
                    <summary class="holo-sdg-header">
                        <span>🌍 Social Impact <span style="font-weight: 400; opacity: 0.6; font-size: 0.85rem;">(Optional)</span></span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </summary>
                    <div class="holo-sdg-content">
                        <p class="holo-sdg-intro">Which UN Sustainable Development Goals does this support?</p>
                        <?php
                        require_once __DIR__ . '/../../../src/Helpers/SDG.php';
                        $sdgs = \Nexus\Helpers\SDG::all();
                        $currentGoals = json_decode($listing['sdg_goals'], true) ?? [];
                        ?>
                        <div class="holo-sdg-grid">
                            <?php foreach ($sdgs as $id => $goal):
                                $isChecked = in_array($id, $currentGoals);
                            ?>
                                <label class="holo-sdg-card" data-color="<?= $goal['color'] ?>"
                                    style="<?= $isChecked ? "border-color: {$goal['color']}; background-color: {$goal['color']}18; box-shadow: 0 4px 15px {$goal['color']}30;" : '' ?>">
                                    <input type="checkbox" name="sdg_goals[]" value="<?= $id ?>" <?= $isChecked ? 'checked' : '' ?> onchange="toggleSDG(this, '<?= $goal['color'] ?>')">
                                    <span class="sdg-icon"><?= $goal['icon'] ?></span>
                                    <span class="sdg-label"><?= $goal['label'] ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </details>

                <!-- SEO Settings Accordion -->
                <?php
                $seo = $seo ?? \Nexus\Models\SeoMetadata::get('listing', $listing['id']);
                $entityTitle = $listing['title'] ?? '';
                $entityUrl = $basePath . '/listings/' . $listing['id'];
                require __DIR__ . '/../../partials/seo-accordion.php';
                ?>

                <!-- Submit Button -->
                <button type="submit" class="holo-submit-btn" id="submitBtn">
                    <i class="fa-solid fa-check" style="margin-right: 10px;"></i>
                    Save Changes
                </button>
            </form>

            <!-- Danger Zone -->
            <div class="holo-danger-zone">
                <div class="holo-danger-title">Danger Zone</div>
                <form action="<?= $basePath ?>/listings/delete" method="POST" onsubmit="return confirm('Are you sure you want to delete this listing? This action cannot be undone.');">
                    <?= Nexus\Core\Csrf::input() ?>
                    <input type="hidden" name="id" value="<?= $listing['id'] ?>">
                    <button type="submit" class="holo-delete-btn">
                        <i class="fa-solid fa-trash" style="margin-right: 8px;"></i>
                        Delete Listing
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Type Selection Styling
function updateTypeStyles(radio) {
    const offerBox = document.getElementById('type-offer-box');
    const requestBox = document.getElementById('type-request-box');

    // Clear all selections
    offerBox.classList.remove('selected-offer', 'selected-request');
    requestBox.classList.remove('selected-offer', 'selected-request');

    // Apply new selection
    if (radio.value === 'offer') {
        offerBox.classList.add('selected-offer');
    } else {
        requestBox.classList.add('selected-request');
    }

    // Filter attributes if function exists
    if (typeof filterAttributes === 'function') {
        filterAttributes();
    }
}

// Attribute Filtering
const categorySelect = document.getElementById('category_id');

function filterAttributes() {
    const container = document.getElementById('attributes-container');
    if (!container) return;

    const selectedCat = categorySelect.value;
    const selectedType = document.querySelector('input[name="type"]:checked')?.value || 'offer';
    const items = document.querySelectorAll('.attribute-item');
    let visibleCount = 0;

    items.forEach(item => {
        const itemCat = item.getAttribute('data-category-id');
        const itemType = item.getAttribute('data-target-type');

        const catMatch = itemCat === 'global' || itemCat == selectedCat;
        const typeMatch = itemType === 'any' || itemType === selectedType;

        if (catMatch && typeMatch) {
            item.style.display = 'flex';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });

    container.style.display = visibleCount > 0 ? 'block' : 'none';
}

if (categorySelect) {
    categorySelect.addEventListener('change', filterAttributes);
}

// SDG Toggle
function toggleSDG(checkbox, color) {
    const card = checkbox.closest('.holo-sdg-card');
    if (checkbox.checked) {
        card.style.borderColor = color;
        card.style.backgroundColor = color + '18';
        card.style.boxShadow = `0 4px 15px ${color}30`;
    } else {
        card.style.borderColor = '';
        card.style.backgroundColor = '';
        card.style.boxShadow = '';
    }
}

// Image Preview
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('newImageTag').src = e.target.result;
            document.getElementById('newImagePreview').style.display = 'flex';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// File Upload Handler
const fileInput = document.getElementById('image');
const fileUpload = document.querySelector('.holo-file-upload');

if (fileInput && fileUpload) {
    fileInput.addEventListener('change', function() {
        const fileName = this.files[0]?.name;
        if (fileName) {
            fileUpload.querySelector('.holo-file-text').textContent = fileName;
            fileUpload.querySelector('.holo-file-icon').innerHTML = '<i class="fa-solid fa-check-circle" style="color: #10b981;"></i>';
            previewImage(this);
        }
    });
}

// Form Submission
document.addEventListener('DOMContentLoaded', function() {
    // Initialize
    if (typeof filterAttributes === 'function') {
        filterAttributes();
    }

    const checkedRadio = document.querySelector('input[name="type"]:checked');
    if (checkedRadio) {
        updateTypeStyles(checkedRadio);
    }

    // Form submission handling
    const form = document.getElementById('editListingForm');
    const submitBtn = document.getElementById('submitBtn');

    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            if (!navigator.onLine) {
                e.preventDefault();
                alert('You are offline. Please connect to the internet to save your changes.');
                return;
            }

            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
        });
    }

    // Touch feedback for cards
    document.querySelectorAll('.holo-type-card, .holo-sdg-card, .holo-submit-btn').forEach(el => {
        el.addEventListener('pointerdown', () => el.style.transform = 'scale(0.97)');
        el.addEventListener('pointerup', () => el.style.transform = '');
        el.addEventListener('pointerleave', () => el.style.transform = '');
    });
});
</script>

<?php require __DIR__ . '/../../layouts/modern/footer.php'; ?>
