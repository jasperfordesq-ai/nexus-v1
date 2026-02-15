<?php
/**
 * Admin Plan Form - Create/Edit
 */

use Nexus\Core\TenantContext;
use Nexus\Core\Csrf;

$basePath = TenantContext::getBasePath();
$isEdit = $mode === 'edit';
$pageTitle = $isEdit ? 'Edit Plan' : 'Create Plan';

// Parse JSON fields
$features = [];
$layouts = [];
if ($plan) {
    $features = is_string($plan['features']) ? json_decode($plan['features'], true) : $plan['features'];
    $layouts = is_string($plan['allowed_layouts']) ? json_decode($plan['allowed_layouts'], true) : $plan['allowed_layouts'];
}

// Admin header configuration
$adminPageTitle = $pageTitle;
$adminPageSubtitle = 'Subscriptions';
$adminPageIcon = 'fa-crown';

require dirname(__DIR__) . '/partials/admin-header.php';
?>

<div class="page-hero">
    <div class="page-hero-content">
        <h1>
            <a href="<?= $basePath ?>/admin-legacy/plans" class="admin-back-link">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <?= $pageTitle ?>
        </h1>
        <p><?= $isEdit ? 'Modify subscription plan settings' : 'Create a new subscription tier' ?></p>
    </div>
</div>

