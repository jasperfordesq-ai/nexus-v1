<?php
// CivicOne View: Create Listing
$pageTitle = 'Post New Listing';
require dirname(__DIR__, 2) . '/layouts/civicone/header.php';
?>

<?php
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'Offers & Requests', 'url' => '/listings'],
    ['label' => 'Post New Listing']
];
require dirname(__DIR__, 2) . '/layouts/civicone/partials/breadcrumb.php';
?>

<style>
/* Accessible Radio Card Styles */
.type-selector-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 400px) {
    .type-selector-grid {
        grid-template-columns: 1fr;
    }
}

.type-radio-card {
    position: relative;
}

.type-radio-card input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    z-index: 2;
    margin: 0;
}

.type-radio-card input[type="radio"]:focus + .type-card-visual {
    outline: 3px solid var(--civic-focus, #FBBF24);
    outline-offset: 2px;
}

.type-card-visual {
    text-align: center;
    padding: 20px;
    border: 2px solid #ccc;
    border-radius: var(--civic-radius, 8px);
    background: #fff;
    transition: all 0.2s;
    cursor: pointer;
}

.type-radio-card input[type="radio"]:checked + .type-card-visual.type-offer {
    background-color: #f0fdf4;
    border-color: #16a34a;
    color: #16a34a;
}

.type-radio-card input[type="radio"]:checked + .type-card-visual.type-request {
    background-color: #fff7ed;
    border-color: #ea580c;
    color: #ea580c;
}

/* Error message styles */
.civic-field-error {
    color: var(--civic-error, #DC2626);
    font-size: 14px;
    font-weight: 600;
    margin-top: 5px;
    display: none;
    align-items: center;
    gap: 5px;
}

.civic-field-error::before {
    content: "⚠";
}

.civic-input:invalid:not(:placeholder-shown) ~ .civic-field-error,
.civic-input.has-error ~ .civic-field-error {
    display: flex;
}

.civic-input:invalid:not(:placeholder-shown),
.civic-input.has-error {
    border-color: var(--civic-error, #DC2626);
    border-width: 3px;
}
</style>

<div class="civic-container">

    <div style="margin-bottom: 30px; border-bottom: 4px solid var(--skin-primary, #00796B); padding-bottom: 10px;">
        <h1 style="margin: 0; text-transform: uppercase;">Post a New Listing</h1>
        <p style="margin: 5px 0 0; color: var(--civic-text-secondary, #4B5563);">Share your skills or ask for help.</p>
    </div>

    <div class="civic-card" style="max-width: 800px;">
        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/listings/store" method="POST" enctype="multipart/form-data" novalidate>
            <?= Nexus\Core\Csrf::input() ?>

            <!-- Type Selection - Accessible Fieldset -->
            <?php $type = $_GET['type'] ?? 'offer'; ?>
            <fieldset style="border: none; padding: 0; margin: 0 0 25px 0;">
                <legend id="type-legend" style="display: block; font-weight: bold; margin-bottom: 10px; padding: 0;">I want to... <span style="color: var(--civic-error, #DC2626);">*</span></legend>
                <div class="type-selector-grid" role="radiogroup" aria-labelledby="type-legend">
                    <div class="type-radio-card">
                        <input type="radio" name="type" id="type-offer" value="offer" <?= $type === 'offer' ? 'checked' : '' ?> required aria-describedby="type-hint">
                        <label for="type-offer" class="type-card-visual type-offer">
                            <div style="font-size: 2rem;" aria-hidden="true">🎁</div>
                            <div style="font-weight: bold;">Offer Help</div>
                        </label>
                    </div>
                    <div class="type-radio-card">
                        <input type="radio" name="type" id="type-request" value="request" <?= $type === 'request' ? 'checked' : '' ?> aria-describedby="type-hint">
                        <label for="type-request" class="type-card-visual type-request">
                            <div style="font-size: 2rem;" aria-hidden="true">🙋</div>
                            <div style="font-weight: bold;">Request Help</div>
                        </label>
                    </div>
                </div>
                <span id="type-hint" class="civic-field-hint" style="font-size: 13px; color: var(--civic-text-secondary, #4B5563); margin-top: 8px; display: block;">Choose whether you're offering a skill or requesting help from the community.</span>
            </fieldset>

            <!-- Category -->
            <div style="margin-bottom: 20px;">
                <label for="category_id" class="civic-label-required" style="display: block; font-weight: bold; margin-bottom: 5px;">Category <span style="color: var(--civic-error, #DC2626);">*</span></label>
                <select name="category_id" id="category_id" required class="civic-input" style="width: 100%;" aria-describedby="category-hint category-error">
                    <option value="" disabled selected>Select a Category...</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span id="category-hint" class="civic-field-hint" style="font-size: 13px; color: var(--civic-text-secondary, #4B5563); margin-top: 5px; display: block;">Select the category that best describes your listing.</span>
                <span id="category-error" class="civic-field-error" role="alert">Please select a category.</span>
            </div>

            <!-- Title -->
            <div style="margin-bottom: 20px;">
                <label for="title" class="civic-label-required" style="display: block; font-weight: bold; margin-bottom: 5px;">Title <span style="color: var(--civic-error, #DC2626);">*</span></label>
                <input type="text" name="title" id="title" placeholder="e.g. Gardening Assistance" required class="civic-input" style="width: 100%;" aria-describedby="title-hint title-error">
                <span id="title-hint" class="civic-field-hint" style="font-size: 13px; color: var(--civic-text-secondary, #4B5563); margin-top: 5px; display: block;">A short, descriptive title for your listing.</span>
                <span id="title-error" class="civic-field-error" role="alert">Please enter a title for your listing.</span>
            </div>

            <!-- Description -->
            <div style="margin-bottom: 20px;">
                <label for="description" class="civic-label-required" style="display: block; font-weight: bold; margin-bottom: 5px;">Description <span style="color: var(--civic-error, #DC2626);">*</span></label>
                <textarea name="description" id="description" rows="5" placeholder="Describe what you can offer or need..." required class="civic-input" style="width: 100%; font-family: inherit;" aria-describedby="description-hint description-error"></textarea>
                <span id="description-hint" class="civic-field-hint" style="font-size: 13px; color: var(--civic-text-secondary, #4B5563); margin-top: 5px; display: block;">Provide details about what you're offering or what help you need.</span>
                <span id="description-error" class="civic-field-error" role="alert">Please provide a description.</span>
            </div>

            <!-- Location -->
            <div style="margin-bottom: 20px;">
                <label for="location" style="display: block; font-weight: bold; margin-bottom: 5px;">Location</label>
                <input type="text" name="location" id="location" placeholder="City or Area" class="civic-input mapbox-location-input-v2" style="width: 100%;" aria-describedby="location-hint">
                <input type="hidden" name="latitude">
                <input type="hidden" name="longitude">
                <span id="location-hint" class="civic-field-hint" style="font-size: 13px; color: var(--civic-text-secondary, #4B5563); margin-top: 5px; display: block;">Optional. Helps members find local services.</span>
            </div>

            <!-- Image -->
            <div style="margin-bottom: 30px;">
                <label for="image" style="display: block; font-weight: bold; margin-bottom: 5px;">Image (Optional)</label>
                <input type="file" name="image" id="image" accept="image/*" class="civic-input" style="width: 100%; padding: 10px;" aria-describedby="image-hint">
                <span id="image-hint" class="civic-field-hint" style="font-size: 13px; color: var(--civic-text-secondary, #4B5563); margin-top: 5px; display: block;">Add a photo to help illustrate your listing.</span>
            </div>

            <button type="submit" class="civic-btn" style="width: 100%; font-size: 1.2rem;">Post Listing</button>
        </form>
    </div>

</div>

<script>
    // Form validation with accessible error handling
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('form');

        form.addEventListener('submit', (e) => {
            let hasErrors = false;
            const requiredFields = form.querySelectorAll('[required]');

            requiredFields.forEach(field => {
                const errorEl = document.getElementById(field.id + '-error');

                if (!field.value || (field.tagName === 'SELECT' && field.value === '')) {
                    field.classList.add('has-error');
                    field.setAttribute('aria-invalid', 'true');
                    if (errorEl) errorEl.style.display = 'flex';
                    hasErrors = true;
                } else {
                    field.classList.remove('has-error');
                    field.setAttribute('aria-invalid', 'false');
                    if (errorEl) errorEl.style.display = 'none';
                }
            });

            if (hasErrors) {
                e.preventDefault();
                // Focus first error field
                const firstError = form.querySelector('.has-error, [aria-invalid="true"]');
                if (firstError) firstError.focus();
            }
        });

        // Clear errors on input
        form.querySelectorAll('.civic-input').forEach(input => {
            input.addEventListener('input', () => {
                input.classList.remove('has-error');
                input.setAttribute('aria-invalid', 'false');
                const errorEl = document.getElementById(input.id + '-error');
                if (errorEl) errorEl.style.display = 'none';
            });
        });
    });
</script>

<?php require dirname(__DIR__, 2) . '/layouts/civicone/footer.php'; ?>