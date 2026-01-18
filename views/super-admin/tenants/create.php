<?php
/**
 * Super Admin - Create Tenant Form
 */

use Nexus\Core\Csrf;

$pageTitle = $pageTitle ?? 'Create Tenant';
$parentTenant = $parentTenant ?? null;
$availableParents = $availableParents ?? [];
require __DIR__ . '/../partials/header.php';
?>

<!-- Breadcrumb -->
<div class="super-breadcrumb">
    <a href="/super-admin"><i class="fa-solid fa-gauge-high"></i></a>
    <span class="super-breadcrumb-sep">/</span>
    <a href="/super-admin/tenants">Tenants</a>
    <span class="super-breadcrumb-sep">/</span>
    <span>Create</span>
</div>

<!-- Page Header -->
<div class="super-page-header">
    <div>
        <h1 class="super-page-title">
            <i class="fa-solid fa-plus-circle"></i>
            Create New Tenant
        </h1>
        <p class="super-page-subtitle">
            <?php if ($parentTenant): ?>
                Creating sub-tenant under <strong><?= htmlspecialchars($parentTenant['name']) ?></strong>
            <?php else: ?>
                Select a parent tenant to create a new sub-tenant
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="super-alert super-alert-danger" style="margin-bottom: 1.5rem;">
        <i class="fa-solid fa-exclamation-circle"></i>
        <?= htmlspecialchars($_SESSION['flash_error']) ?>
    </div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div class="super-card">
    <div class="super-card-header">
        <h3 class="super-card-title">
            <i class="fa-solid fa-building"></i>
            Tenant Details
        </h3>
    </div>
    <div class="super-card-body">
        <form method="POST" action="/super-admin/tenants/store">
            <?= Csrf::field() ?>

            <!-- Parent Tenant Selection -->
            <div class="super-form-group">
                <label class="super-form-label">
                    Parent Tenant <span style="color: var(--super-danger);">*</span>
                </label>
                <select name="parent_id" class="super-form-select" required <?= $parentTenant ? 'disabled' : '' ?>>
                    <option value="">-- Select Parent Tenant --</option>
                    <?php foreach ($availableParents as $parent): ?>
                        <option value="<?= $parent['id'] ?>"
                            <?= ($parentTenant && $parentTenant['id'] == $parent['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($parent['display_name']) ?>
                            (Depth: <?= $parent['depth'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($parentTenant): ?>
                    <input type="hidden" name="parent_id" value="<?= $parentTenant['id'] ?>">
                <?php endif; ?>
                <p class="super-form-help">
                    Only tenants with sub-tenant capability are shown. The new tenant will be created as a child of the selected parent.
                </p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                <!-- Name -->
                <div class="super-form-group">
                    <label class="super-form-label">
                        Tenant Name <span style="color: var(--super-danger);">*</span>
                    </label>
                    <input type="text" name="name" class="super-form-input" required
                           placeholder="e.g., Acme Corporation"
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>

                <!-- Slug -->
                <div class="super-form-group">
                    <label class="super-form-label">
                        Slug <span style="color: var(--super-danger);">*</span>
                    </label>
                    <input type="text" name="slug" class="super-form-input" required
                           placeholder="e.g., acme-corp"
                           pattern="[a-z0-9-]+"
                           value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>">
                    <p class="super-form-help">Lowercase letters, numbers, and hyphens only</p>
                </div>
            </div>

            <!-- Tagline -->
            <div class="super-form-group">
                <label class="super-form-label">Tagline</label>
                <input type="text" name="tagline" class="super-form-input"
                       placeholder="e.g., Building the future together"
                       value="<?= htmlspecialchars($_POST['tagline'] ?? '') ?>">
            </div>

            <!-- Domain -->
            <div class="super-form-group">
                <label class="super-form-label">Custom Domain</label>
                <input type="text" name="domain" class="super-form-input"
                       placeholder="e.g., acme.example.com"
                       value="<?= htmlspecialchars($_POST['domain'] ?? '') ?>">
                <p class="super-form-help">Optional custom domain for this tenant. DNS must be configured separately.</p>
            </div>

            <!-- Description -->
            <div class="super-form-group">
                <label class="super-form-label">Description</label>
                <textarea name="description" class="super-form-textarea" rows="3"
                          placeholder="Brief description of this tenant..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <!-- Settings -->
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-top: 1rem;">
                <div class="super-form-group">
                    <label class="super-form-checkbox">
                        <input type="checkbox" name="is_active" value="1" checked>
                        <span>Active</span>
                    </label>
                    <p class="super-form-help">Inactive tenants cannot be accessed by users</p>
                </div>

                <div class="super-form-group">
                    <label class="super-form-checkbox">
                        <input type="checkbox" name="allows_subtenants" value="1">
                        <span>Allow Sub-Tenants (Hub)</span>
                    </label>
                    <p class="super-form-help">Enable this tenant to create their own sub-tenants</p>
                </div>
            </div>

            <!-- Max Depth -->
            <div class="super-form-group">
                <label class="super-form-label">Maximum Sub-Tenant Depth</label>
                <select name="max_depth" class="super-form-select">
                    <option value="0">Unlimited</option>
                    <option value="1">1 level deep</option>
                    <option value="2">2 levels deep</option>
                    <option value="3">3 levels deep</option>
                    <option value="5">5 levels deep</option>
                </select>
                <p class="super-form-help">Limit how many levels of sub-tenants can be created below this tenant (only applies if Hub is enabled)</p>
            </div>

            <!-- Submit -->
            <div style="display: flex; gap: 1rem; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--super-border);">
                <button type="submit" class="super-btn super-btn-primary">
                    <i class="fa-solid fa-plus"></i>
                    Create Tenant
                </button>
                <a href="/super-admin/tenants" class="super-btn super-btn-secondary">
                    <i class="fa-solid fa-times"></i>
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Auto-generate slug from name
document.querySelector('input[name="name"]').addEventListener('input', function(e) {
    const slugInput = document.querySelector('input[name="slug"]');
    if (!slugInput.dataset.manual) {
        slugInput.value = e.target.value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
    }
});

document.querySelector('input[name="slug"]').addEventListener('input', function(e) {
    e.target.dataset.manual = 'true';
});
</script>

<?php require __DIR__ . '/../partials/footer.php'; ?>