<div class="admin-glass-card" style="max-width: 900px;">
    <form method="POST" action="<?= $basePath ?>/admin-legacy/plans/<?= $isEdit ? 'edit/' . $plan['id'] : 'create' ?>">
        <input type="hidden" name="csrf_token" value="<?= Csrf::generate() ?>">

        <div class="form-section">
            <h3><i class="fa-solid fa-tag"></i> Basic Information</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="name">Plan Name *</label>
                    <input type="text" id="name" name="name" required
                           value="<?= htmlspecialchars($plan['name'] ?? '') ?>"
                           placeholder="e.g., Professional">
                </div>

                <div class="form-group">
                    <label for="slug">Slug *</label>
                    <input type="text" id="slug" name="slug" <?= $isEdit ? '' : 'readonly' ?>
                           value="<?= htmlspecialchars($plan['slug'] ?? '') ?>"
                           placeholder="professional">
                    <small><?= $isEdit ? 'Be careful changing slugs - may break integrations' : 'Auto-generated from name' ?></small>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"
                          placeholder="Brief description of this plan..."><?= htmlspecialchars($plan['description'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="tier_level">Tier Level *</label>
                    <input type="number" id="tier_level" name="tier_level" required min="0" max="999"
                           value="<?= $plan['tier_level'] ?? 0 ?>">
                    <small>0 = Free, 1 = Basic, 2 = Pro, 3 = Enterprise, etc.</small>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" <?= ($plan['is_active'] ?? 1) ? 'checked' : '' ?>>
                        Active (visible to users)
                    </label>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3><i class="fa-solid fa-dollar-sign"></i> Pricing</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="price_monthly">Monthly Price ($)</label>
                    <input type="number" id="price_monthly" name="price_monthly" step="0.01" min="0"
                           value="<?= $plan['price_monthly'] ?? 0 ?>">
                </div>

                <div class="form-group">
                    <label for="price_yearly">Yearly Price ($)</label>
                    <input type="number" id="price_yearly" name="price_yearly" step="0.01" min="0"
                           value="<?= $plan['price_yearly'] ?? 0 ?>">
                    <small>Annual pricing (optional)</small>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3><i class="fa-solid fa-sliders"></i> Limits</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="max_menus">Max Menus *</label>
                    <input type="number" id="max_menus" name="max_menus" required min="1" max="999"
                           value="<?= $plan['max_menus'] ?? 1 ?>">
                    <small>999 = Unlimited</small>
                </div>

                <div class="form-group">
                    <label for="max_menu_items">Max Items Per Menu *</label>
                    <input type="number" id="max_menu_items" name="max_menu_items" required min="1" max="999"
                           value="<?= $plan['max_menu_items'] ?? 10 ?>">
                    <small>999 = Unlimited</small>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3><i class="fa-solid fa-palette"></i> Allowed Layouts</h3>
            <p style="color: rgba(255,255,255,0.6); margin-bottom: 1rem;">Select which layouts tenants on this plan can access:</p>

            <div class="checkbox-grid">
                <?php foreach ($available_layouts as $layoutKey => $layoutName): ?>
                <label class="checkbox-card">
                    <input type="checkbox" name="allowed_layouts[]" value="<?= $layoutKey ?>"
                           <?= in_array($layoutKey, $layouts ?? []) ? 'checked' : '' ?>>
                    <div class="checkbox-card-content">
                        <i class="fa-solid fa-paint-brush"></i>
                        <strong><?= $layoutName ?></strong>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-section">
            <h3><i class="fa-solid fa-puzzle-piece"></i> Features (JSON)</h3>
            <p style="color: rgba(255,255,255,0.6); margin-bottom: 1rem;">
                Define feature flags as JSON. Example: <code>{"custom_menus": true, "analytics": false}</code>
            </p>

            <div class="form-group">
                <label for="features">Features JSON</label>
                <textarea id="features" name="features" rows="10" class="code-input"
                          placeholder='{"custom_menus": true, "page_builder": true, "analytics": false}'><?= $features ? json_encode($features, JSON_PRETTY_PRINT) : '' ?></textarea>
                <small>
                    Common features: custom_menus, page_builder, analytics, ai_features, white_label, priority_support
                </small>
            </div>

            <div class="json-helper">
                <strong>Quick Add:</strong>
                <button type="button" onclick="addFeature('custom_menus', true)">+ Custom Menus</button>
                <button type="button" onclick="addFeature('page_builder', true)">+ Page Builder</button>
                <button type="button" onclick="addFeature('analytics', true)">+ Analytics</button>
                <button type="button" onclick="addFeature('ai_features', true)">+ AI Features</button>
                <button type="button" onclick="addFeature('white_label', true)">+ White Label</button>
                <button type="button" onclick="validateJSON()">✓ Validate JSON</button>
            </div>
        </div>

        <div class="form-actions">
            <a href="<?= $basePath ?>/admin-legacy/plans" class="admin-btn admin-btn-secondary">
                <i class="fa-solid fa-times"></i> Cancel
            </a>
            <button type="submit" class="admin-btn admin-btn-primary">
                <i class="fa-solid fa-check"></i> <?= $isEdit ? 'Update Plan' : 'Create Plan' ?>
            </button>
        </div>
    </form>
</div>

<style>
.form-section {
    padding: 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.form-section:last-of-type {
    border-bottom: none;
}

.form-section h3 {
    margin: 0 0 1.5rem 0;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.form-section h3 i {
    color: #3b82f6;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
}

.form-group input[type="text"],
.form-group input[type="number"],
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 0.375rem;
    background: rgba(0, 0, 0, 0.3);
    color: #fff;
    font-size: 1rem;
}

.form-group input[type="text"]:focus,
.form-group input[type="number"]:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #3b82f6;
    background: rgba(0, 0, 0, 0.5);
}

.form-group small {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: rgba(255, 255, 255, 0.6);
}

.code-input {
    font-family: 'Courier New', monospace;
    font-size: 0.875rem;
}

.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
}

.checkbox-card {
    display: block;
    cursor: pointer;
}

.checkbox-card input {
    display: none;
}

.checkbox-card-content {
    padding: 1.5rem;
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 0.5rem;
    background: rgba(0, 0, 0, 0.2);
    text-align: center;
    transition: all 0.2s;
}

.checkbox-card input:checked + .checkbox-card-content {
    border-color: #3b82f6;
    background: rgba(59, 130, 246, 0.2);
}

.checkbox-card-content i {
    display: block;
    font-size: 2rem;
    margin-bottom: 0.5rem;
    color: rgba(255, 255, 255, 0.5);
}

.checkbox-card input:checked + .checkbox-card-content i {
    color: #3b82f6;
}

.json-helper {
    margin-top: 1rem;
    padding: 1rem;
    background: rgba(59, 130, 246, 0.1);
    border-radius: 0.5rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}

.json-helper strong {
    margin-right: 0.5rem;
}

.json-helper button {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #fff;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    cursor: pointer;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.json-helper button:hover {
    background: rgba(255, 255, 255, 0.2);
}

.form-actions {
    padding: 2rem;
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.admin-back-link {
    color: inherit;
    text-decoration: none;
    margin-right: 1rem;
}

.admin-back-link:hover {
    opacity: 0.8;
}
</style>

<script>
// Auto-generate slug from name
<?php if (!$isEdit): ?>
document.getElementById('name').addEventListener('input', function(e) {
    const slug = e.target.value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9-]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
    document.getElementById('slug').value = slug;
});
<?php endif; ?>

function addFeature(name, value) {
    const textarea = document.getElementById('features');
    let features = {};

    try {
        if (textarea.value.trim()) {
            features = JSON.parse(textarea.value);
        }
    } catch (e) {
        features = {};
    }

    features[name] = value;
    textarea.value = JSON.stringify(features, null, 2);
}

function validateJSON() {
    const textarea = document.getElementById('features');
    try {
        if (textarea.value.trim()) {
            JSON.parse(textarea.value);
        }
        alert('✓ Valid JSON');
        textarea.style.borderColor = '#22c55e';
    } catch (e) {
        alert('✗ Invalid JSON: ' + e.message);
        textarea.style.borderColor = '#ef4444';
    }
}
</script>

<?php require dirname(__DIR__) . '/partials/admin-footer.php'; ?>
