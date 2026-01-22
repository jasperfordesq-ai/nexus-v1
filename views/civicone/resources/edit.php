<?php
/**
 * CivicOne Resources Edit - Edit Resource Form
 * Template D: Form/Flow (Section 10.7)
 * Edit resource metadata with holographic glassmorphism design
 * WCAG 2.1 AA Compliant
 */
require __DIR__ . '/../../layouts/civicone/header.php';
?>
<link rel="stylesheet" href="<?= \Nexus\Core\TenantContext::getBasePath() ?>/assets/css/purged/civicone-resources-form.min.css?v=<?= time() ?>">

<!-- Offline Banner -->
<div class="holo-offline-banner" id="offlineBanner" role="alert" aria-live="polite">
    <i class="fa-solid fa-wifi-slash" aria-hidden="true"></i>
    <span>No internet connection</span>
</div>

<div class="holo-resource-page">
    <!-- Floating Orbs -->
    <div class="holo-orb holo-orb-1" aria-hidden="true"></div>
    <div class="holo-orb holo-orb-2" aria-hidden="true"></div>
    <div class="holo-orb holo-orb-3" aria-hidden="true"></div>

    <div class="holo-glass-card">
        <div class="holo-header">
            <div class="holo-header-icon" aria-hidden="true">
                <i class="fa-solid fa-pen-to-square"></i>
            </div>
            <h1 class="holo-title">Edit Resource</h1>
            <p class="holo-subtitle">Update the document details or category.</p>
        </div>

        <!-- Current File Display -->
        <div class="holo-file-display">
            <div class="holo-file-icon" aria-hidden="true">
                <i class="fa-solid fa-file-lines"></i>
            </div>
            <div class="holo-file-info">
                <div class="holo-file-name"><?= htmlspecialchars($resource['file_name'] ?? $resource['title']) ?></div>
                <div class="holo-file-hint">File cannot be changed. Upload a new resource instead.</div>
            </div>
        </div>

        <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/resources/<?= $resource['id'] ?>/update" method="POST" id="editForm">
            <?= \Nexus\Core\Csrf::input() ?>

            <!-- Title -->
            <div class="holo-form-group">
                <label for="title" class="holo-label">Document Title</label>
                <input type="text" name="title" id="title" class="holo-input" required
                       value="<?= htmlspecialchars($resource['title']) ?>">
            </div>

            <!-- Description -->
            <div class="holo-form-group">
                <label for="description" class="holo-label">Description</label>
                <textarea name="description" id="description" class="holo-input" rows="3"><?= htmlspecialchars($resource['description']) ?></textarea>
            </div>

            <!-- Category -->
            <div class="holo-form-group">
                <label for="category_id" class="holo-label">Category</label>
                <select name="category_id" id="category_id" class="holo-select">
                    <option value="">-- No Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $resource['category_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="holo-actions">
                <button type="submit" class="holo-btn holo-btn-primary">
                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                    Save Changes
                </button>
                <a href="<?= Nexus\Core\TenantContext::getBasePath() ?>/resources" class="holo-btn holo-btn-secondary">
                    Cancel
                </a>
            </div>
        </form>

        <!-- Danger Zone -->
        <div class="holo-danger-zone">
            <div class="holo-danger-label">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                Danger Zone
            </div>
            <form action="<?= Nexus\Core\TenantContext::getBasePath() ?>/resources/<?= $resource['id'] ?>/delete" method="POST"
                  onsubmit="return confirm('Are you sure you want to delete this resource? This action cannot be undone.');">
                <?= \Nexus\Core\Csrf::input() ?>
                <button type="submit" class="holo-btn holo-btn-danger">
                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                    Delete Permanently
                </button>
            </form>
        </div>
    </div>
</div>

<script src="<?= Nexus\Core\TenantContext::getBasePath() ?>/assets/js/civicone-resources-form.js?v=<?= time() ?>"></script>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
